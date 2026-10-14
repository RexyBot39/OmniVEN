<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Http\Controllers\Concerns\SyncsBrands;
use App\Models\Brand;
use App\Models\Routine;
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

        return redirect()->to(route('dashboard').'#routines')->with('status', $msg);
    }

    public function create(): View
    {
        return $this->form(new Routine(['cadence' => 'daily', 'all_brands' => true, 'sort' => 100]));
    }

    public function store(Request $request): RedirectResponse
    {
        $routine = new Routine($this->validated($request));
        $this->applyBrands($routine, $request);
        Activity::log('routine.created', "Added routine “{$routine->title}”", $routine);

        return redirect()->to(route('dashboard').'#routines')->with('status', 'Routine added.');
    }

    public function edit(Routine $routine): View
    {
        return $this->form($routine->load('brands'));
    }

    public function update(Request $request, Routine $routine): RedirectResponse
    {
        $routine->fill($this->validated($request));
        $this->applyBrands($routine, $request);
        Activity::log('routine.updated', "Edited routine “{$routine->title}”", $routine);

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
        return view('routines.form', ['routine' => $routine, 'brands' => Brand::orderBy('sort')->get()]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'cadence' => ['required', Rule::in(array_keys(config('omniven.cadences')))],
            'description' => ['nullable', 'string', 'max:1000'],
            'link' => ['nullable', 'url:http,https', 'max:1024'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ] + $this->brandRules());
        unset($data['brands']);
        $data['sort'] ??= 100;

        return $data;
    }
}
