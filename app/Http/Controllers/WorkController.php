<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** One form for new work: anyone can add a task, only admins a routine. Saving hands off to the task or routine controller. */
class WorkController extends Controller
{
    public function create(Request $request): View
    {
        $assignee = User::where('is_active', true)->find($request->integer('assignee'));

        return view('work.create', [
            'kind' => $request->query('type') === 'routine' && $request->user()->can('admin') ? 'routine' : 'task',
            'routine' => new Routine(['cadence' => 'daily', 'all_brands' => true, 'sort' => 100]),
            'assignee' => $assignee,
            'fromPerson' => $assignee && $request->query('from') === 'person' && $request->user()->can('admin'),
            'brands' => Brand::orderBy('sort')->get(),
            'people' => User::where('is_active', true)->orderBy('name')->get(),
            'taskBrands' => \App\Models\Task::brandChoices(),
            'campaign' => \App\Models\Campaign::find($request->integer('campaign') ?: (int) $request->old('campaign_id')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('kind') === 'routine') {
            Gate::authorize('admin');
        }
        $request->validate([
            'kind' => ['required', Rule::in(['routine', 'task'])],
            // A task always belongs to one person; a routine can be left to the whole team.
            'assignee_id' => [Rule::requiredIf($request->input('kind') === 'task')],
            'title' => $request->input('kind') === 'routine' ? ['max:160'] : [],
            'details' => $request->input('kind') === 'routine' ? ['nullable', 'max:1000'] : [],
        ], [
            'assignee_id.required' => 'Choose who the task is for.',
            'title.max' => 'Keep a routine\'s title under 160 characters.',
            'details.max' => 'Keep a routine\'s details under 1,000 characters.',
        ]);

        if ($request->input('kind') === 'routine') {
            // The shared "Details" box is a routine's description.
            $request->merge(['description' => $request->input('details')]);
            $response = app(RoutineController::class)->store($request);
        } else {
            $response = app(TaskController::class)->store($request);
        }

        $person = $request->boolean('back_to_person') && $request->user()->can('admin') ? User::find($request->integer('assignee_id')) : null;

        $campaign = $request->input('kind') === 'task' ? \App\Models\Campaign::find($request->integer('campaign_id')) : null;

        return match (true) {
            (bool) $person => redirect()->route('people.show', $person)->with('status', session('status')),
            (bool) $campaign => redirect()->route('campaigns.show', $campaign)->with('status', session('status')),
            default => $response,
        };
    }
}
