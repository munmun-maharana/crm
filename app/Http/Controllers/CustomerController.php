<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\NoteTemplate;
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
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $filter = $request->string('filter')->toString();

        if ($filter === 'interested' && $status === '') {
            $status = 'interested';
        }

        $customers = Customer::with('assignee')
            ->when($q, fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%");
            }))
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
        $formFields = $this->fields->formFields();
        $tableFields = $this->fields->tableFields();

        return view('customers.index', compact('customers', 'agents', 'q', 'status', 'filter', 'formFields', 'tableFields'));
    }

    public function requests(Request $request): View
    {
        $customers = Customer::with('assignee')
            ->whereIn('status', ['interested', 'request'])
            ->latest()
            ->paginate(15);

        return view('requests.index', compact('customers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $fields = $this->fields->formFields();
        $request->validate($this->fields->validationRules($fields));
        [$core, $meta] = $this->fields->splitPayload($request, $fields);

        $customer = Customer::create([...$core, 'meta' => $meta ?: null]);

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'type' => 'system',
            'title' => 'Customer created',
            'body' => 'Record added to CRM.',
        ]);

        return back()->with('success', 'Customer created.');
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['assignee', 'activities.user']);
        $formFields = $this->fields->formFields();
        $noteTemplates = NoteTemplate::active()->ordered()->get(['id', 'title', 'body']);

        return response()->json([
            'customer' => $customer,
            'html' => view('customers.partials.panel', compact('customer', 'formFields', 'noteTemplates'))->render(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $fields = $this->fields->formFields();
        $request->validate($this->fields->validationRules($fields));
        [$core, $meta] = $this->fields->splitPayload($request, $fields);

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
            $formFields = $this->fields->formFields();
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
        $formFields = $this->fields->formFields();

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
        $formFields = $this->fields->formFields();

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

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return back()->with('success', 'Customer deleted.');
    }
}
