<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsPayload;
use App\Http\Controllers\Controller;
use App\Models\CrmField;
use App\Models\Customer;
use App\Models\NoteTemplate;
use App\Models\OperationSetting;
use App\Models\User;
use App\Services\CrmFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read endpoints for CRM / TEAM OPS records (mirrors customers.index, operations.index,
 * requests.index and the customer side panel). All writes go through the web
 * CustomerController via routes/api/v1.php so behaviour stays identical.
 */
class CustomerController extends Controller
{
    use FormatsPayload;

    public function __construct(private CrmFieldService $fields) {}

    private function assertPipelineAccess(Request $request, string $pipeline): void
    {
        abort_unless(
            $pipeline === 'operations' ? $request->user()->canAccessOperations() : $request->user()->canAccessCrm(),
            403
        );
    }

    private function formFieldsFor(Request $request, string $pipeline)
    {
        $formFields = $this->fields->formFields($pipeline);
        if (! $request->user()->canAssignUsers()) {
            $formFields = $formFields->reject(fn ($f) => $f->key === 'assigned_to')->values();
        }

        return $formFields;
    }

    /** GET /customers?pipeline=crm|operations&q=&status=&filter=&assigned_to=&page= */
    public function index(Request $request): JsonResponse
    {
        $pipeline = $this->pipelineOf($request->string('pipeline')->toString());
        $this->assertPipelineAccess($request, $pipeline);

        $q = trim($request->string('q')->toString());
        $status = trim($request->string('status')->toString());
        $filter = trim($request->string('filter')->toString());
        $assignedTo = $request->integer('assigned_to') ?: null;
        $perPage = min(50, max(5, (int) $request->integer('per_page', 15)));

        if ($filter === 'interested' && $status === '') {
            $status = 'interested';
        }

        $base = Customer::query()->visibleTo($request->user())->inPipeline($pipeline);

        $page = (clone $base)
            ->with('assignee')
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%");
            }))
            ->when($request->user()->canSeeAllRecords() && $assignedTo, fn ($query) => $query->where('assigned_to', $assignedTo))
            ->when($filter === 'follow_up_today', function ($query) {
                $query->where('follow_up', true)
                    ->whereDate('follow_up_at', today())
                    ->orderByRaw('CASE WHEN follow_up_has_time = 1 THEN 0 ELSE 1 END')
                    ->orderBy('follow_up_at');
            })
            ->when($filter !== 'follow_up_today' && $status !== '', function ($query) use ($status) {
                $query->where(function ($sub) use ($status) {
                    $sub->where('status', $status)
                        ->orWhere('status', str_replace(' ', '-', $status))
                        ->orWhere('status', str_replace('-', ' ', $status));
                });
            })
            ->when($filter !== 'follow_up_today', fn ($query) => $query->latest())
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'pipeline' => $pipeline,
            'filters' => ['q' => $q, 'status' => $status, 'filter' => $filter, 'assigned_to' => $assignedTo],
            'summary' => [
                'total' => (clone $base)->count(),
                'today' => (clone $base)->whereDate('created_at', today())->count(),
                'follow_up_today' => (clone $base)->where('follow_up', true)->whereDate('follow_up_at', today())->count(),
            ],
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'has_more' => $page->hasMorePages(),
            ],
            'customers' => collect($page->items())->map(fn ($c) => $this->customerRow($c))->values(),
        ]);
    }

    /** GET /customers/meta?pipeline= — form/table/excel fields, agents, statuses, permissions */
    public function meta(Request $request): JsonResponse
    {
        $pipeline = $this->pipelineOf($request->string('pipeline')->toString());
        $this->assertPipelineAccess($request, $pipeline);
        $user = $request->user();

        return response()->json([
            'ok' => true,
            'pipeline' => $pipeline,
            'form_fields' => $this->fieldsPayload($this->formFieldsFor($request, $pipeline)),
            'table_fields' => $this->fieldsPayload($this->fields->tableFields($pipeline)),
            'excel_fields' => $this->fieldsPayload($this->fields->excelFields($pipeline)),
            'status_options' => $this->statusOptionsFor($pipeline),
            'agents' => User::orderBy('name')->get(['id', 'name', 'role'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role])->values(),
            'note_templates' => NoteTemplate::active()->ordered()->get(['id', 'title', 'body']),
            'permissions' => [
                'can_assign_users' => $user->canAssignUsers(),
                'can_bulk_map' => $user->canBulkMap(),
                'can_see_all' => $user->canSeeAllRecords(),
                'can_import' => $user->canAccessCrm(),
            ],
        ]);
    }

    /** GET /customers/{customer} — everything the web side panel shows */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($customer->accessibleBy($request->user()), 403);
        $pipeline = $customer->pipeline ?? 'crm';

        $customer->load(['assignee', 'activities.user', 'operationCopy', 'sourceCustomer']);

        $allFields = CrmField::inPipeline($pipeline)->active()->ordered()->get();
        $fieldValues = $allFields->map(function (CrmField $f) use ($customer) {
            $value = $customer->fieldValue($f->key);
            if ($f->key === 'assigned_to') {
                $value = $customer->assignee?->name;
            }

            return [
                ...$this->fieldPayload($f),
                'value' => is_array($value) ? implode(', ', $value) : $value,
                'raw_value' => $f->key === 'assigned_to' ? $customer->assigned_to : $customer->fieldValue($f->key),
            ];
        })->values();

        $updateFields = $this->updateFieldsFor($customer)->map(fn (CrmField $f) => [
            ...$this->fieldPayload($f),
            'value' => (string) ($customer->fieldValue($f->key) ?? ''),
        ])->values();

        $user = $request->user();
        $isOps = $customer->isOperations();

        return response()->json([
            'ok' => true,
            'customer' => [
                ...$this->customerRow($customer),
                'operation_copy_id' => $customer->operationCopy?->id,
                'source_customer_id' => $customer->source_customer_id,
                'source_customer_name' => $customer->sourceCustomer?->name,
            ],
            'fields' => $fieldValues,
            'form_fields' => $this->fieldsPayload($this->formFieldsFor($request, $pipeline)),
            'update_fields' => $updateFields,
            'status_options' => $this->statusOptionsFor($pipeline),
            'wash_statuses' => $isOps ? ['Success', 'Cancelled', 'Pending'] : [],
            'note_templates' => NoteTemplate::active()->ordered()->get(['id', 'title', 'body']),
            'activities' => $customer->activities->sortByDesc('id')->map(fn ($a) => $this->activityPayload($a))->values(),
            'operation_setting' => $isOps ? null : OperationSetting::current()->only(['status', 'title', 'body']),
            'permissions' => [
                'can_edit' => true,
                'can_delete' => true,
                'can_assign_operations' => ! $isOps && $user->canAccessCrm() && ! $customer->operationCopy,
                'is_assigned_to_ops' => ! $isOps && (bool) $customer->operationCopy,
                'can_assign_users' => $user->canAssignUsers(),
            ],
        ]);
    }

    /** GET /requests — Interested / Request leads (web requests.index) */
    public function requests(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAccessCrm(), 403);
        $page = Customer::query()->visibleTo($request->user())
            ->inPipeline('crm')
            ->with('assignee')
            ->whereIn('status', ['interested', 'request'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'ok' => true,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'has_more' => $page->hasMorePages(),
            ],
            'customers' => collect($page->items())->map(fn ($c) => $this->customerRow($c))->values(),
        ]);
    }

    /** Same field selection the web addActivity() uses for "update_fields". */
    private function updateFieldsFor(Customer $customer)
    {
        $updateFields = CrmField::inPipeline($customer->pipeline ?? 'crm')
            ->active()->where('key', '!=', 'status')->ordered()->get()
            ->filter(fn ($f) => (bool) ($f->in_update ?? false))->values();

        if ($customer->isOperations() && $updateFields->isEmpty()) {
            $crmUpdateKeys = CrmField::inPipeline('crm')->active()->where('key', '!=', 'status')->get()
                ->filter(fn ($f) => (bool) ($f->in_update ?? false))->pluck('key')->all();
            $opAll = CrmField::inPipeline('operations')->active()->where('key', '!=', 'status')->ordered()->get();
            $updateFields = $opAll->filter(fn ($f) => in_array($f->maps_from, $crmUpdateKeys, true) || in_array($f->key, $crmUpdateKeys, true))->values();
            if ($updateFields->isEmpty()) {
                $skip = ['name', 'email', 'phone', 'company', 'status', 'assigned_to', 'notes', 'date', 'entry_date', 'created_date', 'created_at'];
                $updateFields = $opAll->reject(fn ($f) => in_array(strtolower($f->key), $skip, true))->values();
            }
        }

        return $updateFields;
    }
}

