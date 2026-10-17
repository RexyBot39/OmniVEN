<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\TemplateRun;
use App\Models\User;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Launch and promo templates. Anyone can run one: give it a name, brand, start day and people, and
 * every step becomes a task due relative to that day. Admins edit the templates.
 */
class TemplateController extends Controller
{
    public function index(): View
    {
        return view('templates.index', [
            'templates' => TaskTemplate::withCount('items')->orderBy('sort')->orderBy('name')->get(),
            'runs' => TemplateRun::with(['template', 'brand', 'user'])->withCount([
                'tasks', 'tasks as done_count' => fn ($q) => $q->whereNotNull('completed_at'),
            ])->latest()->limit(12)->get(),
        ]);
    }

    public function runForm(TaskTemplate $template): View
    {
        return view('templates.run', [
            'template' => $template->load('items.assignee'),
            'people' => $this->people(),
            'taskBrands' => Task::brandChoices(),
            'defaultStart' => CarbonImmutable::now(config('app.timezone'))->addDays(max(0, -(int) $template->items->min('offset_days')))->format('Y-m-d'),
        ]);
    }

    public function run(Request $request, TaskTemplate $template): RedirectResponse
    {
        $ids = $this->people()->pluck('id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'brand_id' => ['nullable', Rule::in(Task::brandChoices()->pluck('id'))],
            'starts_on' => ['required', 'date'],
            'assignee_id' => ['required', Rule::in($ids)],
            'assignees' => ['array'],
            'assignees.*' => ['nullable', Rule::in($ids)],
            'skip' => ['array'],
        ], ['assignee_id.required' => 'Choose who gets the steps nobody else is picked for.']);

        $start = CarbonImmutable::parse($data['starts_on'], config('app.timezone'));
        $items = $template->items()->get()->reject(fn ($i) => $request->boolean("skip.{$i->id}"));
        if ($items->isEmpty()) {
            return back()->withErrors(['skip' => 'Keep at least one step.'])->withInput();
        }

        $me = $request->user();
        $run = DB::transaction(function () use ($template, $data, $start, $items, $me, $request) {
            $run = TemplateRun::create(['task_template_id' => $template->id, 'name' => trim($data['name']), 'brand_id' => $data['brand_id'] ?? null,
                'starts_on' => $start->format('Y-m-d'), 'user_id' => $me->id]);
            foreach ($items as $i) {
                Task::create([
                    'title' => mb_substr(TaskTemplateItem::withName($i->title, $run->name), 0, 200),
                    'details' => TaskTemplateItem::withName($i->details, $run->name),
                    'assignee_id' => $request->input("assignees.{$i->id}") ?: ($i->assignee_id && $i->assignee?->is_active ? $i->assignee_id : $data['assignee_id']),
                    'brand_id' => $run->brand_id,
                    'hot' => $i->hot,
                    'due_on' => $start->addDays($i->offset_days)->format('Y-m-d'),
                    'created_by' => $me->id, 'assigned_at' => now(), 'assigned_by' => $me->id,
                    'template_run_id' => $run->id,
                ]);
            }

            return $run;
        });
        Activity::log('template.run', 'Started “'.$run->name.'” from the '.$template->name.' template ('.$items->count().' tasks)', $run);

        return redirect()->route('templates.show-run', $run)->with('status', $items->count().' tasks created for '.$run->name.'.');
    }

    public function showRun(TemplateRun $run): View
    {
        return view('templates.show-run', [
            'run' => $run->load(['template', 'brand', 'user']),
            'tasks' => $run->tasks()->with(['assignee', 'completer'])->orderBy('due_on')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $t = TaskTemplate::create(['name' => $data['name'], 'sort' => (int) TaskTemplate::max('sort') + 10]);

        return redirect()->route('templates.edit', $t);
    }

    public function edit(TaskTemplate $template): View
    {
        return view('templates.edit', ['template' => $template->load('items'), 'people' => $this->people()]);
    }

    /** Save the template and its steps: change rows, add the filled-in blank rows, remove the ticked ones. */
    public function update(Request $request, TaskTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'items' => ['array'],
            'items.*.title' => ['nullable', 'string', 'max:200'],
            'items.*.details' => ['nullable', 'string', 'max:2000'],
            'items.*.offset_days' => ['nullable', 'integer', 'min:-365', 'max:365'],
            'items.*.assignee_id' => ['nullable', Rule::in($this->people()->pluck('id'))],
        ]);
        DB::transaction(function () use ($template, $data, $request) {
            $template->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $existing = $template->items()->get()->keyBy('id');
            foreach ($data['items'] ?? [] as $key => $row) {
                $item = str_starts_with((string) $key, 'new') ? null : $existing->get((int) $key);
                if ($item && $request->boolean("items.$key.remove")) {
                    $item->delete();

                    continue;
                }
                if (trim((string) ($row['title'] ?? '')) === '') {
                    continue;
                }
                $fields = ['title' => trim($row['title']), 'details' => $row['details'] ?? null, 'offset_days' => (int) ($row['offset_days'] ?? 0),
                    'assignee_id' => $row['assignee_id'] ?? null, 'hot' => $request->boolean("items.$key.hot")];
                $item ? $item->update($fields) : $template->items()->create($fields + ['sort' => 1000]);
            }
        });

        return redirect()->route('templates.edit', $template)->with('status', 'Template saved.');
    }

    public function destroy(TaskTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('templates.index')->with('status', 'Template deleted. Tasks already created stay.');
    }

    private function people()
    {
        return User::where('is_active', true)->orderBy('name')->get();
    }
}
