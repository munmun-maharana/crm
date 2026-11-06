<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FieldController extends Controller
{
    public function index(): View
    {
        $fields = CrmField::inPipeline('crm')->ordered()->get();

        return view('fields.index', compact('fields'));
    }

    public function store(Request $request): RedirectResponse
    {
        $pipeline = CrmField::normalizePipeline((string) $request->input('pipeline', 'crm'));
        $crmKeys = CrmField::inPipeline('crm')->pluck('key')->all();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', CrmField::typeRule()],
            'options' => ['nullable', 'string'],
            'required' => ['nullable', 'boolean'],
            'in_form' => ['nullable', 'boolean'],
            'in_table' => ['nullable', 'boolean'],
            'in_excel' => ['nullable', 'boolean'],
            'in_update' => ['nullable', 'boolean'],
            'maps_from' => ['nullable', 'string', 'max:80'],
        ]);

        $options = null;
        if ($data['type'] === 'select' && ! empty($data['options'])) {
            $options = collect(explode(',', $data['options']))
                ->map(fn ($o) => trim($o))
                ->filter()
                ->values()
                ->all();
        }

        $mapsFrom = $pipeline === 'operations' ? trim((string) ($data['maps_from'] ?? '')) : '';
        if ($mapsFrom !== '' && ! in_array($mapsFrom, $crmKeys, true)) {
            $mapsFrom = '';
        }

        $maxSort = (int) CrmField::inPipeline($pipeline)->max('sort_order');

        CrmField::create([
            'pipeline' => $pipeline,
            'key' => CrmField::makeKey($data['label'], $pipeline),
            'maps_from' => $mapsFrom !== '' ? $mapsFrom : null,
            'label' => $data['label'],
            'type' => $data['type'],
            'options' => $options,
            'required' => $request->boolean('required'),
            'active' => true,
            'in_form' => $request->boolean('in_form', true),
            'in_table' => $request->boolean('in_table'),
            'in_excel' => $pipeline === 'crm' ? $request->boolean('in_excel', true) : false,
            'in_update' => in_array($pipeline, ['crm', 'operations'], true) ? $request->boolean('in_update') : false,
            'is_system' => false,
            'sort_order' => $maxSort + 1,
        ]);

        if ($pipeline === 'operations') {
            return redirect()->route('settings.index', ['tab' => 'op-fields'])->with('success', 'Operation field added. Map it to a CRM field to copy data on Assign.');
        }
        if ($pipeline === 'users') {
            return redirect()->route('settings.index', ['tab' => 'user-fields'])->with('success', 'User field added.');
        }

        return redirect()->route('settings.index', ['tab' => 'fields'])->with('success', 'Field added.');
    }

    public function update(Request $request, CrmField $field): RedirectResponse
    {
        $crmKeys = CrmField::inPipeline('crm')->pluck('key')->all();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', CrmField::typeRule()],
            'options' => ['nullable', 'string'],
            'required' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'in_form' => ['nullable', 'boolean'],
            'in_table' => ['nullable', 'boolean'],
            'in_excel' => ['nullable', 'boolean'],
            'in_update' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'maps_from' => ['nullable', 'string', 'max:80'],
        ]);

        $required = $request->boolean('required');
        $active = $request->boolean('active');
        $inForm = $request->boolean('in_form');
        $inTable = $request->boolean('in_table');
        $inExcel = $request->boolean('in_excel');
        $inUpdate = $request->boolean('in_update');

        if ($field->key === 'name') {
            $required = true;
            $active = true;
            $inForm = true;
        }

        $options = $field->options;
        if (($field->is_system ? $field->type : $data['type']) === 'select' && array_key_exists('options', $data)) {
            $options = collect(explode(',', (string) ($data['options'] ?? '')))
                ->map(fn ($o) => trim($o))
                ->filter()
                ->values()
                ->all();
        }

        $mapsFrom = $field->maps_from;
        if ($field->pipeline === 'operations') {
            $mapsFrom = trim((string) ($data['maps_from'] ?? ''));
            if ($mapsFrom !== '' && ! in_array($mapsFrom, $crmKeys, true)) {
                $mapsFrom = '';
            }
            $mapsFrom = $mapsFrom !== '' ? $mapsFrom : null;
        }

        $field->update([
            'label' => $data['label'],
            'type' => $field->is_system ? $field->type : $data['type'],
            'options' => $options,
            'required' => $required,
            'active' => $active,
            'in_form' => $inForm,
            'in_table' => $field->pipeline === 'operations' ? $field->in_table : $inTable,
            'in_excel' => $field->pipeline === 'operations' ? false : $inExcel,
            'in_update' => in_array($field->pipeline, ['crm', 'operations'], true) ? $inUpdate : false,
            'sort_order' => $data['sort_order'] ?? $field->sort_order,
            'maps_from' => $mapsFrom,
        ]);

        if ($field->pipeline === 'operations') {
            return redirect()->route('settings.index', ['tab' => 'op-fields'])->with('success', 'Operation field updated.');
        }
        if ($field->pipeline === 'users') {
            return redirect()->route('settings.index', ['tab' => 'user-fields'])->with('success', 'User field updated.');
        }

        return redirect()->route('settings.index', ['tab' => 'fields'])->with('success', 'Field updated.');
    }

    public function destroy(Request $request, CrmField $field): RedirectResponse
    {
        if ($field->key === 'name') {
            return back()->withErrors(['field' => 'The Name field is required by the system and cannot be deleted.']);
        }

        if ($field->pipeline === 'users' && in_array($field->key, CrmField::USER_CORE_KEYS, true)) {
            return back()->withErrors(['field' => 'Core user fields cannot be deleted.']);
        }

        $key = $field->key;
        $pipeline = CrmField::normalizePipeline((string) $field->pipeline);
        $field->delete();

        if ($pipeline === 'crm') {
            Customer::query()->inPipeline('crm')->orderBy('id')->chunkById(100, function ($rows) use ($key) {
                foreach ($rows as $customer) {
                    $meta = $customer->meta ?? [];
                    if (is_array($meta) && array_key_exists($key, $meta)) {
                        unset($meta[$key]);
                        $customer->meta = $meta ?: null;
                    }
                    if (in_array($key, CrmField::CORE_KEYS, true) && $key !== 'name') {
                        if ($key === 'status') {
                            $customer->status = 'new';
                        } else {
                            $customer->{$key} = null;
                        }
                    }
                    $customer->save();
                }
            });
            CrmField::inPipeline('operations')->where('maps_from', $key)->update(['maps_from' => null]);
        }

        if ($pipeline === 'operations') {
            Customer::query()->inPipeline('operations')->orderBy('id')->chunkById(100, function ($rows) use ($key) {
                foreach ($rows as $customer) {
                    $meta = $customer->meta ?? [];
                    if (is_array($meta) && array_key_exists($key, $meta)) {
                        unset($meta[$key]);
                        $customer->meta = $meta ?: null;
                    }
                    if (in_array($key, CrmField::CORE_KEYS, true) && $key !== 'name') {
                        if ($key === 'status') {
                            $customer->status = 'new';
                        } else {
                            $customer->{$key} = null;
                        }
                    }
                    $customer->save();
                }
            });

            return redirect()->route('settings.index', ['tab' => 'op-fields'])->with('success', 'Operation field deleted.');
        }

        if ($pipeline === 'users') {
            User::query()->orderBy('id')->chunkById(100, function ($rows) use ($key) {
                foreach ($rows as $user) {
                    $meta = $user->meta ?? [];
                    if (! is_array($meta) || ! array_key_exists($key, $meta)) {
                        continue;
                    }
                    unset($meta[$key]);
                    $user->meta = $meta ?: null;
                    $user->save();
                }
            });

            return redirect()->route('settings.index', ['tab' => 'user-fields'])->with('success', 'User field deleted.');
        }

        return redirect()->route('settings.index', ['tab' => 'fields'])->with('success', 'Field deleted. Existing values cleared.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:crm_fields,id'],
        ]);

        foreach ($data['order'] as $index => $id) {
            CrmField::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
