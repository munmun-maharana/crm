<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\OperationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationSettingController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString() === 'fields' ? 'fields' : 'assign';
        $setting = OperationSetting::current();
        $statuses = $this->statusOptions();
        $fields = CrmField::inPipeline('operations')->ordered()->get();
        $crmFields = CrmField::inPipeline('crm')->ordered()->get(['id', 'key', 'label']);

        return view('operation-settings.index', compact('tab', 'setting', 'statuses', 'fields', 'crmFields'));
    }

    public function update(Request $request): RedirectResponse
    {
        $statuses = $this->statusOptions();
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', $statuses)],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string'],
        ]);

        $setting = OperationSetting::current();
        $setting->update($data);

        return back()->with('success', 'Operation Team setting saved. Assign button will use this status, title and note.');
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
