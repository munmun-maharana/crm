<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsPayload;
use App\Http\Controllers\Controller;
use App\Models\BookingSlot;
use App\Models\BookingSlotSetting;
use App\Models\CrmField;
use App\Models\NoteTemplate;
use App\Models\OperationSetting;
use App\Models\User;
use App\Models\WhatsappSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admin read endpoints (mirrors settings.index tabs + users.index). */
class SettingsController extends Controller
{
    use FormatsPayload;

    /** GET /settings — every settings tab in one payload */
    public function index(Request $request): JsonResponse
    {
        $crmFields = CrmField::inPipeline('crm')->ordered()->get();

        $opStatuses = collect(CrmField::inPipeline('crm')->where('key', 'status')->first()?->options ?? [])
            ->map(fn ($o) => strtolower(trim((string) $o)))->filter()->values()->all();
        $opStatuses = array_values(array_unique(array_merge(['new', 'interested', 'contacted', 'converted', 'lost', 'request'], $opStatuses)));

        return response()->json([
            'ok' => true,
            'field_types' => CrmField::TYPES,
            'fields' => [
                'crm' => $this->fieldsPayload($crmFields),
                'operations' => $this->fieldsPayload(CrmField::inPipeline('operations')->ordered()->get()),
                'users' => $this->fieldsPayload(CrmField::inPipeline('users')->ordered()->get()),
            ],
            'crm_field_keys' => $crmFields->map(fn ($f) => ['key' => $f->key, 'label' => $f->label])->values(),
            'note_templates' => NoteTemplate::ordered()->get(['id', 'title', 'body', 'sort_order', 'is_active']),
            'operation_setting' => OperationSetting::current()->only(['status', 'title', 'body']),
            'operation_statuses' => $opStatuses,
            'whatsapp_setting' => WhatsappSetting::current()->only(['phone', 'message_template']),
            'booking_slot_setting' => BookingSlotSetting::current()->only(['status', 'title', 'body']),
        ]);
    }

    /** GET /settings/booking-slots?slot_date= — admin slot manager for one date */
    public function bookingSlots(Request $request): JsonResponse
    {
        $str = $request->string('slot_date')->toString();
        try {
            $slotDate = $str !== '' ? Carbon::parse($str)->toDateString() : today()->toDateString();
        } catch (\Throwable) {
            $slotDate = today()->toDateString();
        }

        $slots = BookingSlot::query()->whereDate('slot_date', $slotDate)
            ->orderBy('start_time')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (BookingSlot $s) => [
                'id' => $s->id,
                'slot_date' => $s->slot_date?->toDateString(),
                'start_time' => substr((string) $s->start_time, 0, 5),
                'end_time' => substr((string) $s->end_time, 0, 5),
                'label' => $s->label ?: BookingSlot::formatTimeLabel((string) $s->start_time, (string) $s->end_time),
                'status' => $s->status,
                'status_label' => BookingSlot::STATUSES[$s->status] ?? ucfirst((string) $s->status),
                'customer_name' => $s->customer_name,
                'customer_id' => $s->customer_id,
                'notes' => $s->notes,
            ])->values();

        $dates = BookingSlot::query()
            ->selectRaw('slot_date, COUNT(*) as total_slots, SUM(CASE WHEN status = "available" THEN 1 ELSE 0 END) as available_slots, SUM(CASE WHEN status = "booked" THEN 1 ELSE 0 END) as booked_slots')
            ->whereDate('slot_date', '>=', today()->subDays(7)->toDateString())
            ->groupBy('slot_date')->orderBy('slot_date')->limit(30)->get()
            ->map(fn ($r) => [
                'date' => Carbon::parse($r->slot_date)->toDateString(),
                'label' => Carbon::parse($r->slot_date)->format('d M (D)'),
                'total' => (int) $r->total_slots,
                'available' => (int) $r->available_slots,
                'booked' => (int) $r->booked_slots,
            ])->values();

        return response()->json([
            'ok' => true,
            'slot_date' => $slotDate,
            'slot_date_label' => Carbon::parse($slotDate)->format('d M Y (D)'),
            'statuses' => BookingSlot::STATUSES,
            'slots' => $slots,
            'configured_dates' => $dates,
        ]);
    }

    /** GET /users — users.index */
    public function users(Request $request): JsonResponse
    {
        $page = User::latest()->paginate(min(100, max(10, (int) $request->integer('per_page', 50))));
        $fields = CrmField::inPipeline('users')->active()->ordered()->get();

        return response()->json([
            'ok' => true,
            'role_options' => User::roleOptions(),
            'form_fields' => $this->fieldsPayload($fields->where('in_form', true)->values()),
            'table_fields' => $this->fieldsPayload($fields->where('in_table', true)->values()),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'has_more' => $page->hasMorePages(),
            ],
            'users' => collect($page->items())->map(function (User $u) use ($fields) {
                $values = [];
                foreach ($fields as $f) {
                    if ($f->key === 'password') {
                        continue;
                    }
                    $values[$f->key] = $u->fieldValue($f->key);
                }

                return [
                    ...$this->userPayload($u),
                    'values' => (object) $values,
                    'access_crm' => $u->appAccessCrm(),
                    'access_operations' => $u->appAccessOperations(),
                    'access_inventory' => $u->appAccessInventory(),
                    'is_self' => (int) $u->id === (int) request()->user()->id,
                    'created_at' => $u->created_at?->format('d M Y'),
                ];
            })->values(),
        ]);
    }
}

