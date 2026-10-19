<?php

namespace App\Http\Controllers;

use App\Support\Affiliate\Affiliates;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Affiliate scoreboard: CJ and Awin sales, commission, pending commission and top publishers for a
 * picked range of months, against the previous months or the same months last year.
 */
class AffiliatesController extends Controller
{
    /** Totals are kept by month, so ranges are whole months. */
    public const PRESETS = ['mtd', 'last_month', 'qtd', 'last_quarter', 'ytd', 'last_year', 'last12m', 'all', 'custom'];

    public function index(Request $request): View
    {
        if (Affiliates::configured()) {
            Affiliates::refreshIfStale();
        }
        $tz = config('app.timezone');
        $this_ = CarbonImmutable::now($tz)->startOfMonth();
        $first = DB::table('affiliate_months')->min('month');
        $min = $first ? CarbonImmutable::parse(substr((string) $first, 0, 10), $tz) : null;
        // An older ?month=2026-03 link opens that month.
        if (preg_match('/^(\d{4})-(\d{2})$/', (string) $request->query('month'), $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            $mo = CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, $tz)->min($this_);
            $request->query->add(['range' => 'custom', 'from' => $mo->format('Y-m-d'), 'to' => $mo->endOfMonth()->format('Y-m-d')]);
        }
        $range = DateRange::fromRequest($request, 'mtd', 'previous', null, $min, self::PRESETS);
        $cmp = $range->comparison();
        [$m1, $m2] = [$range->from->startOfMonth(), $range->to->startOfMonth()];
        [$c1, $c2] = $cmp ? [$cmp[0]->startOfMonth(), $cmp[1]->startOfMonth()] : [null, null];
        $months = fn ($a, $b) => DB::table('affiliate_months')->whereBetween('month', [$a->format('Y-m-d'), $b->format('Y-m-d')]);

        $sum = fn ($a, $b) => $months($a, $b)->groupBy('network')
            ->selectRaw('network, sum(orders) as orders, sum(sale_cents) as sales, sum(commission_cents) as comm, sum(pending_cents) as pending')
            ->get()->keyBy('network');
        $now = $sum($m1, $m2);
        $prev = $cmp ? $sum($c1, $c2) : collect();
        $total = fn ($rows) => $rows->isEmpty() ? null : [
            'orders' => (int) $rows->sum('orders'), 'sales' => (int) $rows->sum('sales'), 'comm' => (int) $rows->sum('comm'), 'pending' => (int) $rows->sum('pending'),
        ];
        $active = fn ($a, $b) => $months($a, $b)->where('orders', '>', 0)->distinct()->count(DB::raw("network || ':' || publisher_id"));

        // Month by month across the range (at least the last 12 months to its end), with the comparison lined up.
        $chartFrom = $m2->subMonths(11)->min($m1);
        $byMonth = DB::table('affiliate_months')->where('month', '>=', ($c1 ?? $chartFrom)->min($chartFrom)->format('Y-m-d'))->where('month', '<=', $m2->format('Y-m-d'))
            ->groupBy('month')->selectRaw('month, sum(sale_cents) as sales, sum(commission_cents) as comm, sum(orders) as orders')->get()
            ->keyBy(fn ($r) => substr((string) $r->month, 0, 7));
        $offset = $range->compare === 'yoy' ? 12 : ($cmp ? ($m2->year - $m1->year) * 12 + $m2->month - $m1->month + 1 : 0);
        $trend = collect();
        for ($mo = $chartFrom; $mo->lte($m2); $mo = $mo->addMonth()) {
            $row = $byMonth[$mo->format('Y-m')] ?? null;
            $then = $offset ? ($byMonth[$mo->subMonths($offset)->format('Y-m')] ?? null) : null;
            $trend->push(['month' => $mo, 'in_range' => $mo->gte($m1), 'sales' => (int) ($row->sales ?? 0), 'comm' => (int) ($row->comm ?? 0), 'orders' => (int) ($row->orders ?? 0),
                'then_sales' => $offset ? (int) ($then->sales ?? 0) : null]);
        }

        return view('affiliates.index', [
            'range' => $range,
            'vsLabel' => SalesController::vsLabel($range),
            'm1' => $m1,
            'm2' => $m2,
            'cmpMonths' => $cmp ? [$c1, $c2] : null,
            'status' => Affiliates::status(),
            'configured' => Affiliates::configured(),
            'networks' => collect(Affiliates::NETWORKS)->map(fn ($label, $n) => ['label' => $label, 'now' => $now->get($n), 'prev' => $prev->get($n)]),
            'total' => $total($now) ?? ['orders' => 0, 'sales' => 0, 'comm' => 0, 'pending' => 0],
            'prevTotal' => $total($prev),
            'activeNow' => $active($m1, $m2),
            'activePrev' => $cmp ? $active($c1, $c2) : null,
            'publishers' => $months($m1, $m2)->groupBy('network', 'publisher_id')
                ->selectRaw('network, publisher_id, max(publisher_name) as publisher_name, sum(orders) as orders, sum(sale_cents) as sale_cents, sum(commission_cents) as commission_cents, sum(pending_cents) as pending_cents')
                ->orderByDesc('sale_cents')->orderByDesc('commission_cents')->limit(15)->get(),
            'trend' => $trend,
            'chart' => $trend->map(fn ($t) => ['label' => $t['month']->format('F Y'), 'short' => $t['month']->format($trend->first()['month']->year === $m2->year ? 'M' : 'M y'),
                'start' => $t['month']->format('Y-m-d'), 'v' => $t['sales'], 'c' => $t['then_sales']])->values()->all(),
        ]);
    }
}
