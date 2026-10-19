<?php

namespace App\Http\Controllers;

use App\Models\AiCheck;
use App\Models\Review;
use App\Models\RoutineCompletion;
use App\Models\SalesNote;
use App\Models\Task;
use App\Models\TemplateRun;
use App\Models\User;
use App\Models\UtmLink;
use App\Support\Revenue\Report;
use App\Support\Revenue\Revenue;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The monthly marketing report: one page that gathers what OmniVEN already knows about a month
 * (revenue, channels, goals, affiliates, reviews, AI answers, work done and the weekly notes),
 * ready to print or save as a PDF for leadership. Each section shows only when it has data.
 */
class MonthlyReportController extends Controller
{
    public function show(Request $request): View
    {
        $tz = config('app.timezone');
        $now = CarbonImmutable::now($tz);
        $month = $now->startOfMonth();
        if (preg_match('/^(\d{4})-(\d{2})$/', (string) $request->query('month'), $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            $month = CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, $tz)->min($now->startOfMonth());
        }
        $end = $month->endOfMonth()->min($now->endOfDay());
        $partial = $end->lt($month->endOfMonth());
        [$a, $b] = [$month->format('Y-m-d'), $end->format('Y-m-d')];
        // Timestamps are stored in the app timezone (Eastern), so compare in it too.
        $between = [$month->startOfDay(), $end->endOfDay()];

        // Revenue, channels, brands and goals (against the same month last year, loaded now if it isn't in).
        Revenue::refreshIfStale($month->subYear());
        $revenue = Report::figures($month, $end->startOfDay());
        $brands = Revenue::brands();
        $goals = DB::table('revenue_goals')->where('month', $a)->pluck('goal_cents', 'brand_id');
        $byBrand = $brands->map(fn ($br) => ['brand' => $br, 'goal' => isset($goals[$br->id]) ? (int) $goals[$br->id] : null]
            + Report::figures($month, $end->startOfDay(), $br->id))->values();
        $channels = array_slice(Report::channels($month, $end->startOfDay())['rows'], 0, 8);

        // Affiliates.
        $aff = DB::table('affiliate_months')->where('month', $a);
        $affiliates = [
            'sales' => (int) (clone $aff)->sum('sale_cents'), 'orders' => (int) (clone $aff)->sum('orders'),
            'comm' => (int) (clone $aff)->sum('commission_cents'), 'rows' => (clone $aff)->count(),
            'top' => (clone $aff)->orderByDesc('sale_cents')->limit(5)->get(),
        ];

        // Reviews: each platform's rating at the end of the month, and the month's reviews.
        $ratings = collect(config('reviews.platforms'))->map(fn ($p, $key) => [
            'label' => $p['label'],
            'snap' => DB::table('review_snapshots')->where('platform', $key)->where('day', '<=', $b)->orderByDesc('day')->first(),
        ])->filter(fn ($r) => $r['snap'] !== null);
        $monthReviews = Review::whereBetween('reviewed_at', $between)->get(['stars']);

        // AI answers.
        $ai = AiCheck::where('month', $a)->get(['result']);

        // Work done.
        $done = Task::with(['completer', 'brand'])->whereBetween('completed_at', $between)->get();
        $byPerson = $done->groupBy('completed_by')->map(fn ($ts) => ['user' => $ts->first()->completer, 'n' => $ts->count()])
            ->sortByDesc('n')->values();

        return view('reports.monthly', [
            'month' => $month,
            'end' => $end,
            'partial' => $partial,
            'revenue' => $revenue,
            'byBrand' => $byBrand,
            'goalTotal' => $goals->sum(),
            'channels' => $channels,
            'affiliates' => $affiliates,
            'ratings' => $ratings,
            'reviews' => ['n' => $monthReviews->count(), 'avg' => $monthReviews->avg('stars'), 'low' => $monthReviews->where('stars', '<=', 2)->count()],
            'ai' => ['n' => $ai->count(), 'mention' => $ai->count() ? $ai->whereIn('result', ['recommended', 'mentioned'])->count() / $ai->count() : null,
                'recommend' => $ai->count() ? $ai->where('result', 'recommended')->count() / $ai->count() : null],
            'work' => [
                'done' => $done->count(),
                'created' => Task::whereBetween('created_at', $between)->count(),
                'routines' => RoutineCompletion::whereBetween('completed_at', $between)->count(),
                'links' => UtmLink::whereBetween('created_at', $between)->count(),
                'byPerson' => $byPerson,
                'byBrand' => $done->groupBy(fn ($t) => $t->brand?->name ?? 'No brand')->map->count()->sortDesc(),
            ],
            'shipped' => $done->filter(fn ($t) => $t->outcome || $t->outcome_url)->sortBy('completed_at')->values(),
            'campaigns' => \App\Models\Campaign::with('brand')->during($month, $month->endOfMonth())
                ->withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->whereNotNull('completed_at'), 'links'])->orderBy('starts_on')->get(),
            'outreach' => [
                'pitched' => \App\Models\OutreachEvent::where('kind', 'pitched')->whereBetween('created_at', $between)->distinct()->count('publisher_id'),
                'replied' => \App\Models\OutreachEvent::where('kind', 'replied')->whereBetween('created_at', $between)->distinct()->count('publisher_id'),
                'live' => \App\Models\Publisher::where('stage', 'live')->whereBetween('live_at', $between)->orderBy('live_at')->get(),
            ],
            'launches' => TemplateRun::withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->whereNotNull('completed_at')])
                ->whereBetween('starts_on', [$a, $month->endOfMonth()->format('Y-m-d')])->orderBy('starts_on')->get(),
            'notes' => SalesNote::with('user')->whereBetween('week', [$month->startOfWeek(CarbonImmutable::MONDAY)->format('Y-m-d'), $b])->orderBy('week')->get(),
            'people' => User::count(),
        ]);
    }
}
