<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WhatsappSettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'message_template' => ['nullable', 'string', 'max:2000'],
        ]);

        $setting = WhatsappSetting::current();
        $setting->update($data);

        return redirect()->route('settings.index', ['tab' => 'whatsapp'])
            ->with('success', 'WhatsApp settings saved successfully.');
    }
}
