<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\NoteTemplate;
use App\Models\OperationSetting;
use App\Models\WhatsappSetting;
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
            ->when($filter !== 'follow_up_today' && $status, function ($query) use ($status) {
                $query->where(function ($sub) use ($status) {
                    $sub->where('status', $status)
                        ->orWhere('status', str_replace(' ', '-', $status))
                        ->orWhere('status', str_replace('-', ' ', $status));
                });
            })
            ->when($filter !== 'follow_up_today', fn ($query) => $query->latest())
            ->paginate(15)
            ->withQueryString();

        $agents = User::orderBy('name')->get(['id', 'name']);
        $formFields = $this->formFieldsFor($request, $pipeline);
        $tableFields = $this->fields->tableFields($pipeline);
        $excelFields = $this->fields->excelFields($pipeline);

        $statusField = CrmField::inPipeline($pipeline)->where('key', 'status')->first();
        $statusOptions = ! empty($statusField?->options)
            ? $statusField->options
            : ['new', 'interested', 'contacted', 'converted', 'lost', 'request'];

        return view('customers.index', compact(
            'customers', 'agents', 'q', 'status', 'filter', 'assignedTo', 'formFields', 'tableFields', 'excelFields', 'pipeline', 'listRoute', 'statusOptions'
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
        $customer->load([
            'assignee',
            'activities.user',
            'operationCopy',
            'sourceCustomer',
        ]);
        $formFields = $this->formFieldsFor($request, $customer->pipeline ?? 'crm');
        $noteTemplates = NoteTemplate::active()->ordered()->get(['id', 'title', 'body']);
        $statusField = CrmField::inPipeline($customer->pipeline ?? 'crm')->where('key', 'status')->first();
        $statusOptions = ! empty($statusField?->options)
            ? $statusField->options
            : ['new', 'interested', 'contacted', 'converted', 'lost', 'request'];

        return response()->json([
            'customer' => $customer,
            'html' => view('customers.partials.panel', compact(
                'customer',
                'formFields',
                'statusOptions',
                'noteTemplates'
            ))->render(),
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
        $statusField = CrmField::inPipeline($customer->pipeline ?? 'crm')->where('key', 'status')->first();
        $statusOptions = ! empty($statusField?->options)
            ? $statusField->options
            : ['new', 'interested', 'contacted', 'converted', 'lost', 'request'];

        $data = $request->validate([
            'type' => ['required', 'in:note,feedback,call,whatsapp,email,sms'],
            'status_change' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string'],
            'follow_up' => ['nullable', 'boolean'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_time_on' => ['nullable', 'boolean'],
            'follow_up_time' => ['nullable', 'date_format:H:i'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['nullable', 'image', 'max:10240'],
        ]);

        $activityType = $data['type'];
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        $uploadedImages = [];
        if ($request->hasFile('images')) {
            $uploadDir = public_path('uploads/activities/'.$customer->id);
            if (! is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            foreach ((array) $request->file('images') as $imgFile) {
                if (! $imgFile || ! $imgFile->isValid()) {
                    continue;
                }
                $origName = $imgFile->getClientOriginalName();
                $ext = strtolower($imgFile->getClientOriginalExtension() ?: 'jpg');
                $safeName = uniqid('img_', true).'.'.$ext;
                $imgFile->move($uploadDir, $safeName);
                $relPath = 'uploads/activities/'.$customer->id.'/'.$safeName;
                $uploadedImages[] = [
                    'name' => $origName,
                    'path' => $relPath,
                    'url' => asset($relPath),
                ];
            }
        }

        $statusChangeVal = trim((string) ($data['status_change'] ?? ''));
        $targetStatus = null;
        if ($statusChangeVal !== '') {
            $lowerVal = strtolower($statusChangeVal);
            if ($customer->isOperations() && in_array($lowerVal, ['success', 'wash success', 'completed'], true)) {
                $targetStatus = 'Success';
            } elseif ($customer->isOperations() && in_array($lowerVal, ['cancel', 'cancelled', 'canceled'], true)) {
                $targetStatus = 'Cancelled';
            } elseif ($customer->isOperations() && $lowerVal === 'pending') {
                $targetStatus = 'Pending';
            } else {
                $matched = collect($statusOptions)->first(fn ($s) => strtolower(trim((string)$s)) === $lowerVal);
                $targetStatus = $matched ?: $statusChangeVal;
            }
        }

        $isWashAction = $request->boolean('wash_action');
        $statusChanged = ($targetStatus !== null && strtolower((string)$targetStatus) !== strtolower((string)$customer->status))
            || ($isWashAction && $targetStatus !== null);
        $oldStatus = $customer->status;

        $followUp = $request->boolean('follow_up');
        $hasTime = $request->boolean('follow_up_time_on');
        $followDate = $data['follow_up_date'] ?? null;
        $followTime = $data['follow_up_time'] ?? null;

        if ($statusChanged && in_array(strtolower((string) $targetStatus), ['confirmed', 'confirm', 'converted', 'success'], true)) {
            $followUp = false;
            $hasTime = false;
            $followDate = null;
            $followTime = null;
        }

        if ($followUp && empty($followDate)) {
            return response()->json(['ok' => false, 'message' => 'Follow-up date required when Follow up is checked.'], 422);
        }
        if ($followUp && $hasTime && empty($followTime)) {
            return response()->json(['ok' => false, 'message' => 'Select a follow-up time, or uncheck Time.'], 422);
        }

        $updateFields = CrmField::inPipeline($customer->pipeline ?? 'crm')
            ->active()
            ->where('key', '!=', 'status')
            ->ordered()
            ->get()
            ->filter(fn ($f) => (bool) ($f->in_update ?? false))
            ->values();

        $fieldChanges = [];
        $currentMeta = is_array($customer->meta) ? $customer->meta : [];
        $coreFieldUpdates = [];
        $inputUpdateFields = $request->input('update_fields', []);

        if (is_array($inputUpdateFields)) {
            foreach ($updateFields as $uf) {
                if (! array_key_exists($uf->key, $inputUpdateFields)) {
                    continue;
                }
                $newVal = trim((string) ($inputUpdateFields[$uf->key] ?? ''));
                $oldVal = trim((string) ($customer->fieldValue($uf->key) ?? ''));

                if ($newVal !== $oldVal) {
                    $fieldChanges[$uf->label] = [
                        'key' => $uf->key,
                        'from' => $oldVal !== '' ? $oldVal : '—',
                        'to' => $newVal !== '' ? $newVal : '—',
                    ];

                    if ($uf->isCore()) {
                        $coreFieldUpdates[$uf->key] = $newVal !== '' ? $newVal : null;
                    } else {
                        if ($newVal === '') {
                            unset($currentMeta[$uf->key]);
                        } else {
                            $currentMeta[$uf->key] = $newVal;
                        }
                    }

                    if (($uf->type === 'phone' || in_array($uf->key, ['phone', 'phone_no', 'mobile', 'contact', 'contact_no'], true)) && $newVal !== '') {
                        $coreFieldUpdates['phone'] = $newVal;
                    }
                    if (($uf->type === 'email' || in_array($uf->key, ['email', 'email_address', 'mail'], true)) && $newVal !== '') {
                        $coreFieldUpdates['email'] = $newVal;
                    }
                }
            }
        }

        if ($body === '' || strtolower($body) === 'null') {
            if ($statusChanged && $followUp) {
                $body = "Status updated to {$targetStatus} and follow-up scheduled.";
            } elseif ($statusChanged) {
                $body = "Status updated to {$targetStatus}.";
            } elseif ($followUp) {
                $body = "Follow-up scheduled.";
            } elseif (! empty($fieldChanges)) {
                $parts = [];
                foreach ($fieldChanges as $label => $chg) {
                    $parts[] = "{$label}: {$chg['from']} → {$chg['to']}";
                }
                $body = "Updated " . implode(', ', $parts) . ".";
            } elseif (! empty($uploadedImages)) {
                $body = "Uploaded " . count($uploadedImages) . " image(s).";
            } else {
                return response()->json(['ok' => false, 'message' => 'Please write feedback/note, upload an image, change status/fields, or set a follow-up.'], 422);
            }
        }

        $followUpAt = null;
        $followUpHasTime = false;
        if ($followUp) {
            $followUpAt = $hasTime
                ? $followDate.' '.$followTime.':00'
                : $followDate.' 00:00:00';
            $followUpHasTime = $hasTime;
            unset($currentMeta['booking_slot_time']);
        } elseif ($statusChanged && in_array(strtolower((string) $targetStatus), ['confirmed', 'confirm', 'converted'], true)) {
            $followUpAt = now();
            $followUpHasTime = false;
        } elseif (! $customer->follow_up && $customer->follow_up_at) {
            // Preserve existing booking confirmation date if user just posts a note without follow-up
            $followUpAt = $customer->follow_up_at;
            $followUpHasTime = (bool) $customer->follow_up_has_time;
        }

        $customerData = array_merge($coreFieldUpdates, [
            'last_contact_at' => now(),
            'follow_up' => $followUp,
            'follow_up_at' => $followUpAt,
            'follow_up_has_time' => $followUpHasTime,
            'meta' => $currentMeta ?: null,
        ]);

        if ($statusChanged) {
            $customerData['status'] = $targetStatus;
            if ($title === '') {
                $title = 'Update · '.$oldStatus.' → '.$targetStatus;
            }
        } elseif ($title === '') {
            $title = ucfirst($activityType).' update';
        }

        $customer->update($customerData);

        $meta = [];
        if ($statusChanged) {
            $meta['status_from'] = $oldStatus;
            $meta['status_to'] = $targetStatus;
        }
        if ($followUp) {
            $meta['follow_up'] = true;
            $meta['follow_up_at'] = $followUpAt;
            $meta['follow_up_has_time'] = $hasTime;
        }
        if (! empty($fieldChanges)) {
            $meta['field_changes'] = $fieldChanges;
        }
        if (! empty($uploadedImages)) {
            $meta['images'] = $uploadedImages;
        }

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'type' => $activityType,
            'title' => $title,
            'body' => $body,
            'meta' => $meta ?: null,
        ]);

        // If this is a TEAM OPS record, sync status & mapped field updates to CRM customer and Booking Slot
        if ($customer->isOperations()) {
            $sourceCustomer = $customer->source_customer_id
                ? Customer::find($customer->source_customer_id)
                : Customer::query()
                    ->where('pipeline', 'crm')
                    ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $customer->name))])
                    ->latest('id')
                    ->first();

            if ($sourceCustomer) {
                $srcCore = [];
                if ($statusChanged && $targetStatus !== null) {
                    $srcCore['status'] = $targetStatus;
                    $srcCore['last_contact_at'] = now();
                }

                if (is_array($inputUpdateFields) && $updateFields->isNotEmpty()) {
                    $srcMeta = is_array($sourceCustomer->meta) ? $sourceCustomer->meta : [];
                    foreach ($updateFields as $uf) {
                        $mappedCrmKey = trim((string) ($uf->maps_from ?? ''));
                        if ($mappedCrmKey === '' || ! array_key_exists($uf->key, $inputUpdateFields)) {
                            continue;
                        }
                        $val = trim((string) ($inputUpdateFields[$uf->key] ?? ''));
                        if (in_array($mappedCrmKey, CrmField::CORE_KEYS, true)) {
                            $srcCore[$mappedCrmKey] = $val !== '' ? $val : null;
                        } else {
                            if ($val === '') {
                                unset($srcMeta[$mappedCrmKey]);
                            } else {
                                $srcMeta[$mappedCrmKey] = $val;
                            }
                        }
                    }
                    if ($srcMeta !== (is_array($sourceCustomer->meta) ? $sourceCustomer->meta : [])) {
                        $srcCore['meta'] = $srcMeta ?: null;
                    }
                }

                if (! empty($srcCore)) {
                    $sourceCustomer->update($srcCore);
                }

                CustomerActivity::create([
                    'customer_id' => $sourceCustomer->id,
                    'user_id' => $request->user()->id,
                    'type' => $activityType,
                    'title' => 'OPS · '.$title,
                    'body' => $body,
                    'meta' => $meta ?: null,
                ]);
            }

            if ($statusChanged && $targetStatus !== null) {
                $lowerTarget = strtolower((string) $targetStatus);
                $newSlotStatus = match (true) {
                    in_array($lowerTarget, ['success', 'wash success', 'completed', 'converted'], true) => \App\Models\BookingSlot::STATUS_SUCCESS,
                    in_array($lowerTarget, ['cancel', 'cancelled', 'canceled', 'lost'], true) => \App\Models\BookingSlot::STATUS_CANCELLED,
                    default => \App\Models\BookingSlot::STATUS_BOOKED,
                };

                $targetIds = array_values(array_filter([
                    $customer->id,
                    $customer->source_customer_id,
                    $sourceCustomer?->id,
                ]));

                $slotQuery = \App\Models\BookingSlot::query()->where(function ($q) use ($targetIds, $customer) {
                    if (! empty($targetIds)) {
                        $q->whereIn('customer_id', $targetIds);
                    }
                    if (trim((string) $customer->name) !== '') {
                        $q->orWhereRaw('LOWER(customer_name) = ?', [strtolower(trim((string) $customer->name))]);
                    }
                });

                $slotsToUpdate = collect();
                if ($customer->follow_up_at) {
                    $slotsToUpdate = (clone $slotQuery)
                        ->whereDate('slot_date', $customer->follow_up_at->toDateString())
                        ->get();
                }
                if ($slotsToUpdate->isEmpty()) {
                    $slotsToUpdate = $slotQuery->latest('slot_date')->limit(1)->get();
                }

                foreach ($slotsToUpdate as $bSlot) {
                    $bSlot->update(['status' => $newSlotStatus]);
                }
            }
        }
        if ($request->wantsJson()) {
            $customer->load(['assignee', 'activities.user']);
            $formFields = $this->formFieldsFor($request, $customer->pipeline ?? 'crm');
            $customer->refresh();

            $followUpHtml = $customer->actionDateCellHtml();

            $toneMap = [
                'new' => 'text-sky-700 bg-sky-50',
                'pending' => 'text-amber-800 bg-amber-100',
                'no answer' => 'text-slate-700 bg-slate-100',
                'follow-up' => 'text-amber-800 bg-amber-100',
                'interested' => 'text-teal-700 bg-teal-50',
                'contacted' => 'text-blue-700 bg-blue-50',
                'converted' => 'text-emerald-700 bg-emerald-50',
                'success' => 'text-emerald-700 bg-emerald-50',
                'cancelled' => 'text-rose-700 bg-rose-50',
                'lost' => 'text-rose-700 bg-rose-50',
                'confirmed' => 'text-emerald-700 bg-emerald-50',
                'request' => 'text-amber-800 bg-amber-50',
            ];
            $statusToneClass = $toneMap[strtolower((string)$customer->status)] ?? 'bg-sky-50 text-sky-800';

            return response()->json([
                'ok' => true,
                'customer_id' => $customer->id,
                'status' => $customer->status,
                'status_tone_class' => $statusToneClass,
                'follow_up' => (bool) $customer->follow_up,
                'follow_up_at' => $customer->follow_up_at?->toIso8601String(),
                'follow_up_label' => ($customer->follow_up && $customer->follow_up_at)
                    ? 'FU '.$customer->follow_up_at->format('d M')
                    : null,
                'follow_up_html' => $followUpHtml,
                'list_date_html' => $customer->listDateSummaryHtml(),
                'html' => view('customers.partials.panel', [
                    'customer' => $customer,
                    'formFields' => $formFields,
                    'statusOptions' => $statusOptions,
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
        $copy->update(['status' => 'Pending']);

        $setting = OperationSetting::current();
        $oldStatus = $customer->status;
        $newStatus = $setting->status ?: $customer->status;
        $statusChanged = $newStatus !== $oldStatus;

        $customer->update([
            'status' => $newStatus,
            'follow_up' => false,
            'follow_up_at' => null,
            'follow_up_has_time' => false,
        ]);

        $title = trim((string) $setting->title) ?: 'Assigned to Operation Team';
        $body = trim((string) $setting->body) ?: 'Lead handed over to Operation Team.';
        $meta = [];
        if ($statusChanged) {
            $meta['status_from'] = $oldStatus;
            $meta['status_to'] = $newStatus;
        }
        $meta['follow_up_cleared'] = true;

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
                'customer_id' => $customer->id,
                'follow_up' => false,
                'follow_up_label' => null,
                'follow_up_html' => '<span class="text-slate-400">—</span>',
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

    public function bulkWhatsapp(Request $request): JsonResponse
    {
        $setting = WhatsappSetting::current();
        $targetPhone = $setting->normalizedPhone();

        if (empty($targetPhone)) {
            return response()->json([
                'ok' => false,
                'message' => 'Please set the WhatsApp number in Settings first.',
                'settings_url' => route('settings.index', ['tab' => 'whatsapp']),
            ], 422);
        }

        $rawIds = $request->input('ids');
        if (is_string($rawIds)) {
            $rawIds = explode(',', $rawIds);
        }
        $ids = is_array($rawIds) ? array_filter(array_map('intval', $rawIds)) : [];

        if (empty($ids)) {
            return response()->json(['ok' => false, 'message' => 'Please select at least one customer.'], 422);
        }

        $customers = Customer::query()
            ->whereIn('id', $ids)
            ->visibleTo($request->user())
            ->with('assignee')
            ->orderBy('id', 'desc')
            ->get();

        if ($customers->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'No accessible customers found.'], 404);
        }

        $pipeline = $customers->first()->pipeline ?? 'crm';
        $crmFields = CrmField::inPipeline($pipeline)->active()->ordered()->get();

        $lines = [];
        $count = $customers->count();
        $lines[] = "📋 *CUSTOMER LEAD DATA* (" . $count . " " . ($count > 1 ? "Records" : "Record") . ")";
        $lines[] = "📅 *Date:* " . now()->format('d M Y, h:i A');
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";

        foreach ($customers as $index => $customer) {
            $num = $count > 1 ? " #" . ($index + 1) : "";
            $lines[] = "👤 *CUSTOMER{$num}:* *" . trim($customer->name) . "*";

            foreach ($crmFields as $f) {
                if ($f->key === 'name') {
                    continue;
                }
                $val = $customer->fieldValue($f->key);
                if ($f->key === 'assigned_to') {
                    $val = $customer->assignee?->name;
                }
                if ($val !== null && $val !== '' && ! (is_array($val) && empty($val))) {
                    if (is_array($val)) {
                        $val = implode(', ', $val);
                    }
                    $lines[] = "• *{$f->label}:* " . trim((string) $val);
                }
            }

            if ($customer->follow_up && $customer->follow_up_at) {
                $lines[] = "• *Follow-up:* " . $customer->followUpScheduleLabel();
            }

            if ($index < $count - 1) {
                $lines[] = "────────────────────";
            }
        }

        $messageText = implode("\n", $lines);
        $encodedText = rawurlencode($messageText);
        $whatsappUrl = "https://api.whatsapp.com/send?phone={$targetPhone}&text={$encodedText}";

        return response()->json([
            'ok' => true,
            'phone' => $targetPhone,
            'url' => $whatsappUrl,
            'count' => $count,
        ]);
    }

    public function bulkEmail(Request $request): JsonResponse
    {
        $rawIds = $request->input('ids');
        if (is_string($rawIds)) {
            $rawIds = explode(',', $rawIds);
        }
        $ids = is_array($rawIds) ? array_filter(array_map('intval', $rawIds)) : [];

        if (empty($ids)) {
            return response()->json(['ok' => false, 'message' => 'Please select at least one customer.'], 422);
        }

        $customers = Customer::query()
            ->whereIn('id', $ids)
            ->visibleTo($request->user())
            ->with(['assignee', 'activities'])
            ->orderBy('id', 'desc')
            ->get();

        if ($customers->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'No accessible customers found.'], 404);
        }

        $headers = ['Date', 'Name', 'City', 'Mobile Number', 'Leads Status', 'Feedback', 'Amount Received', 'Payment Mode'];
        $rows = [];

        foreach ($customers as $c) {
            $date = $c->fieldValue('date') ?: ($c->created_at ? $c->created_at->format('d-m-Y') : '');
            $name = trim((string) $c->name);
            $city = trim((string) ($c->city ?: $c->fieldValue('city')));
            $phone = trim((string) ($c->phone ?: $c->fieldValue('phone_no')));
            $status = trim((string) ($c->fieldValue('leads_status') ?: $c->status));

            // Feedback: strictly customer note only (no vehicle or status logs)
            $feedback = trim((string) $c->notes);
            if ($feedback === '') {
                $userNote = $c->activities
                    ->where('type', 'note')
                    ->first(function ($act) {
                        $b = trim((string) $act->body);
                        return $b !== '' && ! str_starts_with($b, 'Status updated to');
                    })?->body;
                $feedback = trim((string) ($userNote ?: ($c->fieldValue('notes') ?: ($c->fieldValue('note') ?: ($c->fieldValue('feedback') ?: '')))));
            }

            // Only show Amount Received (sell price) and Payment Mode when status is success / confirmed / converted
            $statusNorm = strtolower(trim(str_replace(['_', '-'], ' ', (string) $status)));
            $isSuccess = in_array($statusNorm, ['success', 'successful', 'confirmed', 'converted', 'won', 'completed', 'done', 'paid'], true);

            if ($isSuccess) {
                $amountReceived = (string) ($c->fieldValue('amount_received') ?: ($c->fieldValue('seal_price') ?: ($c->fieldValue('sell_price') ?: ($c->fieldValue('mrp') ?: ''))));
                $rawPaymentMode = trim((string) ($c->fieldValue('payment_mode') ?: ($c->fieldValue('payment_type') ?: '')));
                $paymentMode = $rawPaymentMode !== '' ? $rawPaymentMode : 'Online';
            } else {
                $amountReceived = '';
                $paymentMode = '';
            }

            $rows[] = [
                'date' => $date,
                'name' => $name,
                'city' => $city,
                'phone' => $phone,
                'status' => $status,
                'feedback' => $feedback,
                'amount_received' => $amountReceived,
                'payment_mode' => $paymentMode,
            ];
        }

        $recipient = trim((string) $request->input('recipient', ''));

        // 1. Build Rich HTML Table matching user's image styling (cyan background header, underlined, black borders)
        $htmlTable = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-family: Calibri, \'Segoe UI\', Arial, sans-serif; font-size: 13px; border: 1px solid #000000; width: 100%; min-width: 720px; background-color: #ffffff;">' . "\n";
        $htmlTable .= '  <thead>' . "\n";
        $htmlTable .= '    <tr style="background-color: #8fe3eb;">' . "\n";
        foreach ($headers as $h) {
            $htmlTable .= '      <th style="background-color: #8fe3eb; background: #8fe3eb; border: 1px solid #000000; padding: 7px 10px; text-decoration: underline; font-weight: bold; color: #000000; text-align: left; vertical-align: middle;">' . htmlspecialchars($h) . '</th>' . "\n";
        }
        $htmlTable .= '    </tr>' . "\n";
        $htmlTable .= '  </thead>' . "\n";
        $htmlTable .= '  <tbody>' . "\n";
        foreach ($rows as $r) {
            $htmlTable .= '    <tr style="background-color: #ffffff;">' . "\n";
            $htmlTable .= '      <td style="border: 1px solid #000000; padding: 6px 10px; color: #000000; white-space: nowrap;">' . htmlspecialchars($r['date']) . '</td>' . "\n";
            $htmlTable .= '      <td style="border: 1px solid #000000; padding: 6px 10px; font-weight: bold; color: #000000;">' . htmlspecialchars($r['name']) . '</td>' . "\n";
            $htmlTable .= '      <td style="border: 1px solid #000000; padding: 6px 10px; color: #000000;">' . htmlspecialchars($r['city']) . '</td>' . "\n";
            $htmlTable .= '      <td style="border: 1px solid #000000; padding: 6px 10px; color: #000000; white-space: nowrap;">' . htmlspecialchars($r['phone']) . '</td>' . "\n";
            $htmlTable .= '      <td style="border: 1px solid #000000; padding: 6px 10px; color: #000000;">' . htmlspecialchars($r['status']) . '</td>' . "\n";
            $htmlTable .= '      <td style="border: 1px solid #000000; padding: 6px 10px; color: #000000;">' . htmlspecialchars($r['feedback']) . '</td>' . "\n";
            $htmlTable .= '      <td style="border: 1px solid #000000; padding: 6px 10px; color: #000000; text-align: right;">' . htmlspecialchars($r['amount_received']) . '</td>' . "\n";
            $htmlTable .= '      <td style="border: 1px solid #000000; padding: 6px 10px; color: #000000;">' . htmlspecialchars($r['payment_mode']) . '</td>' . "\n";
            $htmlTable .= '    </tr>' . "\n";
        }
        $htmlTable .= '  </tbody>' . "\n";
        $htmlTable .= '</table>';

        // 2. Clean, beautifully structured lead summary for Gmail body
        $leadCards = [];
        $idx = 1;
        foreach ($rows as $r) {
            $lines = [];
            $lines[] = "[Lead {$idx}] " . ($r['name'] ?: 'Customer') . " (" . ($r['phone'] ?: 'No Phone') . ")";
            $lines[] = "  • Date: " . ($r['date'] ?: '-') . "  |  City: " . ($r['city'] ?: '-') . "  |  Status: " . ($r['status'] ?: '-');
            if ($r['feedback'] !== '') {
                $lines[] = "  • Feedback: " . $r['feedback'];
            }
            if ($r['amount_received'] !== '' || $r['payment_mode'] !== '') {
                $lines[] = "  • Amount: " . ($r['amount_received'] ?: '-') . "  |  Mode: " . ($r['payment_mode'] ?: '-');
            }
            $leadCards[] = implode("\n", $lines);
            $idx++;
        }

        $subject = "Customer Leads Report (" . count($rows) . " Records) - " . now()->format('d M Y');

        $fullBody = "Dear Team,\n\n"
            . "👉 [PASTE TABLE HERE: Press Ctrl + V to insert the colored Excel table]\n\n"
            . "--------------------------------------------------\n"
            . implode("\n--------------------------------------------------\n", $leadCards) . "\n"
            . "--------------------------------------------------\n\n"
            . "Regards,\nCRM";

        $gmailUrl = "https://mail.google.com/mail/?view=cm&fs=1"
            . ($recipient !== '' ? "&to=" . rawurlencode($recipient) : "&to=")
            . "&su=" . rawurlencode($subject)
            . "&body=" . rawurlencode($fullBody);

        return response()->json([
            'ok' => true,
            'count' => count($rows),
            'subject' => $subject,
            'recipient' => $recipient,
            'gmail_url' => $gmailUrl,
            'html_table' => $htmlTable,
            'rows' => $rows,
        ]);
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
