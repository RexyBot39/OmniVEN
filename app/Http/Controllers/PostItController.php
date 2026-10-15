<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Models\PostIt;
use App\Models\PostItItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Personal to-do notes. Each person only ever sees and changes their own. */
class PostItController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'color' => ['required', Rule::in(array_keys(config('omniven.postit_colors')))],
        ]);
        // New notes start stuck to the screen; the owner can unstick them any time.
        $n = $request->user()->postits()->create($data + ['stuck' => true]);
        Activity::log('postit.created', 'Created a sticky note', $n);

        return $this->back('Note added. It stays in the top right of every page until you unstick it.');
    }

    public function update(Request $request, PostIt $postit): RedirectResponse
    {
        Gate::authorize('own-postit', $postit);
        $postit->update($request->validate([
            'title' => ['required', 'string', 'max:80'],
            'color' => ['required', Rule::in(array_keys(config('omniven.postit_colors')))],
        ]));

        return $this->back('Note saved.');
    }

    public function destroy(PostIt $postit): RedirectResponse
    {
        Gate::authorize('own-postit', $postit);
        Activity::log('postit.deleted', 'Deleted a sticky note', $postit);
        $postit->delete();

        return $this->back('Note removed.');
    }

    /** Stick a note to the screen, or unstick it back to the Sticky Notes tab. */
    public function stick(PostIt $postit): RedirectResponse
    {
        Gate::authorize('own-postit', $postit);
        $postit->update(['stuck' => ! $postit->stuck]);
        $postit->stuck
            ? Activity::log('postit.stuck', 'Stuck a sticky note to the screen', $postit)
            : Activity::log('postit.unstuck', 'Unstuck a sticky note', $postit);

        return $this->back($postit->stuck ? 'Note stuck to the screen.' : 'Note unstuck. It\'s still on the Sticky Notes tab.');
    }

    public function addItem(Request $request, PostIt $postit): RedirectResponse
    {
        Gate::authorize('own-postit', $postit);
        $postit->items()->create($request->validate(['body' => ['required', 'string', 'max:500']]));
        Activity::log('postit.item_added', 'Added a sticky note to-do', $postit);

        return $this->back();
    }

    public function toggleItem(PostItItem $item): RedirectResponse
    {
        Gate::authorize('own-postit', $item->postit);
        $item->update(['done_at' => $item->done_at ? null : now()]);
        $item->done_at
            ? Activity::log('postit.item_done', 'Checked off a sticky note to-do', $item->postit)
            : Activity::log('postit.item_undone', 'Unchecked a sticky note to-do', $item->postit);

        return $this->back();
    }

    public function destroyItem(PostItItem $item): RedirectResponse
    {
        Gate::authorize('own-postit', $item->postit);
        $item->delete();

        return $this->back();
    }

    public function clearDone(PostIt $postit): RedirectResponse
    {
        Gate::authorize('own-postit', $postit);
        $postit->items()->whereNotNull('done_at')->delete();
        Activity::log('postit.cleared', 'Cleared finished sticky note to-dos', $postit);

        return $this->back('Cleared finished items.');
    }

    private function back(?string $msg = null): RedirectResponse
    {
        // From the stuck note, stay on whatever page the person was on.
        $r = request()->boolean('from_sticky')
            ? redirect()->back(302, [], route('dashboard'))
            : redirect()->to(route('dashboard').'#postit');

        return $msg ? $r->with('status', $msg) : $r;
    }
}
