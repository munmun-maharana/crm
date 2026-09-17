<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\OperationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationSettingController extends Controller
{
    public function index(): View
    {
        $setting = OperationSetting::current();
        $statuses = $this->statusOptions();

        return view('operation-settings.index', compact('setting', 'statuses'));
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
        $field = CrmField::query()->where('key', 'status')->first();
        $fromField = collect($field?->options ?? [])
            ->map(fn ($o) => strtolower(trim((string) $o)))
            ->filter()
            ->values()
            ->all();

        $defaults = ['new', 'interested', 'contacted', 'converted', 'lost', 'request'];

        return array_values(array_unique(array_merge($defaults, $fromField)));
    }
}
