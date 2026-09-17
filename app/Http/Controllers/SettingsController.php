<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\NoteTemplate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString() === 'notes' ? 'notes' : 'fields';
        $fields = CrmField::inPipeline('crm')->ordered()->get();
        $templates = NoteTemplate::ordered()->get();

        return view('settings.index', compact('tab', 'fields', 'templates'));
    }
}
