<?php

namespace App\Http\Controllers;

use App\Support\Affiliate\Affiliates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Affiliate scoreboard: CJ and Awin sales, commission, pending commission and top publishers by month. */
class AffiliatesController extends Controller
{
    public function index(Request $request): View
    {
        if (Affiliates::configured()) {
            Affiliates::refreshIfStale();
        }
        $tz = config('app.timezone');
        $this_ = CarbonImmutable::now($tz)->startOfMonth();
        $month = $this_;
        if (preg_match('/^(\d{4})-(\d{2})$/', (string) $request->query('month'), $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            $month = CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, $tz)->min($this_);
        }

        $sum = fn (CarbonImmutable $mo) => DB::table('affiliate_months')->where('month', $mo->format('Y-m-d'))->groupBy('network')
            ->selectRaw('network, sum(orders) as orders, sum(sale_cents) as sales, sum(commission_cents) as comm, sum(pending_cents) as pending, count(*) as publishers')
            ->get()->keyBy('network');
        $now = $sum($month);
        $prev = $sum($month->subMonth());
        $total = fn ($rows) => [
            'orders' => (int) $rows->sum('orders'), 'sales' => (int) $rows->sum('sales'), 'comm' => (int) $rows->sum('comm'),
            'pending' => (int) $rows->sum('pending'), 'publishers' => (int) $rows->sum('publishers'),
        ];

        $from = $this_->subMonths(11);
        $trend = DB::table('affiliate_months')->where('month', '>=', $from->format('Y-m-d'))->groupBy('month')
            ->selectRaw('month, sum(sale_cents) as sales, sum(commission_cents) as comm, sum(orders) as orders')->get()
            ->keyBy(fn ($r) => substr((string) $r->month, 0, 7));

        return view('affiliates.index', [
            'month' => $month,
            'isCurrent' => $month->eq($this_),
            'status' => Affiliates::status(),
            'configured' => Affiliates::configured(),
            'networks' => collect(Affiliates::NETWORKS)->map(fn ($label, $n) => ['label' => $label, 'now' => $now->get($n), 'prev' => $prev->get($n)]),
            'total' => $total($now),
            'prevTotal' => $total($prev),
            'active' => fn (CarbonImmutable $mo) => DB::table('affiliate_months')->where('month', $mo->format('Y-m-d'))->where('orders', '>', 0)->count(),
            'publishers' => DB::table('affiliate_months')->where('month', $month->format('Y-m-d'))
                ->orderByDesc('sale_cents')->orderByDesc('commission_cents')->limit(15)->get(),
            'trend' => collect(range(0, 11))->map(fn ($i) => $from->addMonths($i))->map(fn ($mo) => [
                'month' => $mo,
                'sales' => (int) ($trend[$mo->format('Y-m')]->sales ?? 0),
                'comm' => (int) ($trend[$mo->format('Y-m')]->comm ?? 0),
                'orders' => (int) ($trend[$mo->format('Y-m')]->orders ?? 0),
            ]),
        ]);
    }
}
