<?php

namespace App\Http\Controllers;

use App\Models\InventoryType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $max = (int) InventoryType::max('sort_order');

        InventoryType::create([
            'name' => trim($data['name']),
            'key' => InventoryType::makeKey($data['name']),
            'sort_order' => $max + 1,
            'is_active' => true,
            'is_system' => false,
        ]);

        return redirect()
            ->route('settings.index', ['tab' => 'inventory'])
            ->with('success', 'Inventory type added.');
    }

    public function update(Request $request, InventoryType $inventoryType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $inventoryType->update([
            'name' => trim($data['name']),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? $inventoryType->sort_order,
        ]);

        return redirect()
            ->route('settings.index', ['tab' => 'inventory'])
            ->with('success', 'Inventory type updated.');
    }

    public function destroy(InventoryType $inventoryType): RedirectResponse
    {
        if ($inventoryType->is_system) {
            return back()->withErrors(['type' => 'Default inventory types cannot be deleted. Deactivate instead.']);
        }

        $inventoryType->delete();

        return redirect()
            ->route('settings.index', ['tab' => 'inventory'])
            ->with('success', 'Inventory type deleted.');
    }
}
