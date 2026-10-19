<?php

namespace App\Http\Controllers;

use App\Support\DateRange;
use App\Support\Seo\SearchConsole;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Search: each brand's Google Search Console picture for a picked range (default: the last 28 days
 * against the 28 before), with daily totals back as far as Search Console keeps them.
 */
class SeoController extends Controller
{
    public const METRICS = [
        'clicks' => ['Clicks', 'int'],
        'impressions' => ['Impressions', 'int'],
        'ctr' => ['Click-through rate', 'pct'],
        'position' => ['Average position', 'dec'],
    ];

    public function index(Request $request): View
    {
        SearchConsole::refreshIfStale();
        $brands = SearchConsole::brands();
        $brand = $brands->get((string) $request->query('brand')) ?? $brands->first();
        $end = CarbonImmutable::now(config('app.timezone'))->startOfDay()->subDays(config('search_console.lag_days'));
        $range = DateRange::fromRequest($request, 'last28', 'previous', $end, $brand ? SearchConsole::firstDay($brand->id) : null);
        $metric = array_key_exists((string) $request->query('metric'), self::METRICS) ? (string) $request->query('metric') : 'clicks';

        // The regular summary covers the default view; any other range is looked up (and kept a while).
        $summary = $brand ? SearchConsole::summary($brand->slug) : null;
        $isDefault = $range->preset === 'last28' && $range->compare === 'previous';
        $s = $isDefault || ! $brand ? $summary : ($summary ? SearchConsole::forRange($brand->slug, $range) : null);
        $rangeError = is_array($s) && isset($s['error']) ? $s['error'] : null;
        if ($rangeError) {
            $s = null;
        }

        $cmp = $range->comparison();
        $days = $brand ? SearchConsole::days($brand->id, $range->from, $range->to) : [];
        $then = $brand && $cmp ? SearchConsole::days($brand->id, $cmp[0], $cmp[1]) : null;

        return view('seo.index', [
            'configured' => SearchConsole::configured(),
            'status' => SearchConsole::status(),
            'brands' => $brands,
            'brand' => $brand,
            'range' => $range,
            'vsLabel' => SalesController::vsLabel($range),
            'metric' => $metric,
            'totals' => SearchConsole::sumDays($days),
            'prevTotals' => $then !== null ? SearchConsole::sumDays($then) : null,
            'trend' => $this->trend($range, $metric, $days, $then),
            'hasSummary' => $summary !== null,
            's' => $s,
            'rangeError' => $rangeError,
        ]);
    }

    private function trend(DateRange $range, string $metric, array $days, ?array $then): array
    {
        $pick = fn (?array $rows, string $k) => $rows === null ? null : array_map(fn ($r) => $r[$k], $rows);
        if (in_array($metric, ['clicks', 'impressions'], true)) {
            $k = $metric === 'clicks' ? 'c' : 'i';

            return $range->series($pick($days, $k), $pick($then, $k));
        }
        // Ratios: sum the parts per point, then divide.
        [$num, $den] = $metric === 'ctr' ? ['c', 'i'] : ['p', 'i'];
        $n = $range->series($pick($days, $num), $pick($then, $num));
        $d = $range->series($pick($days, $den), $pick($then, $den));

        return array_map(fn ($p, $q) => array_merge($p, [
            'v' => $q['v'] > 0 ? $p['v'] / $q['v'] : null,
            'c' => $p['c'] !== null && $q['c'] > 0 ? $p['c'] / $q['c'] : null,
        ]), $n, $d);
    }
}
