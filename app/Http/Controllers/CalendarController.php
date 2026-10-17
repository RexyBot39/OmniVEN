<?php

namespace App\Http\Controllers;

use App\Models\BoostLog;
use App\Models\Brand;
use App\Models\CalendarCheck;
use App\Models\CalendarItem;
use App\Models\CalendarWeek;
use App\Support\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The weekly action calendar: the whole team shares the same checkmarks, boost log and notes. */
class CalendarController extends Controller
{
    public function show(?CalendarWeek $week = null): View|RedirectResponse
    {
        $current = CalendarWeek::current();
        if (! $current) {
            return redirect()->route('dashboard')->with('error', 'The action calendar has no weeks yet.');
        }
        $week ??= $current;

        $weeks = CalendarWeek::orderBy('starts_on')->get();
        $recurring = CalendarItem::whereNull('calendar_week_id')->count();
        $oneOffs = CalendarItem::whereNotNull('calendar_week_id')->selectRaw('calendar_week_id, count(*) as n')->groupBy('calendar_week_id')->pluck('n', 'calendar_week_id');
        $done = CalendarCheck::selectRaw('calendar_week_id, count(*) as n')->groupBy('calendar_week_id')->pluck('n', 'calendar_week_id');
        $progress = $weeks->mapWithKeys(fn ($w) => [$w->id => ['done' => (int) ($done[$w->id] ?? 0), 'total' => $recurring + (int) ($oneOffs[$w->id] ?? 0)]]);

        $items = $week->items();
        $checks = CalendarCheck::with('user')->where('calendar_week_id', $week->id)->get()->keyBy('calendar_item_id');
        $boosts = $week->boosts()->with('user')->get();
        $quarterDone = $progress->sum('done');
        $quarterTotal = $progress->sum('total');

        $from = \Carbon\CarbonImmutable::parse($week->starts_on->format('Y-m-d'), config('app.timezone'));
        $to = $from->addDays(6);
        $campaigns = \App\Models\Campaign::with('brand')->during($from, $to)
            ->withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->whereNotNull('completed_at'), 'links'])
            ->with(['tasks' => fn ($q) => $q->with('assignee')->whereNull('completed_at')->whereNotNull('due_on')->whereBetween('due_on', [$from->format('Y-m-d'), $to->format('Y-m-d').' 23:59:59'])->orderBy('due_on')])
            ->orderBy('starts_on')->get();

        return view('calendar.show', [
            'campaigns' => $campaigns, 'weekFrom' => $from, 'weekTo' => $to,
            'week' => $week, 'current' => $current, 'weeks' => $weeks, 'progress' => $progress,
            'items' => $items, 'checks' => $checks, 'boosts' => $boosts,
            'brands' => Brand::all()->keyBy('slug'),
            'quarterPct' => $quarterTotal ? (int) round($quarterDone / $quarterTotal * 100) : 0,
            'totalSpend' => BoostLog::where('beat_median', true)->sum('amount'),
        ]);
    }

    public function toggle(Request $request, CalendarWeek $week, CalendarItem $item): RedirectResponse|JsonResponse
    {
        abort_unless($item->calendar_week_id === null || $item->calendar_week_id === $week->id, 404);

        $existing = CalendarCheck::where(['calendar_week_id' => $week->id, 'calendar_item_id' => $item->id])->first();
        if ($existing) {
            $existing->delete();
            $on = false;
        } else {
            CalendarCheck::create(['calendar_week_id' => $week->id, 'calendar_item_id' => $item->id, 'user_id' => $request->user()->id, 'checked_at' => now()]);
            $on = true;
        }
        Activity::log($on ? 'calendar.checked' : 'calendar.unchecked', ($on ? 'Checked' : 'Unchecked').' week '.$week->number.': “'.\Illuminate\Support\Str::limit($item->title, 80).'”', $item);

        if ($request->expectsJson()) {
            return response()->json(['checked' => $on]);
        }

        return redirect()->to(route('calendar.week', $week).'#item-'.$item->id);
    }

    public function storeBoost(Request $request, CalendarWeek $week): RedirectResponse
    {
        $data = $request->validate([
            'day' => ['required', Rule::in(BoostLog::DAYS)],
            'brand_slug' => ['required', Rule::in(array_keys(BoostLog::BRANDS))],
            'format' => ['nullable', 'string', 'max:40'],
            'beat' => ['required', Rule::in(['yes', 'no'])],
            'amount' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $boost = $week->boosts()->create([
            'day' => $data['day'], 'brand_slug' => $data['brand_slug'], 'format' => $data['format'] ?? null,
            'beat_median' => $data['beat'] === 'yes', 'amount' => $data['beat'] === 'yes' ? (int) ($data['amount'] ?? 0) : 0,
            'user_id' => $request->user()->id,
        ]);
        Activity::log('calendar.boost', 'Logged a boost decision for week '.$week->number.': '.$boost->brandLabel().' '.($boost->beat_median ? 'boosted $'.$boost->amount : 'skipped'), $boost);

        return redirect()->to(route('calendar.week', $week).'#boost-log')->with('status', $boost->beat_median ? 'Boost logged.' : 'Skip logged.');
    }

    public function destroyBoost(Request $request, BoostLog $boost): RedirectResponse
    {
        abort_unless($request->user()->can('admin') || $boost->user_id === $request->user()->id, 403);
        $week = CalendarWeek::findOrFail($boost->calendar_week_id);
        $boost->delete();

        return redirect()->to(route('calendar.week', $week).'#boost-log')->with('status', 'Removed that boost decision.');
    }

    public function notes(Request $request, CalendarWeek $week): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:5000']]);
        $week->update(['notes' => $data['notes'] ?? null, 'notes_by' => $request->user()->id, 'notes_at' => now()]);
        Activity::log('calendar.notes', 'Updated the Sunday review note for week '.$week->number, $week);

        if ($request->expectsJson()) {
            return response()->json(['saved' => true]);
        }

        return redirect()->to(route('calendar.week', $week).'#review')->with('status', 'Sunday review note saved.');
    }

    public function exportBoosts(): StreamedResponse
    {
        $rows = BoostLog::with('user')->orderBy('calendar_week_id')->orderBy('id')->get();
        $weeks = CalendarWeek::all()->keyBy('id');

        return $this->csv('boost-log.csv', ['Week', 'Dates', 'Day', 'Brand', 'Format', 'Beat median', 'Spend', 'Logged by'],
            $rows->map(fn ($r) => [$weeks[$r->calendar_week_id]->number ?? '', $weeks[$r->calendar_week_id]->range_label ?? '', $r->day, $r->brandLabel(), $r->format, $r->beat_median ? 'yes' : 'no', $r->spend(), $r->user?->name]));
    }

    public function exportTasks(): StreamedResponse
    {
        $brands = Brand::all()->keyBy('slug');
        $rows = collect();
        foreach (CalendarWeek::orderBy('starts_on')->get() as $w) {
            $checks = CalendarCheck::with('user')->where('calendar_week_id', $w->id)->get()->keyBy('calendar_item_id');
            foreach ($w->items() as $i) {
                $c = $checks[$i->id] ?? null;
                $rows->push([$w->number, $w->range_label, $i->day, $i->brand_slug ? ($brands[$i->brand_slug]->name ?? $i->brand_slug) : 'All', $i->title, $i->keyword, $i->owner, $c ? 'yes' : 'no', $c?->user?->name]);
            }
        }

        return $this->csv('action-calendar-tasks.csv', ['Week', 'Dates', 'Day', 'Brand', 'Task', 'Funnel', 'Owner', 'Done', 'Checked by'], $rows);
    }

    private function csv(string $name, array $head, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $head);
            foreach ($rows as $r) {
                // Stop spreadsheet apps from treating a cell as a formula.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $r));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
