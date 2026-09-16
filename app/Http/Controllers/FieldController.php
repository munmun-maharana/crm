<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FieldController extends Controller
{
    public function index(): View
    {
        $fields = CrmField::ordered()->get();

        return view('fields.index', compact('fields'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', 'in:text,email,phone,textarea,select'],
            'options' => ['nullable', 'string'],
            'required' => ['nullable', 'boolean'],
            'in_form' => ['nullable', 'boolean'],
            'in_table' => ['nullable', 'boolean'],
            'in_excel' => ['nullable', 'boolean'],
        ]);

        $options = null;
        if ($data['type'] === 'select' && ! empty($data['options'])) {
            $options = collect(explode(',', $data['options']))
                ->map(fn ($o) => trim($o))
                ->filter()
                ->values()
                ->all();
        }

        $maxSort = (int) CrmField::max('sort_order');

        CrmField::create([
            'key' => CrmField::makeKey($data['label']),
            'label' => $data['label'],
            'type' => $data['type'],
            'options' => $options,
            'required' => $request->boolean('required'),
            'active' => true,
            'in_form' => $request->boolean('in_form', true),
            'in_table' => $request->boolean('in_table'),
            'in_excel' => $request->boolean('in_excel', true),
            'is_system' => false,
            'sort_order' => $maxSort + 1,
        ]);

        // Naya field: purane customers me value empty hi rahegi (meta me key nahi)
        return back()->with('success', 'Field added. Old customer data for this field is empty.');
    }

    public function update(Request $request, CrmField $field): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', 'in:text,email,phone,textarea,select'],
            'options' => ['nullable', 'string'],
            'required' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'in_form' => ['nullable', 'boolean'],
            'in_table' => ['nullable', 'boolean'],
            'in_excel' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $required = $request->boolean('required');
        $active = $request->boolean('active');
        $inForm = $request->boolean('in_form');
        $inTable = $request->boolean('in_table');
        $inExcel = $request->boolean('in_excel');

        // Name is always required + active + in form (disabled checkboxes don't POST)
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

        $field->update([
            'label' => $data['label'],
            'type' => $field->is_system ? $field->type : $data['type'],
            'options' => $options,
            'required' => $required,
            'active' => $active,
            'in_form' => $inForm,
            'in_table' => $inTable,
            'in_excel' => $inExcel,
            'sort_order' => $data['sort_order'] ?? $field->sort_order,
        ]);

        return back()->with('success', 'Field updated.');
    }

    public function destroy(CrmField $field): RedirectResponse
    {
        if ($field->is_system) {
            return back()->withErrors(['field' => 'System fields cannot be deleted.']);
        }

        $key = $field->key;
        $field->delete();

        // Data ho ya na ho — field delete; customer meta se key hata do
        Customer::query()->orderBy('id')->chunkById(100, function ($rows) use ($key) {
            foreach ($rows as $customer) {
                $meta = $customer->meta ?? [];
                if (! is_array($meta) || ! array_key_exists($key, $meta)) {
                    continue;
                }
                unset($meta[$key]);
                $customer->meta = $meta ?: null;
                $customer->save();
            }
        });

        return back()->with('success', 'Field deleted. Existing values cleared.');
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
