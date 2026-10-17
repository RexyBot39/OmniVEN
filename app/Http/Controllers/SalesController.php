<?php

namespace App\Http\Controllers;

use App\Support\Revenue\Report;
use App\Support\Revenue\Revenue;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Sales: revenue, orders and conversion by brand and channel, from GA4. Admins and revenue viewers only. */
class SalesController extends Controller
{
    public function index(Request $request): View
    {
        Revenue::refreshIfStale();
        $brands = Revenue::brands();
        $period = in_array($request->query('period'), array_keys(Report::PERIODS), true) ? $request->query('period') : 'mtd';
        $brand = $brands->get((string) $request->query('brand'));
        [$from, $to] = Report::range($period);

        return view('sales.index', [
            'configured' => Revenue::configured(),
            'status' => Revenue::status(),
            'brands' => $brands,
            'brand' => $brand,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'figures' => Report::figures($from, $to, $brand?->id),
            'channels' => Report::channels($from, $to, $brand?->id),
            'byBrand' => $brand ? collect() : $brands->map(fn ($b) => ['brand' => $b] + Report::figures($from, $to, $b->id))->values(),
        ]);
    }
}
