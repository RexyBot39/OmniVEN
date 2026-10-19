<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use App\Support\DateRange;
use App\Support\Email\EmailHistory;
use App\Support\Revenue\Revenue;
use App\Support\Seo\SearchConsole;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HistoryRangesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $pk = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pk, $pem);
        config([
            'revenue.ga4.credentials' => json_encode(['client_email' => 'sa@proj.iam.gserviceaccount.com', 'private_key' => $pem]),
            'revenue.ga4.properties' => ['39dg' => '111'],
        ]);
        Revenue::forget();
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function range(array $q, string $default = 'mtd', string $compare = 'yoy', ?string $min = null): DateRange
    {
        return DateRange::fromRequest(HttpRequest::create('/x', 'GET', $q), $default, $compare, null, $min ? CarbonImmutable::parse($min, config('app.timezone')) : null);
    }

    private function d(string $day): CarbonImmutable
    {
        return CarbonImmutable::parse($day, config('app.timezone'));
    }

    public function test_presets_custom_dates_and_comparisons(): void
    {
        $r = $this->range([]);
        $this->assertSame(['2026-10-01', '2026-10-16'], [$r->from->format('Y-m-d'), $r->to->format('Y-m-d')]);
        $this->assertSame(['2025-10-01', '2025-10-16'], array_map(fn ($d) => $d->format('Y-m-d'), $r->comparison()));

        // Previous period: the same number of days just before; whole months compare with whole months.
        $r = $this->range(['range' => 'last30', 'compare' => 'previous']);
        $this->assertSame(['2026-08-18', '2026-09-16'], array_map(fn ($d) => $d->format('Y-m-d'), $r->comparison()));
        $r = $this->range(['range' => 'last_month', 'compare' => 'previous']);
        $this->assertSame(['2026-08-01', '2026-08-31'], array_map(fn ($d) => $d->format('Y-m-d'), $r->comparison()));
        $r = $this->range(['range' => 'last_quarter', 'compare' => 'previous']);
        $this->assertSame(['2026-07-01', '2026-09-30', '2026-04-01', '2026-06-30'], [$r->from->format('Y-m-d'), $r->to->format('Y-m-d'), ...array_map(fn ($d) => $d->format('Y-m-d'), $r->comparison())]);

        // Custom dates are kept inside the history and never run past today; swapped dates are put right.
        $r = $this->range(['range' => 'custom', 'from' => '2026-12-01', 'to' => '2026-09-01', 'compare' => 'none'], min: '2024-01-01');
        $this->assertSame(['2026-09-01', '2026-10-16'], [$r->from->format('Y-m-d'), $r->to->format('Y-m-d')]);
        $this->assertNull($r->comparison());
        $r = $this->range(['range' => 'custom', 'from' => '2019-01-01', 'to' => 'nonsense'], min: '2024-01-01');
        $this->assertSame('2024-01-01', $r->from->format('Y-m-d'));
        $r = $this->range(['range' => 'all'], min: '2023-06-01');
        $this->assertSame('2023-06-01', $r->from->format('Y-m-d'));
        $this->assertSame('mtd', $this->range(['range' => 'custom'])->preset, 'custom with no dates falls back');
        $this->assertSame('last30', $this->range(['period' => 'last30'])->preset, 'old ?period= links still work');

        // Points: daily to two months, weekly to a year and a half, monthly beyond, lined up with the comparison.
        $this->assertSame('day', $this->range(['range' => 'last30'])->grain());
        $this->assertSame('week', $this->range(['range' => 'last12m'])->grain());
        $this->assertSame('month', $this->range(['range' => 'all'], min: '2022-01-01')->grain());
        $r = $this->range(['range' => 'last7', 'compare' => 'previous']);
        $pts = $r->series(['2026-10-16' => 5, '2026-10-10' => 2], ['2026-10-09' => 7, '2026-10-01' => 99]);
        $this->assertCount(7, $pts);
        $this->assertSame([2, 7], [$pts[0]['v'], $pts[6]['c']]);
        $this->assertSame(5, $pts[6]['v']);
        $this->assertSame(0, array_sum(array_column(array_slice($pts, 1, 5), 'v')));
    }

    public function test_ga4_history_goes_back_to_the_first_month_with_data(): void
    {
        $asked = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use (&$asked) {
            if ($r->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'ya29.t', 'expires_in' => 3600]);
            }
            if ($r['dimensions'][0]['name'] === 'yearMonth') {
                $this->assertSame('2015-08-14', $r['dateRanges'][0]['startDate']);

                return Http::response(['rows' => [
                    ['dimensionValues' => [['value' => '202305']], 'metricValues' => [['value' => '0']]],
                    ['dimensionValues' => [['value' => '202306']], 'metricValues' => [['value' => '812']]],
                    ['dimensionValues' => [['value' => '202610']], 'metricValues' => [['value' => '9000']]],
                ], 'rowCount' => 3]);
            }
            $asked[] = $r['dateRanges'][0]['startDate'];

            return Http::response(['rows' => [], 'rowCount' => 0]);
        });

        $this->assertTrue(Revenue::refresh());
        $this->assertSame('2023-06-01', Revenue::status()['history_floor']);
        $this->assertCount(1 + config('revenue.backfill_months_per_refresh'), $asked, 'recent days, then a few months of history per refresh');

        $this->assertTrue(Revenue::refresh(true));
        $this->assertSame('2023-06-01', min($asked));
        $this->assertSame('2023-06-01', Revenue::status()['history_from']);

        // A history limit, when set, wins over a longer GA4 history.
        config(['revenue.backfill_days' => 400]);
        $this->assertSame('2025-09-11', Revenue::floor($this->d('2026-10-16'))->format('Y-m-d'));
    }

    public function test_asking_for_last_year_loads_it_when_history_is_not_that_far_back_yet(): void
    {
        $asked = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use (&$asked) {
            if ($r->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'ya29.t', 'expires_in' => 3600]);
            }
            $asked[] = $r['dateRanges'][0]['startDate'];
            $last = $r['dateRanges'][0]['startDate'] <= '2025-09-10' && $r['dateRanges'][0]['endDate'] >= '2025-09-10';

            return Http::response(['rows' => $last ? [['dimensionValues' => [['value' => '20250910'], ['value' => 'Organic Search'], ['value' => 'google']], 'metricValues' => [['value' => '100'], ['value' => '4'], ['value' => '400.00']]]] : [], 'rowCount' => $last ? 1 : 0]);
        });
        // GA4 has data from 2023, but only the last couple of months are in so far; the numbers are fresh.
        Cache::forever('revenue:history-floor', '2023-01-01');
        Cache::forever('revenue:backfilled-to', '2026-08-01');
        Cache::forever('revenue:last-sync', now()->timestamp);

        $html = $this->actingAs($this->admin)->get('/sales?range=last_month&compare=yoy')->assertOk()->getContent();
        $this->assertStringContainsString('Loading these dates from GA4.', $html);
        $this->assertStringContainsString('Loading… then', $html);
        // The page loaded the missing months after it was sent, back past September 2025.
        $this->assertLessThanOrEqual('2025-09-01', \Illuminate\Support\Facades\Cache::get('revenue:backfilled-to'));
        $this->assertSame(40000, (int) DB::table('revenue_days')->where('day', '2025-09-10')->sum('revenue_cents'));

        $html = $this->actingAs($this->admin)->get('/sales?range=last_month&compare=yoy')->assertOk()->getContent();
        $this->assertStringNotContainsString('Loading these dates', $html);
        $this->assertStringContainsString('$400 then', $html);

        // Dates GA4 never had are explained, not loaded forever.
        Cache::forever('revenue:backfilled-to', '2023-01-01');
        $html = $this->actingAs($this->admin)->get('/sales?range=custom&from=2023-02-01&to=2023-02-28&compare=yoy')->assertOk()->getContent();
        $this->assertStringContainsString('GA4 has no numbers before Jan 1, 2023', $html);
        $this->assertStringNotContainsString('Loading these dates', $html);
    }

    public function test_sales_page_shows_any_range_against_the_previous_period(): void
    {
        $brand = Brand::where('slug', '39dg')->first();
        $now = now();
        foreach ([['2026-09-10', 100, 5, 50000], ['2026-09-20', 120, 6, 60000], ['2026-08-15', 80, 4, 40000], ['2025-09-10', 90, 3, 30000]] as [$day, $s, $o, $c]) {
            DB::table('revenue_days')->insert(['brand_id' => $brand->id, 'day' => $day, 'channel' => 'organic_search', 'source' => 'ga4', 'sessions' => $s, 'orders' => $o, 'revenue_cents' => $c, 'created_at' => $now, 'updated_at' => $now]);
        }
        Cache::forever('revenue:last-sync', now()->timestamp);

        $html = $this->actingAs($this->admin)->get('/sales?range=last_month&compare=previous')->assertOk()->getContent();
        $this->assertStringContainsString('data-range-picker', $html);
        $this->assertMatchesRegularExpression('#Revenue</span><span class="tv">\$1,100</span>#', $html);
        $this->assertStringContainsString('▲ 175.0%<span class="delta-w"> vs previous period</span>', $html, '$1,100 against August’s $400');
        $this->assertStringContainsString('$400 then', $html);
        $this->assertStringContainsString('compared with Aug 1 – Aug 31, 2026', $html);
        $this->assertStringContainsString('data-trend=', $html);
        $this->assertStringNotContainsString('This month’s goal', $html, 'goals only show for month to date');

        // Same dates last year, and orders in the chart.
        $html = $this->actingAs($this->admin)->get('/sales?range=custom&from=2026-09-01&to=2026-09-15&compare=yoy&metric=orders')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#Orders</span><span class="tv">5</span>#', $html);
        $this->assertStringContainsString('▲ 66.7%<span class="delta-w"> vs last year</span>', $html);
        $this->assertStringContainsString('aria-pressed="true">Orders</a>', $html);

        // The assistant reads the same ranges.
        $out = (new \App\Support\Assistant\Tools($this->admin))->call('revenue', ['from' => '2026-09-01', 'to' => '2026-09-30', 'compare' => 'previous']);
        $this->assertSame(['2026-09-01', '2026-09-30', 'Aug 1 – Aug 31, 2026'], [$out['from'], $out['to'], $out['compared_with']]);
        $this->assertSame(['$1,100', '+175.0'], [$out['total']['revenue'], $out['total']['change']['cents']]);

        // No comparison: no deltas at all.
        $html = $this->actingAs($this->admin)->get('/sales?range=last_month&compare=none')->assertOk()->getContent();
        $this->assertStringNotContainsString('delta-w', $html);
    }

    public function test_search_console_loads_16_months_then_keeps_recent_days_fresh(): void
    {
        config(['search_console.sites' => ['39dg' => 'sc-domain:39dollarglasses.com']]);
        SearchConsole::forget();
        $dateQueries = [];
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use (&$dateQueries) {
            if ($r->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'ya29.sc', 'expires_in' => 3600]);
            }
            if ($r['dimensions'] === ['date']) {
                $dateQueries[] = [$r['startDate'], $r['endDate']];
                $rows = [];
                for ($d = Carbon::parse($r['startDate']); $d->lte(Carbon::parse($r['endDate'])); $d->addDay()) {
                    $rows[] = ['keys' => [$d->format('Y-m-d')], 'clicks' => 10, 'impressions' => 200, 'position' => 6.0];
                }

                return Http::response(['rows' => $rows]);
            }

            return Http::response(['rows' => [['keys' => [str_contains($r['startDate'], '2026-0') ? 'old search' : 'new search', 'https://x.test/p'], 'clicks' => 5, 'impressions' => 50, 'position' => 4.0]]]);
        });

        $this->assertTrue(SearchConsole::refresh());
        $brand = Brand::where('slug', '39dg')->first();
        // Data ends three days back (Oct 13); 16 months before that.
        $this->assertSame('2025-06-14', SearchConsole::firstDay($brand->id)->format('Y-m-d'));
        $this->assertContains(['2025-06-14', '2026-10-13'], $dateQueries);

        $dateQueries = [];
        $this->assertTrue(SearchConsole::refresh());
        $this->assertContains(['2026-09-30', '2026-10-13'], $dateQueries, 'later refreshes re-read the last two weeks');
        $this->assertSame(487, DB::table('search_days')->where('brand_id', $brand->id)->count());

        // Any range: totals and the chart from stored days; searches and pages looked up for those dates.
        $html = $this->actingAs($this->admin)->get('/seo?range=custom&from=2026-01-01&to=2026-01-31&compare=yoy')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#Clicks</span><span class="tv">310</span>#', $html);
        $this->assertStringContainsString('▲', $html);
        $this->assertStringContainsString('compared with Jan 1 – Jan 31, 2025', $html);
        $this->assertStringContainsString('Jan 1 – Jan 31, 2026 · not counting searches for our name', $html);
        Http::assertSent(fn (Request $r) => ($r['dimensions'] ?? null) === ['query'] && $r['startDate'] === '2026-01-01' && $r['endDate'] === '2026-01-31');
        $this->assertTrue(SearchConsole::forRange('39dg', $this->range(['range' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-31'])) !== null);
    }

    /** Klaviyo: the metric list, then daily aggregates (grouped by flow, channel or not at all). */
    private function fakeKlaviyo(): void
    {
        config(['klaviyo.key' => 'pk_test', 'klaviyo.order_metric_id' => 'ORD']);
        Http::preventStrayRequests();
        Http::fake(function (Request $r) {
            if (str_ends_with($r->url(), '/metrics/') || str_contains($r->url(), '/metrics/?')) {
                return Http::response(['data' => [
                    ['id' => 'RCV', 'attributes' => ['name' => 'Received Email', 'integration' => ['name' => 'Klaviyo']]],
                    ['id' => 'OPN', 'attributes' => ['name' => 'Opened Email', 'integration' => ['name' => 'Klaviyo']]],
                    ['id' => 'CLK', 'attributes' => ['name' => 'Clicked Email', 'integration' => ['name' => 'Klaviyo']]],
                    ['id' => 'UNS', 'attributes' => ['name' => 'Unsubscribed', 'integration' => ['name' => 'Klaviyo']]],
                    ['id' => 'SUB', 'attributes' => ['name' => 'Subscribed to List', 'integration' => ['name' => 'Klaviyo']]],
                ], 'links' => ['next' => null]]);
            }
            if (str_ends_with($r->url(), '/metric-aggregates/')) {
                $a = $r['data']['attributes'];
                $from = substr(str_replace('greater-or-equal(datetime,', '', $a['filter'][0]), 0, 10);
                // Only the recent window has activity: Oct 14 and Oct 15.
                if ($from !== '2026-10-03') {
                    return Http::response(['data' => ['attributes' => ['dates' => [], 'data' => []]]]);
                }
                $dates = ['2026-10-14T00:00:00-04:00', '2026-10-15T00:00:00-04:00'];
                $by = $a['by'][0] ?? null;
                $m = $a['metric_id'];
                $series = fn (array $dims, array $values) => ['dimensions' => $dims, 'measurements' => $values];
                $data = match (true) {
                    $m === 'ORD' && $by === null => [$series([], ['count' => [10, 10], 'sum_value' => [500, 500]])],
                    $m === 'ORD' && $by === '$attributed_channel' => [$series([''], ['count' => [6, 6], 'sum_value' => [300, 300]]), $series(['email'], ['count' => [4, 3], 'sum_value' => [200, 150]])],
                    $m === 'ORD' => [$series([''], ['count' => [9, 9], 'sum_value' => [1, 1]]), $series(['FlowA'], ['count' => [1, 2], 'sum_value' => [40, 90]])],
                    $by === '$flow' => [$series([''], [$a['measurements'][0] => [$m === 'RCV' ? 1000 : ($m === 'OPN' ? 400 : ($m === 'CLK' ? 30 : 2)), 0]]), $series(['FlowA'], [$a['measurements'][0] => [0, $m === 'RCV' ? 200 : ($m === 'OPN' ? 100 : ($m === 'CLK' ? 20 : 0))]])],
                    default => [$series([], ['count' => [15, 5]])],
                };

                return Http::response(['data' => ['attributes' => ['dates' => $dates, 'data' => $data]]]);
            }

            return Http::response(['errors' => [['detail' => 'not faked']]], 404);
        });
    }

    public function test_klaviyo_history_splits_campaigns_and_flows_and_loads_years_back(): void
    {
        $this->fakeKlaviyo();
        $this->assertTrue(EmailHistory::refresh());
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/metric-aggregates/') && $r['data']['attributes']['interval'] === 'day' && ($r['data']['attributes']['by'] ?? null) === ['$flow'] && $r['data']['attributes']['measurements'] === ['unique'] && $r['data']['attributes']['metric_id'] === 'OPN');
        $this->assertSame('2025-10-08', EmailHistory::status()['history_from'], 'one year back per refresh');

        $t = EmailHistory::totals(EmailHistory::days($this->d('2026-10-14'), $this->d('2026-10-15')));
        $this->assertSame([1000, 200, 1200], [$t['campaign']['received'], $t['flow']['received'], $t['email']['received']]);
        $this->assertSame(500 / 1200, $t['email']['open_rate']);
        // Orders: 7 credited to email; 3 of those to a flow, so 4 to campaigns.
        $this->assertSame([4, 3, 7], [$t['campaign']['orders'], $t['flow']['orders'], $t['email']['orders']]);
        $this->assertSame([22000, 13000], [$t['campaign']['revenue_cents'], $t['flow']['revenue_cents']]);
        $this->assertSame([20, 20, 100000], [$t['subscribed'], $t['all_orders'], $t['all_revenue_cents']]);
        $this->assertSame(2, $t['campaign']['unsubscribed']);

        $this->assertTrue(EmailHistory::refresh(true));
        $this->assertSame('2021-10-17', EmailHistory::status()['history_from'], 'five years back');
        $this->assertSame(1200, EmailHistory::totals(EmailHistory::days($this->d('2026-10-14'), $this->d('2026-10-15')))['email']['received'], 'refreshes replace days instead of adding');
    }

    public function test_email_page_shows_the_range_section(): void
    {
        $this->fakeKlaviyo();
        EmailHistory::refresh();
        // A 30-day report so the page renders.
        Cache::forever('klaviyo:report', ['at' => now()->timestamp, 'values_ok' => true, 'order_events' => 20, 'order_value_cents' => 100000, 'campaigns' => [],
            'flows' => [], 'flow_totals' => ['recipients' => 0, 'delivered' => 0, 'opens' => 0, 'clicks' => 0, 'unsubs' => 0, 'spam' => 0, 'orders' => 0, 'value_cents' => 0], 'profiles' => 5000]);
        Cache::forever('klaviyo:last', now()->timestamp);

        $html = $this->actingAs($this->admin)->get('/email?range=last7&compare=previous&metric=revenue')->assertOk()->getContent();
        $this->assertStringContainsString('Over any dates', $html);
        $this->assertMatchesRegularExpression('#Emails received</span><span class="tv">1,200</span>#', $html);
        $this->assertMatchesRegularExpression('#Orders from email &amp; SMS</span><span class="tv">7</span>#', $html);
        $this->assertMatchesRegularExpression('#Revenue from email &amp; SMS</span><span class="tv">\$350</span>#', $html);
        $this->assertStringContainsString('— vs previous period', $html, 'no history for the week before');
        $this->assertStringContainsString('Of 20 order events', $html);
        $this->assertStringContainsString('aria-pressed="true">Revenue from email &amp; SMS</a>', $html);
        $this->assertStringContainsString('Last 30 days in detail', $html);
    }

    public function test_affiliate_scoreboard_sums_a_range_of_months_against_last_year(): void
    {
        config(['affiliates.cj.token' => 'cj', 'affiliates.cj.advertiser_id' => '1']);
        Cache::forever('affiliates:cj:last', now()->timestamp);
        $now = now();
        foreach ([['2026-07-01', 1000], ['2026-08-01', 2000], ['2026-09-01', 3000], ['2025-07-01', 1500], ['2025-09-01', 1500]] as [$m, $sale]) {
            DB::table('affiliate_months')->insert(['network' => 'cj', 'month' => $m, 'publisher_id' => 'p1', 'publisher_name' => 'Pub One', 'orders' => 2, 'sale_cents' => $sale * 100,
                'commission_cents' => $sale * 10, 'pending_cents' => 0, 'created_at' => $now, 'updated_at' => $now]);
        }

        $html = $this->actingAs($this->admin)->get('/affiliates?range=last_quarter&compare=yoy')->assertOk()->getContent();
        $this->assertStringContainsString('showing <b>Jul 2026 – Sep 2026</b> against <b>Jul 2025 – Sep 2025</b>', $html);
        $this->assertMatchesRegularExpression('#Affiliate sales</span><span class="tv">\$6,000</span>#', $html);
        $this->assertStringContainsString('▲ 100.0%<span class="delta-w"> vs last year</span>', $html);
        $this->assertStringContainsString('$3,000 then', $html);
        $this->assertStringContainsString('data-trend=', $html);

        // Older one-month links still open that month, against the month before.
        $html = $this->actingAs($this->admin)->get('/affiliates?month=2026-08')->assertOk()->getContent();
        $this->assertStringContainsString('showing <b>August 2026</b> against <b>July 2026</b>', $html);
        $this->assertMatchesRegularExpression('#Affiliate sales</span><span class="tv">\$2,000</span>#', $html);
    }
}
