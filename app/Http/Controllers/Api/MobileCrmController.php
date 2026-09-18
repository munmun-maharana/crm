<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\NoteTemplate;
use App\Services\CrmFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileCrmController extends Controller
{
    public function __construct(private CrmFieldService $fields) {}

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $pipeline = $this->pipelineFrom($request);
        $this->assertAccess($request, $pipeline);
        $this->authorizeRecord($request, $customer, $pipeline);

        $customer->load([
            'assignee:id,name',
            'activities.user:id,name',
            'activationRequests.items.inventoryType:id,name',
            'activationRequests.items.inventoryItem:id,name,code',
            'activationRequests.inventoryType:id,name',
            'activationRequests.inventoryItem:id,name,code',
            'activationRequests.requester:id,name',
        ]);

        return response()->json([
            'customer' => $this->serializeCustomer($customer),
            'activities' => $customer->activities->map(fn ($a) => $this->serializeActivity($a))->values(),
            'activation_requests' => $pipeline === 'operations'
                ? $customer->activationRequests->map(fn ($r) => [
                    'id' => $r->id,
                    'type_name' => $r->inventoryType?->name,
                    'items' => $r->itemLines(),
                    'item_label' => $r->itemLabel(),
                    'status' => $r->status,
                    'status_label' => $r->statusLabel(),
                    'notes' => $r->notes,
                    'user_name' => $r->requester?->name,
                    'created_at' => $r->created_at?->format('d M Y · h:i A'),
                    'ago' => $r->created_at?->diffForHumans(),
                ])->values()
                : [],
            'statuses' => $this->statuses(),
            'activity_types' => ['note', 'call', 'whatsapp', 'email', 'sms'],
            'note_templates' => NoteTemplate::active()->ordered()->get(['id', 'title', 'body']),
            'pipeline' => $pipeline,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $pipeline = $this->pipelineFrom($request);
        $this->assertAccess($request, $pipeline);

        $formFields = $this->formFieldsFor($request, $pipeline);
        $request->validate($this->fields->validationRules($formFields));
        [$core, $meta] = $this->fields->splitPayload($request, $formFields);
        $core = $this->applyAssignment($request, $core);
        $core['pipeline'] = $pipeline;
        $core['status'] = $core['status'] ?? 'new';

        $customer = Customer::create([...$core, 'meta' => $meta ?: null]);

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'type' => 'system',
            'title' => 'Customer created',
            'body' => $pipeline === 'operations'
                ? 'Record added to Operations.'
                : 'Record added to CRM.',
        ]);

        $customer->load(['assignee:id,name', 'activities.user:id,name']);

        return response()->json([
            'ok' => true,
            'customer' => $this->serializeCustomer($customer),
            'activities' => $customer->activities->map(fn ($a) => $this->serializeActivity($a))->values(),
        ], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $pipeline = $this->pipelineFrom($request);
        $this->assertAccess($request, $pipeline);
        $this->authorizeRecord($request, $customer, $pipeline);

        $formFields = $this->formFieldsFor($request, $pipeline);
        $request->validate($this->fields->validationRules($formFields));
        [$core, $meta] = $this->fields->splitPayload($request, $formFields);
        $core = $this->applyAssignment($request, $core, $customer);

        $oldStatus = $customer->status;
        $customer->update([...$core, 'meta' => array_merge($customer->meta ?? [], $meta)]);

        if ($oldStatus !== $customer->status) {
            CustomerActivity::create([
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
                'type' => 'status',
                'title' => 'Status updated',
                'body' => "{$oldStatus} → {$customer->status}",
            ]);
        }

        $customer->load(['assignee:id,name', 'activities.user:id,name']);

        return response()->json([
            'ok' => true,
            'customer' => $this->serializeCustomer($customer->fresh()),
            'activities' => $customer->activities()->with('user:id,name')->get()
                ->map(fn ($a) => $this->serializeActivity($a))
                ->values(),
        ]);
    }

    public function addActivity(Request $request, Customer $customer): JsonResponse
    {
        $pipeline = $this->pipelineFrom($request);
        $this->assertAccess($request, $pipeline);
        $this->authorizeRecord($request, $customer, $pipeline);

        $statuses = $this->statuses();
        $data = $request->validate([
            'type' => ['required', 'in:note,call,whatsapp,email,sms'],
            'status_change' => ['nullable', 'in:'.implode(',', $statuses)],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string'],
            'follow_up' => ['nullable', 'boolean'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_time_on' => ['nullable', 'boolean'],
            'follow_up_time' => ['nullable', 'date_format:H:i'],
        ]);

        $body = trim((string) $data['body']);
        if ($body === '' || strtolower($body) === 'null') {
            return response()->json(['ok' => false, 'message' => 'Please write a note.'], 422);
        }

        $title = trim((string) ($data['title'] ?? ''));
        $activityType = $data['type'];
        $followUp = $request->boolean('follow_up');
        $hasTime = $request->boolean('follow_up_time_on');
        $followDate = $data['follow_up_date'] ?? null;
        $followTime = $data['follow_up_time'] ?? null;

        if ($followUp && empty($followDate)) {
            return response()->json(['ok' => false, 'message' => 'Follow-up date required when Follow up is checked.'], 422);
        }
        if ($followUp && $hasTime && empty($followTime)) {
            return response()->json(['ok' => false, 'message' => 'Select a follow-up time, or uncheck Time.'], 422);
        }

        $followUpAt = null;
        if ($followUp) {
            $followUpAt = $hasTime
                ? $followDate.' '.$followTime.':00'
                : $followDate.' 00:00:00';
        }

        $statusChanged = ! empty($data['status_change']) && $data['status_change'] !== $customer->status;
        $oldStatus = $customer->status;

        $customerData = [
            'last_contact_at' => now(),
            'follow_up' => $followUp,
            'follow_up_at' => $followUpAt,
            'follow_up_has_time' => $followUp && $hasTime,
        ];

        if ($statusChanged) {
            $customerData['status'] = $data['status_change'];
            if ($title === '') {
                $title = 'Update · '.$oldStatus.' → '.$data['status_change'];
            }
        } elseif ($title === '') {
            $title = ucfirst($activityType).' update';
        }

        $customer->update($customerData);

        $meta = [];
        if ($statusChanged) {
            $meta['status_from'] = $oldStatus;
            $meta['status_to'] = $data['status_change'];
        }
        if ($followUp) {
            $meta['follow_up'] = true;
            $meta['follow_up_at'] = $followUpAt;
            $meta['follow_up_has_time'] = $hasTime;
        }

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'type' => $activityType,
            'title' => $title,
            'body' => $body,
            'meta' => $meta ?: null,
        ]);

        $customer->refresh()->load(['assignee:id,name', 'activities.user:id,name']);

        return response()->json([
            'ok' => true,
            'customer' => $this->serializeCustomer($customer),
            'activities' => $customer->activities->map(fn ($a) => $this->serializeActivity($a))->values(),
        ]);
    }

    private function pipelineFrom(Request $request): string
    {
        $fromRoute = $request->route('pipeline');
        if (is_string($fromRoute) && $fromRoute !== '') {
            return $this->normalizePipeline($fromRoute);
        }

        $path = trim($request->path(), '/');
        if (str_contains($path, 'mobile/operations')) {
            return 'operations';
        }

        return 'crm';
    }

    private function normalizePipeline(string $pipeline): string
    {
        return $pipeline === 'operations' ? 'operations' : 'crm';
    }

    private function assertAccess(Request $request, string $pipeline): void
    {
        abort_unless(
            $pipeline === 'operations'
                ? $request->user()->appAccessOperations()
                : $request->user()->appAccessCrm(),
            403
        );
    }

    private function authorizeRecord(Request $request, Customer $customer, string $pipeline): void
    {
        abort_unless(($customer->pipeline ?? 'crm') === $pipeline, 404);
        abort_unless($customer->accessibleBy($request->user()), 403);
    }

    private function formFieldsFor(Request $request, string $pipeline)
    {
        $formFields = $this->fields->formFields($pipeline);
        if (! $request->user()->canAssignUsers()) {
            $formFields = $formFields->reject(fn ($field) => $field->key === 'assigned_to')->values();
        }

        return $formFields;
    }

    private function applyAssignment(Request $request, array $core, ?Customer $customer = null): array
    {
        if ($request->user()->canAssignUsers()) {
            return $core;
        }

        $core['assigned_to'] = $customer?->assigned_to ?: $request->user()->id;

        return $core;
    }

    private function statuses(): array
    {
        return ['new', 'interested', 'contacted', 'converted', 'lost', 'request'];
    }

    private function serializeCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'company' => $customer->company,
            'city' => $customer->city,
            'source' => $customer->source,
            'status' => $customer->status,
            'notes' => $customer->notes,
            'pipeline' => $customer->pipeline ?? 'crm',
            'assigned_to' => $customer->assigned_to,
            'assignee_name' => $customer->assignee?->name,
            'follow_up' => (bool) $customer->follow_up,
            'follow_up_at' => $customer->follow_up_at?->toIso8601String(),
            'follow_up_label' => $customer->followUpScheduleLabel(),
            'follow_up_has_time' => (bool) $customer->follow_up_has_time,
            'last_contact_at' => $customer->last_contact_at?->toIso8601String(),
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
            'meta' => $customer->meta ?? (object) [],
        ];
    }

    private function serializeActivity(CustomerActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'type' => $activity->type,
            'title' => $activity->title,
            'body' => $activity->body,
            'user_name' => $activity->user?->name,
            'created_at' => $activity->created_at?->format('d M Y · h:i A'),
            'meta' => $activity->meta,
        ];
    }
}
