<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\InventoryType;
use App\Models\NoteTemplate;
use App\Models\OperationSetting;
use App\Models\WhatsappSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $allowed = ['fields', 'notes', 'whatsapp', 'op-assign', 'op-fields', 'user-fields', 'inventory', 'security'];
        $tab = $request->string('tab')->toString();
        if (! in_array($tab, $allowed, true)) {
            $tab = 'fields';
        }

        $fields = CrmField::inPipeline('crm')->ordered()->get();
        $templates = NoteTemplate::ordered()->get();
        $setting = OperationSetting::current();
        $whatsappSetting = WhatsappSetting::current();
        $statuses = $this->statusOptions();
        $opFields = CrmField::inPipeline('operations')->ordered()->get();
        $userFields = CrmField::inPipeline('users')->ordered()->get();
        $inventoryTypes = InventoryType::ordered()->get();
        $crmFields = $fields->map(fn ($f) => (object) ['id' => $f->id, 'key' => $f->key, 'label' => $f->label]);

        return view('settings.index', compact(
            'tab',
            'fields',
            'templates',
            'setting',
            'whatsappSetting',
            'statuses',
            'opFields',
            'userFields',
            'inventoryTypes',
            'crmFields'
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
