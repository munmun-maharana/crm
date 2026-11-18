<?php

namespace App\Http\Controllers;

use App\Models\MenuSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MenuSettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $menus = MenuSetting::ordered()->get();

        return response()->json([
            'ok' => true,
            'menus' => $menus,
            'map' => MenuSetting::allMap(),
        ]);
    }

    public function update(Request $request, MenuSetting $menuSetting): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $menuSetting->update([
            'label' => trim($data['label']),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $menuSetting->is_active,
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => true,
                'message' => "Menu \"{$menuSetting->key}\" updated successfully.",
                'menu' => $menuSetting,
                'menus' => MenuSetting::ordered()->get(),
                'map' => MenuSetting::allMap(),
            ]);
        }

        return redirect()->route('settings.index', ['tab' => 'sidebar-menus'])
            ->with('success', "Menu name updated to \"{$menuSetting->label}\".");
    }

    public function updateAll(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $menusData = $request->input('menus', []);
        if (is_array($menusData)) {
            foreach ($menusData as $id => $payload) {
                if (is_array($payload) && isset($payload['label'])) {
                    $item = MenuSetting::find($id);
                    if ($item && trim((string) $payload['label']) !== '') {
                        $item->update([
                            'label' => trim((string) $payload['label']),
                            'is_active' => isset($payload['is_active']) ? (bool) $payload['is_active'] : $item->is_active,
                        ]);
                    }
                }
            }
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => true,
                'message' => 'Sidebar menu names updated successfully.',
                'menus' => MenuSetting::ordered()->get(),
                'map' => MenuSetting::allMap(),
            ]);
        }

        return redirect()->route('settings.index', ['tab' => 'sidebar-menus'])
            ->with('success', 'All sidebar menu names updated successfully.');
    }

    public function reset(Request $request, MenuSetting $menuSetting): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $menuSetting->update([
            'label' => $menuSetting->default_label,
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => true,
                'message' => "Menu reset to default \"{$menuSetting->default_label}\".",
                'menu' => $menuSetting,
                'menus' => MenuSetting::ordered()->get(),
                'map' => MenuSetting::allMap(),
            ]);
        }

        return redirect()->route('settings.index', ['tab' => 'sidebar-menus'])
            ->with('success', "Menu \"{$menuSetting->key}\" reset to default name \"{$menuSetting->default_label}\".");
    }

    public function reorder(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $order = $request->input('order', []);
        if (is_array($order)) {
            foreach ($order as $index => $id) {
                MenuSetting::where('id', (int) $id)->update(['sort_order' => $index + 1]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'Sidebar menu order updated successfully.',
            'menus' => MenuSetting::ordered()->get(),
            'map' => MenuSetting::allMap(),
        ]);
    }
}
