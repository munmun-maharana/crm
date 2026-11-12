<?php

namespace App\Http\Controllers;

use App\Models\BookingSlot;
use App\Models\BookingSlotSetting;
use App\Models\CrmField;
use App\Models\InventoryType;
use App\Models\NoteTemplate;
use App\Models\OperationSetting;
use App\Models\WhatsappSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $allowed = ['fields', 'call-crm-fields', 'notes', 'whatsapp', 'booking-slots', 'op-assign', 'op-fields', 'user-fields', 'pincode-fields', 'security'];
        $tab = $request->string('tab')->toString();
        if (! in_array($tab, $allowed, true)) {
            $tab = 'fields';
        }

        $slotDateStr = $request->string('slot_date')->toString();
        try {
            $slotDate = $slotDateStr !== '' ? Carbon::parse($slotDateStr)->toDateString() : today()->toDateString();
        } catch (\Throwable) {
            $slotDate = today()->toDateString();
        }

        $bookingSlots = BookingSlot::query()
            ->whereDate('slot_date', $slotDate)
            ->orderBy('start_time')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $configuredSlotDates = BookingSlot::query()
            ->selectRaw('slot_date, COUNT(*) as total_slots, SUM(CASE WHEN status = "available" THEN 1 ELSE 0 END) as available_slots, SUM(CASE WHEN status = "booked" THEN 1 ELSE 0 END) as booked_slots')
            ->whereDate('slot_date', '>=', today()->subDays(7)->toDateString())
            ->groupBy('slot_date')
            ->orderBy('slot_date')
            ->limit(30)
            ->get();

        $fields = CrmField::inPipeline('crm')->ordered()->get();
        $callCrmFields = CrmField::inPipeline('call_crm')->ordered()->get();
        $templates = NoteTemplate::ordered()->get();
        $setting = OperationSetting::current();
        $bookingSlotSetting = BookingSlotSetting::current();
        $whatsappSetting = WhatsappSetting::current();
        $statuses = $this->statusOptions();
        $opFields = CrmField::inPipeline('operations')->ordered()->get();
        $userFields = CrmField::inPipeline('users')->ordered()->get();
        $pincodeFields = CrmField::inPipeline('pincodes')->ordered()->get();
        $inventoryTypes = InventoryType::ordered()->get();
        $crmFields = $fields->map(fn ($f) => (object) ['id' => $f->id, 'key' => $f->key, 'label' => $f->label]);

        return view('settings.index', compact(
            'tab',
            'fields',
            'callCrmFields',
            'templates',
            'setting',
            'bookingSlotSetting',
            'whatsappSetting',
            'statuses',
            'opFields',
            'userFields',
            'pincodeFields',
            'inventoryTypes',
            'crmFields',
            'slotDate',
            'bookingSlots',
            'configuredSlotDates'
        ));
    }

    private function statusOptions(): array
    {
        $field = CrmField::inPipeline('crm')->where('key', 'status')->first();
        $fromField = collect($field?->options ?? [])
            ->map(fn ($o) => strtolower(trim((string) $o)))
            ->filter()
            ->values()
            ->all();

        $defaults = ['new', 'interested', 'contacted', 'converted', 'lost', 'request'];

        return array_values(array_unique(array_merge($defaults, $fromField)));
    }
}
