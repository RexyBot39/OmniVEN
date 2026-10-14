<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['pinned'] = $request->user()->is_admin && $request->boolean('pinned');
        $note = Note::create($data + ['user_id' => $request->user()->id]);
        Activity::log('note.posted', "Posted bulletin note “{$note->title}”", $note);

        return redirect()->route('dashboard')->with('status', 'Note posted.');
    }

    public function edit(Note $note): View
    {
        Gate::authorize('manage-note', $note);

        return view('notes.form', compact('note'));
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        Gate::authorize('manage-note', $note);
        $data = $this->validated($request);
        $data['pinned'] = $request->user()->is_admin ? $request->boolean('pinned') : $note->pinned;
        $note->update($data);
        Activity::log('note.updated', "Edited bulletin note “{$note->title}”", $note);

        return redirect()->route('dashboard')->with('status', 'Note saved.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        Gate::authorize('manage-note', $note);
        Activity::log('note.deleted', "Removed bulletin note “{$note->title}”", $note);
        $note->delete();

        return redirect()->route('dashboard')->with('status', 'Note removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:4000'],
        ]);
    }
}
