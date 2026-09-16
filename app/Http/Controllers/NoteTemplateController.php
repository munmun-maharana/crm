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
        ]);

        $max = (int) NoteTemplate::max('sort_order');

        NoteTemplate::create([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'sort_order' => $max + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Note template added.');
    }

    public function update(Request $request, NoteTemplate $noteTemplate): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $noteTemplate->update([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Template updated.');
    }

    public function destroy(NoteTemplate $noteTemplate): RedirectResponse
    {
        $noteTemplate->delete();

        return back()->with('success', 'Template deleted.');
    }
}
