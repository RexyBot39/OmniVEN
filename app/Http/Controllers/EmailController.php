<?php

namespace App\Http\Controllers;

use App\Support\DateRange;
use App\Support\Email\EmailHistory;
use App\Support\Email\Klaviyo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Email & SMS health: the last 30 days of Klaviyo campaigns and flows, list growth, and what needs fixing. */
class EmailController extends Controller
{
    public function index(Request $request): View
    {
        Klaviyo::refreshIfStale();
        $r = Klaviyo::report();
        $campaignTotals = ['recipients' => 0, 'delivered' => 0, 'opens' => 0, 'clicks' => 0, 'unsubs' => 0, 'spam' => 0, 'orders' => 0];
        foreach ($r['campaigns'] ?? [] as $c) {
            foreach ($campaignTotals as $k => $v) {
                $campaignTotals[$k] += $c[$k];
            }
        }
        $rate = fn ($n, $d) => $d > 0 ? $n / $d : null;
        $rates = [
            'open' => $rate($campaignTotals['opens'], $campaignTotals['delivered']),
            'click' => $rate($campaignTotals['clicks'], $campaignTotals['delivered']),
            'unsub' => $rate($campaignTotals['unsubs'], $campaignTotals['delivered']),
            'spam' => $rate($campaignTotals['spam'], $campaignTotals['delivered']),
            'delivery' => $rate($campaignTotals['delivered'], $campaignTotals['recipients']),
        ];

        $list = (string) config('klaviyo.main_list_id');
        $ago = DB::table('email_list_days')->where('list_id', $list)->where('day', '<=', CarbonImmutable::now(config('app.timezone'))->subDays(30)->format('Y-m-d'))->orderByDesc('day')->first();

        // Plain-language problems worth fixing, worst first.
        $checks = [];
        if ($r) {
            if (! $r['values_ok']) {
                $checks[] = ['bad', $r['order_events'] > 0
                    ? 'Klaviyo received '.number_format($r['order_events']).' order events in 30 days but none carried an order value, so it can count orders from email but can’t report email revenue. Ask the developer to send the order total as “value” with the order event.'
                    : 'Klaviyo received no order events in 30 days, so it can’t credit any orders to email. Check that the website still sends them.'];
            }
            if ($rates['unsub'] !== null && $rates['unsub'] > config('klaviyo.warn.unsubscribe_rate')) {
                $checks[] = ['warn', 'Campaign unsubscribe rate is '.number_format($rates['unsub'] * 100, 2).'%, above 0.5%. Send less often to unengaged people or tighten the audience.'];
            }
            if ($rates['spam'] !== null && $rates['spam'] > config('klaviyo.warn.spam_rate')) {
                $checks[] = ['warn', 'Spam complaints are '.number_format($rates['spam'] * 100, 3).'% of delivered campaign email, above the 0.08% inbox providers tolerate. Exclude unengaged profiles.'];
            }
            if ($rates['delivery'] !== null && $rates['delivery'] < config('klaviyo.warn.delivery_rate')) {
                $checks[] = ['warn', 'Only '.number_format($rates['delivery'] * 100, 1).'% of campaign email was delivered. Clean bounced addresses from the list.'];
            }
            if (empty($r['campaigns'])) {
                $checks[] = ['warn', 'No campaigns went to '.number_format(config('klaviyo.min_recipients')).' or more people in the last 30 days.'];
            }
        }

        // Any date range, from the daily history.
        $range = DateRange::fromRequest($request, 'last30', 'previous', null, EmailHistory::firstDay());
        $metric = array_key_exists((string) $request->query('metric'), EmailHistory::CHART) ? (string) $request->query('metric') : 'received';
        $cmp = $range->comparison();

        return view('email.index', [
            'range' => $range,
            'vsLabel' => SalesController::vsLabel($range),
            'metric' => $metric,
            'hist' => EmailHistory::totals(EmailHistory::days($range->from, $range->to)),
            'histPrev' => $cmp ? EmailHistory::totals(EmailHistory::days($cmp[0], $cmp[1])) : null,
            'histTrend' => EmailHistory::trend($range, $metric),
            'histStatus' => EmailHistory::status(),
            'configured' => Klaviyo::configured(),
            'status' => Klaviyo::status(),
            'r' => $r,
            'campaignTotals' => $campaignTotals,
            'rates' => $rates,
            'growth' => $r && $r['profiles'] !== null && $ago ? $r['profiles'] - (int) $ago->profiles : null,
            'checks' => $checks,
        ]);
    }
}
