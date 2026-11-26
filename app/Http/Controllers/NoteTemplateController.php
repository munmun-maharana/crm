<?php

namespace App\Http\Controllers;

use App\Models\NoteTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoteTemplateController extends Controller
{
    public function index(): View
    {
        $templates = NoteTemplate::ordered()->get();

        return view('note-templates.index', compact('templates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string'],
            'status' => ['nullable', 'string', 'max:60'],
        ]);

        $max = (int) NoteTemplate::max('sort_order');

        $template = NoteTemplate::create([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'status' => !empty($data['status']) ? trim($data['status']) : null,
            'sort_order' => $max + 1,
            'is_active' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Note template added.', 'template' => $template]);
        }

        return back()->with('success', 'Note template added.');
    }

    public function update(Request $request, NoteTemplate $noteTemplate): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string'],
            'status' => ['nullable', 'string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $noteTemplate->update([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'status' => !empty($data['status']) ? trim($data['status']) : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Template updated.', 'template' => $noteTemplate]);
        }

        return back()->with('success', 'Template updated.');
    }

    public function destroy(NoteTemplate $noteTemplate): RedirectResponse
    {
        $noteTemplate->delete();

        return back()->with('success', 'Template deleted.');
    }
}
