<?php

namespace App\Http\Controllers;

use App\Support\Revenue\Report;
use App\Support\Revenue\Revenue;
use App\Models\RevenueGoal;
use App\Models\SalesNote;
use App\Support\Activity;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Sales: revenue, orders and conversion by brand and channel, from GA4. Admins and revenue viewers only. */
class SalesController extends Controller
{
    public function index(Request $request): View
    {
        Revenue::refreshIfStale();
        $brands = Revenue::brands();
        $range = DateRange::fromRequest($request, 'mtd', 'yoy', Report::today(), Revenue::firstDay());
        $brand = $brands->get((string) $request->query('brand'));
        $metric = array_key_exists((string) $request->query('metric'), Report::METRICS) ? (string) $request->query('metric') : 'revenue';
        [$from, $to] = [$range->from, $range->to];
        $vs = $range->comparison() ?? false;
        $figures = Report::figures($from, $to, $brand?->id, $vs);
        // Goals and pace only make sense for this month so far.
        $isMtd = $range->preset === 'mtd';

        return view('sales.index', [
            'configured' => Revenue::configured(),
            'status' => Revenue::status(),
            'brands' => $brands,
            'brand' => $brand,
            'range' => $range,
            'vsLabel' => self::vsLabel($range),
            'from' => $from,
            'to' => $to,
            'metric' => $metric,
            'trend' => Report::trend($range, $metric, $brand?->id),
            'figures' => $figures,
            'channels' => Report::channels($from, $to, $brand?->id, $vs),
            'byBrand' => $brand ? collect() : $brands->map(fn ($b) => ['brand' => $b] + Report::figures($from, $to, $b->id, $vs))->values(),
            'pace' => $isMtd ? Report::pace($figures['cents'] ?? 0, $brand?->id) : null,
            'brandPace' => $isMtd ? $brands->mapWithKeys(fn ($b) => [$b->id => Report::pace(Report::figures($from, $to, $b->id)['cents'], $b->id)]) : collect(),
            'isMtd' => $isMtd,
            'goals' => \App\Models\RevenueGoal::where('month', Report::today()->startOfMonth()->format('Y-m-d'))->pluck('goal_cents', 'brand_id'),
            'week' => Report::today()->startOfWeek(\Carbon\CarbonImmutable::MONDAY),
            'notes' => \App\Models\SalesNote::with('user')->orderByDesc('week')->limit(8)->get(),
        ]);
    }

    /** Short words for the comparison, after "vs": "last year" or "previous period". */
    public static function vsLabel(DateRange $range): ?string
    {
        return match ($range->compare) {
            'yoy' => 'last year',
            'previous' => 'previous period',
            default => null,
        };
    }

    /** Admins: this month's revenue goal for each brand (blank clears it). */
    public function goals(Request $request): RedirectResponse
    {
        $data = $request->validate(['goal' => ['array'], 'goal.*' => ['nullable', 'numeric', 'min:0', 'max:100000000']]);
        $month = Report::today()->startOfMonth()->format('Y-m-d');
        foreach (Revenue::brands() as $b) {
            $v = $data['goal'][$b->id] ?? null;
            if ($v === null || $v === '' || (float) $v <= 0) {
                RevenueGoal::where('brand_id', $b->id)->where('month', $month)->delete();
            } else {
                RevenueGoal::updateOrCreate(['brand_id' => $b->id, 'month' => $month], ['goal_cents' => (int) round((float) $v * 100), 'user_id' => $request->user()->id]);
            }
        }
        Activity::log('sales.goals', 'Set revenue goals for '.Report::today()->format('F Y'));

        return redirect()->route('sales')->with('status', 'Goals saved for '.Report::today()->format('F').'.');
    }

    /** This week's revenue review note: what moved and why. */
    public function note(Request $request): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $week = Report::today()->startOfWeek(CarbonImmutable::MONDAY)->format('Y-m-d');
        SalesNote::updateOrCreate(['week' => $week], ['body' => trim($data['body']), 'user_id' => $request->user()->id]);
        Activity::log('sales.note', 'Wrote the weekly revenue review');

        return redirect()->to(route('sales').'#review')->with('status', 'This week’s note is saved.');
    }
}
