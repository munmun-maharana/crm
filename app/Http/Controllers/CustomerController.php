<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\NoteTemplate;
use App\Models\OperationSetting;
use App\Models\User;
use App\Services\CrmFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private CrmFieldService $fields) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccessCrm(), 403);

        return $this->listing($request, 'crm');
    }

    public function operations(Request $request): View
    {
        abort_unless($request->user()->canAccessOperations(), 403);

        return $this->listing($request, 'operations');
    }

    private function listing(Request $request, string $pipeline): View
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $filter = $request->string('filter')->toString();
        $assignedTo = $request->integer('assigned_to') ?: null;
        $listRoute = $pipeline === 'operations' ? 'operations.index' : 'customers.index';

        if ($filter === 'interested' && $status === '') {
            $status = 'interested';
        }

        $customers = $this->mappedCustomers($request)
            ->inPipeline($pipeline)
            ->with('assignee')
            ->when($q, fn ($query) => $query->where(function ($inner) use ($q) {
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
            ->when($filter !== 'follow_up_today' && $status, fn ($query) => $query->where('status', $status))
            ->when($filter !== 'follow_up_today', fn ($query) => $query->latest())
            ->paginate(15)
            ->withQueryString();

        $agents = User::orderBy('name')->get(['id', 'name']);
        $formFields = $this->formFieldsFor($request, $pipeline);
        $tableFields = $this->fields->tableFields($pipeline);

        return view('customers.index', compact(
            'customers', 'agents', 'q', 'status', 'filter', 'assignedTo', 'formFields', 'tableFields', 'pipeline', 'listRoute'
        ));
    }

    public function requests(Request $request): View
    {
        abort_unless($request->user()->canAccessCrm(), 403);
        $customers = $this->mappedCustomers($request)
            ->inPipeline('crm')
            ->with('assignee')
            ->whereIn('status', ['interested', 'request'])
            ->latest()
            ->paginate(15);

        return view('requests.index', compact('customers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $pipeline = $request->input('pipeline') === 'operations' ? 'operations' : 'crm';
        abort_unless(
            $pipeline === 'operations' ? $request->user()->canAccessOperations() : $request->user()->canAccessCrm(),
            403
        );
        $fields = $this->formFieldsFor($request, $pipeline);
        $request->validate($this->fields->validationRules($fields));
        [$core, $meta] = $this->fields->splitPayload($request, $fields);
        $core = $this->applyAssignment($request, $core);
        $core['pipeline'] = $pipeline;
        $core['status'] = $core['status'] ?? 'new';

        $customer = Customer::create([...$core, 'meta' => $meta ?: null]);

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'type' => 'system',
            'title' => 'Customer created',
            'body' => $core['pipeline'] === 'operations' ? 'Record added to Operations.' : 'Record added to CRM.',
        ]);

        return back()->with('success', 'Customer created.');
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeMapped($request, $customer);
        $customer->load(['assignee', 'activities.user', 'operationCopy', 'sourceCustomer']);
        $formFields = $this->formFieldsFor($request, $customer->pipeline ?? 'crm');
        $noteTemplates = NoteTemplate::active()->ordered()->get(['id', 'title', 'body']);

        return response()->json([
            'customer' => $customer,
            'html' => view('customers.partials.panel', compact('customer', 'formFields', 'noteTemplates'))->render(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeMapped($request, $customer);
        $fields = $this->formFieldsFor($request, $customer->pipeline ?? 'crm');
        $request->validate($this->fields->validationRules($fields));
        [$core, $meta] = $this->fields->splitPayload($request, $fields);
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

        return back()->with('success', 'Customer updated.');
    }

    public function addActivity(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $this->authorizeMapped($request, $customer);
        $statuses = ['new', 'interested', 'contacted', 'converted', 'lost', 'request'];
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

        $activityType = $data['type'];
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) $data['body']);
        if ($body === '' || strtolower($body) === 'null') {
            return response()->json(['ok' => false, 'message' => 'Please write a note.'], 422);
        }

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
        if ($request->wantsJson()) {
            $customer->load(['assignee', 'activities.user']);
            $formFields = $this->formFieldsFor($request, $customer->pipeline ?? 'crm');
            $customer->refresh();

            if ($customer->follow_up && $customer->follow_up_at) {
                $timeLine = $customer->follow_up_has_time
                    ? e($customer->follow_up_at->format('h:i A'))
                    : 'Anytime';
                $followUpHtml = '<span class="inline-flex px-2 py-0.5 rounded bg-amber-50 text-amber-800 font-semibold">'
                    .e($customer->follow_up_at->format('d M Y')).'</span>'
                    .'<div class="text-[10px] text-slate-400 mt-0.5">'.$timeLine.'</div>';
            } else {
                $followUpHtml = '<span class="text-slate-400">—</span>';
            }

            return response()->json([
                'ok' => true,
                'customer_id' => $customer->id,
                'follow_up' => (bool) $customer->follow_up,
                'follow_up_at' => $customer->follow_up_at?->toIso8601String(),
                'follow_up_label' => ($customer->follow_up && $customer->follow_up_at)
                    ? 'FU '.$customer->follow_up_at->format('d M')
                    : null,
                'follow_up_html' => $followUpHtml,
                'html' => view('customers.partials.panel', [
                    'customer' => $customer,
                    'formFields' => $formFields,
                    'noteTemplates' => NoteTemplate::active()->ordered()->get(['id', 'title', 'body']),
                ])->render(),
            ]);
        }

        return back()->with('success', 'Activity added.');
    }

    public function autoAction(Request $request, Customer $customer, string $channel): JsonResponse
    {
        $this->authorizeMapped($request, $customer);
        $channel = strtolower($channel);
        abort_unless($channel === 'call', 404);

        $phone = preg_replace('/\D+/', '', (string) $customer->phone);
        if ($phone === '') {
            return response()->json(['ok' => false, 'message' => 'Phone number missing.'], 422);
        }

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'type' => 'call',
            'title' => 'Call',
            'body' => "Call initiated to {$customer->phone}.",
        ]);

        $customer->update(['last_contact_at' => now()]);
        $customer->load(['assignee', 'activities.user']);
        $formFields = $this->formFieldsFor($request, $customer->pipeline ?? 'crm');

        return response()->json([
            'ok' => true,
            'open_url' => 'tel:'.$phone,
            'html' => view('customers.partials.panel', [
                'customer' => $customer,
                'formFields' => $formFields,
                'noteTemplates' => NoteTemplate::active()->ordered()->get(['id', 'title', 'body']),
            ])->render(),
        ]);
    }

    public function compose(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeMapped($request, $customer);
        if ($request->input('link') === '') {
            $request->merge(['link' => null]);
        }

        $data = $request->validate([
            'channel' => ['required', 'in:whatsapp,email,sms'],
            'message' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'max:200'],
            'link' => ['nullable', 'url', 'max:500'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
        ]);

        $channel = $data['channel'];
        $phone = preg_replace('/\D+/', '', (string) $customer->phone);
        $email = trim((string) $customer->email);

        if (in_array($channel, ['whatsapp', 'sms'], true) && $phone === '') {
            return response()->json(['ok' => false, 'message' => 'Phone number missing.'], 422);
        }
        if ($channel === 'email' && $email === '') {
            return response()->json(['ok' => false, 'message' => 'Email missing.'], 422);
        }

        $message = trim($data['message']);
        $link = trim((string) ($data['link'] ?? ''));
        $attachments = [];

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('compose/'.$customer->id, 'public');
                $url = asset('storage/'.$path);
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'url' => $url,
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        $parts = [$message];
        if ($link !== '') {
            $parts[] = $link;
        }
        foreach ($attachments as $a) {
            $parts[] = $a['url'];
        }
        $fullText = implode("\n\n", $parts);

        $titles = ['whatsapp' => 'WhatsApp', 'email' => 'Email', 'sms' => 'SMS'];
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'type' => $channel,
            'title' => $titles[$channel].(! empty($data['subject']) ? ' · '.$data['subject'] : ''),
            'body' => $fullText,
            'meta' => [
                'link' => $link ?: null,
                'attachments' => $attachments ?: null,
                'subject' => $data['subject'] ?? null,
            ],
        ]);

        $customer->update(['last_contact_at' => now()]);
        $customer->load(['assignee', 'activities.user']);
        $formFields = $this->formFieldsFor($request, $customer->pipeline ?? 'crm');

        $openUrl = match ($channel) {
            'whatsapp' => 'https://wa.me/'.$phone.'?text='.rawurlencode($fullText),
            'email' => 'mailto:'.rawurlencode($email)
                .'?subject='.rawurlencode($data['subject'] ?: 'Hello '.$customer->name)
                .'&body='.rawurlencode($fullText),
            'sms' => 'sms:'.$phone.'?body='.rawurlencode($fullText),
        };

        return response()->json([
            'ok' => true,
            'open_url' => $openUrl,
            'html' => view('customers.partials.panel', [
                'customer' => $customer,
                'formFields' => $formFields,
                'noteTemplates' => NoteTemplate::active()->ordered()->get(['id', 'title', 'body']),
            ])->render(),
        ]);
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeMapped($request, $customer);
        $customer->delete();

        return back()->with('success', 'Customer deleted.');
    }

    public function assignToOperations(Request $request, Customer $customer): JsonResponse|RedirectResponse
    {
        $this->authorizeMapped($request, $customer);

        abort_unless($request->user()->canAccessCrm(), 403);

        if ($customer->isOperations()) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'message' => 'This record is already in Operations.'], 422)
                : back()->withErrors(['customer' => 'This record is already in Operations.']);
        }

        if (Customer::where('source_customer_id', $customer->id)->exists()) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'message' => 'Already assigned to Operation Team.'], 422)
                : back()->withErrors(['customer' => 'Already assigned to Operation Team.']);
        }

        $copy = Customer::create($customer->mappedOperationAttributes());
        $copy->update(['status' => 'new']);

        $setting = OperationSetting::current();
        $oldStatus = $customer->status;
        $newStatus = $setting->status ?: $customer->status;
        $statusChanged = $newStatus !== $oldStatus;

        if ($statusChanged) {
            $customer->update(['status' => $newStatus]);
        }

        $title = trim((string) $setting->title) ?: 'Assigned to Operation Team';
        $body = trim((string) $setting->body) ?: 'Lead handed over to Operation Team.';
        $meta = [];
        if ($statusChanged) {
            $meta['status_from'] = $oldStatus;
            $meta['status_to'] = $newStatus;
        }

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'type' => $statusChanged ? 'status' : 'note',
            'title' => $title,
            'body' => $body,
            'meta' => $meta ?: null,
        ]);

        CustomerActivity::create([
            'customer_id' => $copy->id,
            'user_id' => $request->user()->id,
            'type' => 'system',
            'title' => 'Received from CRM',
            'body' => 'Copied from CRM #'.$customer->id.' using mapped Operation fields.',
        ]);

        $customer->refresh();

        if ($request->wantsJson()) {
            $customer->load(['assignee', 'activities.user', 'operationCopy']);
            $formFields = $this->formFieldsFor($request, $customer->pipeline ?? 'crm');

            return response()->json([
                'ok' => true,
                'message' => 'Assigned to Operation Team.',
                'html' => view('customers.partials.panel', [
                    'customer' => $customer,
                    'formFields' => $formFields,
                    'noteTemplates' => NoteTemplate::active()->ordered()->get(['id', 'title', 'body']),
                ])->render(),
            ]);
        }

        return back()->with('success', 'Assigned to Operation Team.');
    }

    public function bulkMap(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canBulkMap(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:customers,id'],
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $agent = User::findOrFail($data['assigned_to']);
        $ids = collect($data['ids'])->unique()->values();

        Customer::whereIn('id', $ids)->update(['assigned_to' => $agent->id]);

        foreach ($ids as $id) {
            CustomerActivity::create([
                'customer_id' => $id,
                'user_id' => $request->user()->id,
                'type' => 'system',
                'title' => 'User mapping updated',
                'body' => "Bulk mapped to {$agent->name}.",
            ]);
        }

        return back()->with('success', "Mapped {$ids->count()} customers to {$agent->name}.");
    }

    private function formFieldsFor(Request $request, string $pipeline = 'crm')
    {
        $formFields = $this->fields->formFields($pipeline);
        if (! $request->user()->canAssignUsers()) {
            $formFields = $formFields->reject(fn ($field) => $field->key === 'assigned_to')->values();
        }

        return $formFields;
    }

    private function mappedCustomers(Request $request)
    {
        return Customer::query()->visibleTo($request->user());
    }

    private function authorizeMapped(Request $request, Customer $customer): void
    {
        abort_unless($customer->accessibleBy($request->user()), 403);
    }

    private function applyAssignment(Request $request, array $core, ?Customer $customer = null): array
    {
        if ($request->user()->canAssignUsers()) {
            return $core;
        }

        $core['assigned_to'] = $customer?->assigned_to ?: $request->user()->id;

        return $core;
    }
}
