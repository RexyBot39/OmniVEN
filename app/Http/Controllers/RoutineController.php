<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Http\Controllers\Concerns\SyncsBrands;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoutineController extends Controller
{
    use SyncsBrands;

    public function toggle(Request $request, Routine $routine): RedirectResponse
    {
        $key = $routine->currentPeriodKey();
        $existing = $routine->completions()->where('period_key', $key)->first();

        if ($existing) {
            $existing->delete();
            Activity::log('routine.undone', "Unticked routine “{$routine->title}”", $routine);
            $msg = "Unmarked “{$routine->title}”.";
        } else {
            $routine->completions()->create([
                'user_id' => $request->user()->id,
                'period_key' => $key,
                'completed_at' => now(),
            ]);
            Activity::log('routine.done', "Ticked routine “{$routine->title}”", $routine);
            $msg = "Marked “{$routine->title}” done.";
        }

        // Ticked from someone's column on the task board: stay on Tasks.
        $tab = $request->input('return') === 'tasks' ? '#tasks' : '#routines';

        return redirect()->to(route('dashboard').$tab)->with('status', $msg);
    }

    /** New routines start on the shared "New routine or task" form. */
    public function create(): RedirectResponse
    {
        return redirect()->route('work.create', ['type' => 'routine']);
    }

    public function store(Request $request): RedirectResponse
    {
        $routine = new Routine($this->validated($request));
        $this->stampAssignment($routine, $request);
        $this->applyBrands($routine, $request);
        Activity::log('routine.created', "Added routine “{$routine->title}”".$this->assignedText($routine), $routine);

        return redirect()->to(route('dashboard').'#routines')->with('status', 'Routine added.');
    }

    public function edit(Routine $routine): View
    {
        return $this->form($routine->load('brands'));
    }

    public function update(Request $request, Routine $routine): RedirectResponse
    {
        $routine->fill($this->validated($request));
        $reassigned = $routine->isDirty('assignee_id');
        if ($reassigned) {
            $this->stampAssignment($routine, $request);
        }
        $this->applyBrands($routine, $request);
        Activity::log('routine.updated', "Edited routine “{$routine->title}”".($reassigned ? $this->assignedText($routine) : ''), $routine);

        return redirect()->to(route('dashboard').'#routines')->with('status', 'Routine saved.');
    }

    public function destroy(Routine $routine): RedirectResponse
    {
        Activity::log('routine.deleted', "Removed routine “{$routine->title}”", $routine);
        $routine->delete();

        return redirect()->to(route('dashboard').'#routines')->with('status', 'Routine removed.');
    }

    private function form(Routine $routine): View
    {
        return view('routines.form', [
            'routine' => $routine,
            'brands' => Brand::orderBy('sort')->get(),
            'people' => User::where('is_active', true)->orWhere('id', $routine->assignee_id)->orderBy('name')->get(),
        ]);
    }

    /** Record when and by whom the routine got its current person (cleared for "whole team"). */
    private function stampAssignment(Routine $routine, Request $request): void
    {
        $routine->fill($routine->assignee_id
            ? ['assigned_at' => now(), 'assigned_by' => $request->user()->id]
            : ['assigned_at' => null, 'assigned_by' => null]);
    }

    private function assignedText(Routine $routine): string
    {
        $who = $routine->assignee_id ? User::find($routine->assignee_id) : null;

        return $who ? " · assigned to {$who->name}" : ' · assigned to the whole team';
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'cadence' => ['required', Rule::in(array_keys(config('omniven.cadences')))],
            'description' => ['nullable', 'string', 'max:1000'],
            'link' => ['nullable', 'url:http,https', 'max:1024'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ] + $this->brandRules());
        $data['assignee_id'] = $data['assignee_id'] ?? null;
        // Only active people can be newly assigned; an existing assignment can stay as it is.
        $current = $request->route('routine');
        if ($data['assignee_id'] && ! User::whereKey($data['assignee_id'])->where('is_active', true)->exists()
            && ! ($current instanceof Routine && (int) $current->assignee_id === (int) $data['assignee_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['assignee_id' => 'That person no longer has access to OmniVEN.']);
        }
        unset($data['brands']);
        $data['sort'] ??= 100;

        return $data;
    }
}
