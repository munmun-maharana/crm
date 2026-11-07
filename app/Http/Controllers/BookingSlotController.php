<?php

namespace App\Http\Controllers;

use App\Models\BookingSlot;
use App\Models\BookingSlotSetting;
use App\Models\CrmField;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\NoteTemplate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingSlotController extends Controller
{
    /**
     * Return slots for a specific date (defaults to today) as JSON for the slider.
     */
    public function index(Request $request): JsonResponse
    {
        $dateStr = $request->string('date')->toString();
        try {
            $date = $dateStr !== '' ? Carbon::parse($dateStr)->startOfDay() : today();
        } catch (\Throwable) {
            $date = today();
        }

        $slots = BookingSlot::query()
            ->with('customer')
            ->whereDate('slot_date', $date->toDateString())
            ->orderBy('start_time')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $counts = [
            'total' => $slots->count(),
            'available' => $slots->where('status', BookingSlot::STATUS_AVAILABLE)->count(),
            'booked' => $slots->where('status', BookingSlot::STATUS_BOOKED)->count(),
            'success' => $slots->where('status', BookingSlot::STATUS_SUCCESS)->count(),
            'cancelled' => $slots->where('status', BookingSlot::STATUS_CANCELLED)->count(),
        ];

        return response()->json([
            'ok' => true,
            'date' => $date->toDateString(),
            'date_formatted' => $date->format('d M Y (D)'),
            'is_today' => $date->isToday(),
            'counts' => $counts,
            'slots' => $slots->map(fn (BookingSlot $s) => $this->serializeSlot($s))->values(),
        ]);
    }

    /**
     * Book an available slot for a customer (prevents double-booking with row lock).
     */
    public function book(Request $request, BookingSlot $bookingSlot): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $customerName = trim($data['customer_name']);
        if ($customerName === '') {
            return response()->json(['ok' => false, 'message' => 'Customer name is required.'], 422);
        }

        $result = DB::transaction(function () use ($bookingSlot, $customerName, $data, $request) {
            /** @var BookingSlot $locked */
            $locked = BookingSlot::query()->lockForUpdate()->findOrFail($bookingSlot->id);

            if (in_array($locked->status, [BookingSlot::STATUS_BOOKED, BookingSlot::STATUS_SUCCESS], true)) {
                return [
                    'ok' => false,
                    'status_code' => 409,
                    'message' => 'This slot is already booked for ' . ($locked->customer_name ?: 'another customer') . ' and cannot be booked again.',
                ];
            }

            if ($locked->status === BookingSlot::STATUS_CANCELLED) {
                return [
                    'ok' => false,
                    'status_code' => 422,
                    'message' => 'This slot is cancelled and not available for booking.',
                ];
            }

            $customerId = $data['customer_id'] ?? null;
            $matchedCustomer = null;
            if ($customerId) {
                $matchedCustomer = Customer::query()->find($customerId);
                if ($matchedCustomer && strtolower(trim($matchedCustomer->name)) !== strtolower($customerName)) {
                    $byName = Customer::query()
                        ->where('pipeline', 'crm')
                        ->whereRaw('LOWER(name) = ?', [strtolower($customerName)])
                        ->latest('id')
                        ->first()
                        ?? Customer::query()
                            ->whereRaw('LOWER(name) = ?', [strtolower($customerName)])
                            ->latest('id')
                            ->first();
                    if ($byName) {
                        $matchedCustomer = $byName;
                        $customerId = $byName->id;
                    }
                }
            } else {
                $matchedCustomer = Customer::query()
                    ->where('pipeline', 'crm')
                    ->whereRaw('LOWER(name) = ?', [strtolower($customerName)])
                    ->latest('id')
                    ->first()
                    ?? Customer::query()
                        ->whereRaw('LOWER(name) = ?', [strtolower($customerName)])
                        ->latest('id')
                        ->first();
                $customerId = $matchedCustomer?->id;
            }

            if ($matchedCustomer && $matchedCustomer->isOperations() && $matchedCustomer->source_customer_id) {
                $crmOriginal = Customer::query()->find($matchedCustomer->source_customer_id);
                if ($crmOriginal) {
                    $matchedCustomer = $crmOriginal;
                    $customerId = $crmOriginal->id;
                }
            }

            $locked->update([
                'status' => BookingSlot::STATUS_BOOKED,
                'customer_name' => $customerName,
                'customer_id' => $customerId,
                'booked_by' => $request->user()->id,
                'booked_at' => now(),
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : $locked->notes,
            ]);

            $customerStatusPayload = null;
            if ($matchedCustomer) {
                $slotSetting = BookingSlotSetting::current();
                $oldStatus = $matchedCustomer->status;
                $newStatus = trim((string) $slotSetting->status) ?: $oldStatus;
                $statusChanged = strtolower((string) $newStatus) !== strtolower((string) $oldStatus);

                $slotDateFormatted = $locked->slot_date->format('d M Y');
                $slotTimeLabel = $locked->label ?: BookingSlot::formatTimeLabel($locked->start_time, $locked->end_time);

                $custMeta = is_array($matchedCustomer->meta) ? $matchedCustomer->meta : [];
                $custMeta['booking_slot_time'] = $slotTimeLabel;
                $custMeta['booking_time'] = $slotTimeLabel;

                $bookingDateTime = $locked->slot_date->toDateString() . ' ' . substr($locked->start_time, 0, 5) . ':00';

                $matchedCustomer->update([
                    'status' => $newStatus,
                    'follow_up' => false,
                    'follow_up_at' => $bookingDateTime,
                    'follow_up_has_time' => true,
                    'meta' => $custMeta,
                ]);

                // Automatically assign to Operation Team when booking a slot
                if (! $matchedCustomer->isOperations()) {
                    $opCopy = Customer::where('source_customer_id', $matchedCustomer->id)->first();
                    $opAttrs = $matchedCustomer->mappedOperationAttributes();
                    $opMeta = is_array($opAttrs['meta'] ?? null) ? $opAttrs['meta'] : [];
                    $opMeta['booking_slot_time'] = $slotTimeLabel;
                    $opMeta['booking_time'] = $slotTimeLabel;
                    $opAttrs['meta'] = $opMeta;
                    $opAttrs['follow_up'] = false;
                    $opAttrs['follow_up_at'] = $bookingDateTime;
                    $opAttrs['follow_up_has_time'] = true;

                    if (! $opCopy) {
                        $opAttrs['status'] = 'Pending';
                        $opCopy = Customer::create($opAttrs);

                        CustomerActivity::create([
                            'customer_id' => $opCopy->id,
                            'user_id' => $request->user()->id,
                            'type' => 'system',
                            'title' => 'Received from CRM (Booking Slot)',
                            'body' => "Auto-assigned to Operation Team from CRM #{$matchedCustomer->id} for slot {$slotDateFormatted} ({$slotTimeLabel}).",
                        ]);
                    } else {
                        $existingOpMeta = is_array($opCopy->meta) ? $opCopy->meta : [];
                        $opCopy->update([
                            'follow_up' => false,
                            'follow_up_at' => $bookingDateTime,
                            'follow_up_has_time' => true,
                            'meta' => array_merge($existingOpMeta, $opMeta),
                        ]);
                    }
                }

                $rawTitle = trim((string) $slotSetting->title) ?: 'Booking Slot Confirmed';
                $rawBody = trim((string) $slotSetting->body) ?: 'Booking slot confirmed for {date} at {time}.';

                $replacements = [
                    '{date}' => $slotDateFormatted,
                    '{time}' => $slotTimeLabel,
                    '{customer}' => $matchedCustomer->name,
                ];
                $actTitle = strtr($rawTitle, $replacements);
                $actBody = strtr($rawBody, $replacements);

                // Ensure date & time are always visible in the activity message even if admin omitted placeholders
                if (! str_contains($actBody, $slotDateFormatted) && ! str_contains($actBody, $locked->slot_date->toDateString())) {
                    $actBody .= "\nDate: {$slotDateFormatted} | Time: {$slotTimeLabel}";
                }

                $actMeta = [
                    'slot_id' => $locked->id,
                    'slot_date' => $locked->slot_date->toDateString(),
                    'slot_time' => $slotTimeLabel,
                ];
                if ($statusChanged) {
                    $actMeta['status_from'] = $oldStatus;
                    $actMeta['status_to'] = $newStatus;
                }

                CustomerActivity::create([
                    'customer_id' => $matchedCustomer->id,
                    'user_id' => $request->user()->id,
                    'type' => $statusChanged ? 'status' : 'note',
                    'title' => $actTitle,
                    'body' => $actBody,
                    'meta' => $actMeta,
                ]);

                $matchedCustomer->refresh();
                $customerStatusPayload = [
                    'customer_id' => $matchedCustomer->id,
                    'status' => $matchedCustomer->status,
                    'follow_up_html' => $matchedCustomer->actionDateCellHtml(),
                    'list_date_html' => $matchedCustomer->listDateSummaryHtml(),
                ];
            }

            return [
                'ok' => true,
                'status_code' => 200,
                'slot' => $this->serializeSlot($locked->fresh()),
                'customer_update' => $customerStatusPayload,
            ];
        });

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'],
            ], $result['status_code']);
        }

        $response = [
            'ok' => true,
            'message' => 'Slot booked successfully for ' . $customerName . '.',
            'slot' => $result['slot'],
        ];

        if (! empty($result['customer_update']['customer_id'])) {
            $cid = $result['customer_update']['customer_id'];
            $newStatus = $result['customer_update']['status'];
            $toneMap = [
                'new' => 'text-sky-700 bg-sky-50',
                'no answer' => 'text-slate-700 bg-slate-100',
                'follow-up' => 'text-amber-800 bg-amber-100',
                'interested' => 'text-teal-700 bg-teal-50',
                'contacted' => 'text-blue-700 bg-blue-50',
                'converted' => 'text-emerald-700 bg-emerald-50',
                'cancelled' => 'text-rose-700 bg-rose-50',
                'lost' => 'text-rose-700 bg-rose-50',
                'confirmed' => 'text-emerald-700 bg-emerald-50',
                'request' => 'text-amber-800 bg-amber-50',
            ];
            $response['customer_id'] = $cid;
            $response['customer_status'] = $newStatus;
            $response['status_tone_class'] = $toneMap[strtolower((string) $newStatus)] ?? 'bg-sky-50 text-sky-800';
            $response['follow_up_html'] = $result['customer_update']['follow_up_html'] ?? null;
            $response['list_date_html'] = $result['customer_update']['list_date_html'] ?? null;
        }

        return response()->json($response);
    }

    /**
     * Admin: Update auto status & message setting when a booking slot is booked.
     */
    public function updateSetting(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $setting = BookingSlotSetting::current();
        $setting->update([
            'status' => trim($data['status']),
            'title' => trim($data['title']),
            'body' => trim($data['body']),
        ]);

        return redirect()->route('settings.index', ['tab' => 'booking-slots'])
            ->with('success', 'Booking slot auto-status & message setting saved.');
    }

    /**
     * Admin: Create single or multiple time slots for a single date or date range.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['nullable', 'in:single,range'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
            'slots.*.end_time' => ['required', 'date_format:H:i'],
        ]);

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = (! empty($data['end_date']) && ($data['mode'] ?? 'single') === 'range')
            ? Carbon::parse($data['end_date'])->startOfDay()
            : $startDate->copy();

        if ($startDate->diffInDays($endDate) > 90) {
            return back()->withErrors(['end_date' => 'Date range cannot exceed 90 days at once.']);
        }

        $validTimeRanges = [];
        foreach ($data['slots'] as $idx => $slotInput) {
            $st = substr(trim($slotInput['start_time']), 0, 5);
            $et = substr(trim($slotInput['end_time']), 0, 5);
            if ($et <= $st) {
                return back()->withErrors(["slots.{$idx}.end_time" => "End time must be after start time ({$st} - {$et})."]);
            }
            $validTimeRanges[] = [
                'start_time' => $st,
                'end_time' => $et,
                'label' => BookingSlot::formatTimeLabel($st, $et),
            ];
        }

        $createdCount = 0;
        $skippedCount = 0;

        $period = CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $dateObj) {
            $dateStr = $dateObj->toDateString();
            foreach ($validTimeRanges as $orderIdx => $tr) {
                $exists = BookingSlot::query()
                    ->whereDate('slot_date', $dateStr)
                    ->where('start_time', $tr['start_time'])
                    ->where('end_time', $tr['end_time'])
                    ->exists();

                if ($exists) {
                    $skippedCount++;
                    continue;
                }

                BookingSlot::create([
                    'slot_date' => $dateStr,
                    'start_time' => $tr['start_time'],
                    'end_time' => $tr['end_time'],
                    'label' => $tr['label'],
                    'status' => BookingSlot::STATUS_AVAILABLE,
                    'sort_order' => $orderIdx + 1,
                ]);
                $createdCount++;
            }
        }

        $msg = "Created {$createdCount} booking slot" . ($createdCount === 1 ? '' : 's') . ".";
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} duplicate slot" . ($skippedCount === 1 ? '' : 's') . " skipped)";
        }

        return redirect()->route('settings.index', [
            'tab' => 'booking-slots',
            'slot_date' => $startDate->toDateString(),
        ])->with('success', $msg);
    }

    /**
     * Admin: Update a booking slot (time, status: available/booked/cancelled, customer_name).
     */
    public function update(Request $request, BookingSlot $bookingSlot): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'slot_date' => ['nullable', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'status' => ['required', 'in:available,booked,success,cancelled'],
            'customer_name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $st = substr(trim($data['start_time']), 0, 5);
        $et = substr(trim($data['end_time']), 0, 5);

        if ($et <= $st) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'End time must be after start time.'], 422);
            }

            return back()->withErrors(['end_time' => 'End time must be after start time.']);
        }

        $targetDate = ! empty($data['slot_date'])
            ? Carbon::parse($data['slot_date'])->toDateString()
            : $bookingSlot->slot_date->toDateString();

        $duplicate = BookingSlot::query()
            ->whereDate('slot_date', $targetDate)
            ->where('start_time', $st)
            ->where('end_time', $et)
            ->where('id', '!=', $bookingSlot->id)
            ->exists();

        if ($duplicate) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'Another slot with this exact time already exists on this date.'], 422);
            }

            return back()->withErrors(['start_time' => 'Another slot with this exact time already exists on this date.']);
        }

        $status = $data['status'];
        $customerName = trim((string) ($data['customer_name'] ?? ''));

        if (in_array($status, [BookingSlot::STATUS_BOOKED, BookingSlot::STATUS_SUCCESS], true) && $customerName === '') {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'Customer name is required when status is Booked or Success.'], 422);
            }

            return back()->withErrors(['customer_name' => 'Customer name is required when status is Booked or Success.']);
        }

        if ($status === BookingSlot::STATUS_AVAILABLE) {
            $customerName = '';
        }

        $isOccupied = in_array($status, [BookingSlot::STATUS_BOOKED, BookingSlot::STATUS_SUCCESS], true);

        $bookingSlot->update([
            'slot_date' => $targetDate,
            'start_time' => $st,
            'end_time' => $et,
            'label' => BookingSlot::formatTimeLabel($st, $et),
            'status' => $status,
            'customer_name' => $customerName !== '' ? $customerName : null,
            'customer_id' => $status === BookingSlot::STATUS_AVAILABLE ? null : $bookingSlot->customer_id,
            'booked_by' => $isOccupied ? ($bookingSlot->booked_by ?: $request->user()->id) : null,
            'booked_at' => $isOccupied ? ($bookingSlot->booked_at ?: now()) : null,
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) : $bookingSlot->notes,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Booking slot updated.',
                'slot' => $this->serializeSlot($bookingSlot->fresh()),
            ]);
        }

        return redirect()->route('settings.index', [
            'tab' => 'booking-slots',
            'slot_date' => $targetDate,
        ])->with('success', 'Booking slot updated.');
    }

    /**
     * Admin: Delete a single booking slot.
     */
    public function destroy(Request $request, BookingSlot $bookingSlot): RedirectResponse|JsonResponse
    {
        $dateStr = $bookingSlot->slot_date->toDateString();
        $bookingSlot->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => 'Booking slot deleted.']);
        }

        return redirect()->route('settings.index', [
            'tab' => 'booking-slots',
            'slot_date' => $dateStr,
        ])->with('success', 'Booking slot deleted.');
    }

    /**
     * Admin: Clear all slots on a given date.
     */
    public function destroyDate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slot_date' => ['required', 'date'],
        ]);

        $dateStr = Carbon::parse($data['slot_date'])->toDateString();
        $deleted = BookingSlot::query()->whereDate('slot_date', $dateStr)->delete();

        return redirect()->route('settings.index', [
            'tab' => 'booking-slots',
            'slot_date' => $dateStr,
        ])->with('success', "Deleted {$deleted} slot(s) for {$dateStr}.");
    }

    private function serializeSlot(BookingSlot $slot): array
    {
        $c = $slot->relationLoaded('customer') ? $slot->customer : $slot->customer()->first();
        if (! $c && ! empty($slot->customer_name)) {
            $c = Customer::query()
                ->whereRaw('LOWER(name) = ?', [strtolower(trim($slot->customer_name))])
                ->latest('id')
                ->first();
        }

        $meta = is_array($c?->meta) ? $c->meta : [];
        $pickMeta = function (array $keys) use ($meta, $c): ?string {
            foreach ($keys as $k) {
                $val = $meta[$k] ?? ($c ? $c->fieldValue($k) : null);
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }
                if ($val !== null && trim((string) $val) !== '') {
                    return trim((string) $val);
                }
            }
            return null;
        };

        $customerPhone = $c?->phone ?: $pickMeta(['phone_no', 'phone', 'mobile', 'contact_no']);
        $carModel = $pickMeta(['model', 'car_model', 'vehicle_model', 'vehicle_type']);
        $serviceType = $pickMeta(['service_details', 'service_type', 'service', 'package']);
        $price = $pickMeta(['seal_price', 'sale_price', 'selling_price', 'price', 'amount', 'mrp']);

        return [
            'id' => $slot->id,
            'slot_date' => $slot->slot_date->toDateString(),
            'date_formatted' => $slot->slot_date->format('d M Y'),
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'label' => $slot->label ?: BookingSlot::formatTimeLabel($slot->start_time, $slot->end_time),
            'status' => $slot->status,
            'status_label' => BookingSlot::STATUSES[$slot->status] ?? ucfirst($slot->status),
            'customer_name' => $slot->customer_name,
            'customer_id' => $slot->customer_id ?: $c?->id,
            'customer_phone' => $customerPhone,
            'car_model' => $carModel,
            'service_type' => $serviceType,
            'price' => $price,
            'notes' => $slot->notes,
            'booked_at' => $slot->booked_at?->format('d M Y, h:i A'),
        ];
    }
}

