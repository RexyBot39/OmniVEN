<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use App\Support\Revenue\Revenue;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RevenueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $pk = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pk, $pem);
        $this->key = json_encode(['type' => 'service_account', 'client_email' => 'omniven@proj.iam.gserviceaccount.com', 'private_key' => $pem]);
        config(['revenue.ga4.credentials' => $this->key, 'revenue.ga4.properties' => ['39dg' => '111', 'ocusafe' => '222']]);
        Revenue::forget();
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->staff = User::factory()->create(['email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function row(string $date, string $group, string $source, int $sessions, int $orders, float $revenue): array
    {
        return ['dimensionValues' => [['value' => $date], ['value' => $group], ['value' => $source]],
            'metricValues' => [['value' => (string) $sessions], ['value' => (string) $orders], ['value' => (string) $revenue]]];
    }

    private function fakeGa4(array $rowsByProperty, int $status = 200): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use ($rowsByProperty, $status) {
            if ($r->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]);
            }
            if (preg_match('#properties/(\d+):runReport$#', $r->url(), $m)) {
                if ($status !== 200) {
                    return Http::response(['error' => ['status' => 'PERMISSION_DENIED']], $status);
                }
                [$from, $to] = [$r['dateRanges'][0]['startDate'], $r['dateRanges'][0]['endDate']];
                $rows = array_values(array_filter($rowsByProperty[$m[1]] ?? [], function ($row) use ($from, $to) {
                    $d = substr($row['dimensionValues'][0]['value'], 0, 4).'-'.substr($row['dimensionValues'][0]['value'], 4, 2).'-'.substr($row['dimensionValues'][0]['value'], 6, 2);

                    return $d >= $from && $d <= $to;
                }));

                return Http::response(['rows' => $rows, 'rowCount' => count($rows)]);
            }
        });
    }

    private function sampleData(): array
    {
        return [
            '111' => [
                $this->row('20261015', 'Organic Search', 'google', 1000, 20, 1500.00),
                $this->row('20261015', 'Referral', 'chatgpt.com', 50, 3, 240.50),
                $this->row('20261015', 'Referral', 'www.awin1.com', 40, 2, 120.00),
                $this->row('20261002', 'Paid Search', 'google', 500, 10, 800.00),
                $this->row('20251010', 'Organic Search', 'google', 900, 15, 1000.00),
            ],
            '222' => [
                $this->row('20261015', 'Email', 'klaviyo', 100, 4, 400.00),
            ],
        ];
    }

    public function test_channels_put_ai_assistants_and_affiliate_networks_first(): void
    {
        $this->assertSame('ai', Revenue::channel('Referral', 'chatgpt.com'));
        $this->assertSame('ai', Revenue::channel('Referral', 'perplexity.ai'));
        $this->assertSame('affiliate', Revenue::channel('Referral', 'www.awin1.com'));
        $this->assertSame('affiliate', Revenue::channel('Referral', 'www.anrdoezrs.net'));
        $this->assertSame('organic_search', Revenue::channel('Organic Search', 'google'));
        $this->assertSame('shopping', Revenue::channel('Paid Shopping', 'google'));
        $this->assertSame('email', Revenue::channel('SMS', 'klaviyo'));
        $this->assertSame('other', Revenue::channel('Unassigned', '(not set)'));
    }

    public function test_sync_signs_in_as_the_service_account_and_stores_daily_totals_only(): void
    {
        $this->fakeGa4($this->sampleData());
        $this->assertTrue(Revenue::refresh(true));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/token' && str_contains($r->body(), 'jwt-bearer') && str_contains($r->body(), 'assertion='));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'properties/111:runReport') && $r->hasHeader('Authorization', 'Bearer ya29.test')
            && collect($r['dimensions'])->pluck('name')->all() === ['date', 'sessionDefaultChannelGroup', 'sessionSource']);

        $dg = Brand::where('slug', '39dg')->first();
        $day = fn ($d, $ch) => DB::table('revenue_days')->where('brand_id', $dg->id)->where('channel', $ch)->where('day', $d)->first();
        $this->assertSame(150000, (int) $day('2026-10-15', 'organic_search')->revenue_cents);
        $this->assertSame(24050, (int) $day('2026-10-15', 'ai')->revenue_cents);
        $this->assertSame(2, (int) $day('2026-10-15', 'affiliate')->orders);
        $this->assertSame(100000, (int) $day('2025-10-10', 'organic_search')->revenue_cents, 'last year is filled in');
        $this->assertEqualsCanonicalizing(['id', 'brand_id', 'day', 'channel', 'source', 'sessions', 'orders', 'revenue_cents', 'created_at', 'updated_at'], array_keys((array) $day('2026-10-15', 'ai')));

        // A second sync replaces the days rather than doubling them.
        Revenue::refresh();
        $this->assertSame(150000, (int) $day('2026-10-15', 'organic_search')->revenue_cents);
    }

    public function test_the_key_can_be_base64_and_a_bad_key_is_explained(): void
    {
        config(['revenue.ga4.credentials' => base64_encode($this->key)]);
        $this->assertTrue(Revenue::configured());
        config(['revenue.ga4.credentials' => 'not a key']);
        $this->assertFalse(Revenue::configured());
    }

    public function test_dashboard_strip_shows_yesterday_month_and_brands_to_revenue_viewers_only(): void
    {
        $this->fakeGa4($this->sampleData());
        Revenue::refresh(true);

        $html = $this->actingAs($this->admin)->get('/')->assertOk()->getContent();
        $strip = substr($html, strpos($html, 'class="rev wrap"'), 3000);
        $this->assertStringContainsString('$2,261', $strip); // yesterday, both brands
        $this->assertStringContainsString('9 orders', $strip);
        $this->assertStringContainsString('$3,061', $strip); // month to date
        $this->assertStringContainsString('▲ 206.1%', $strip); // vs Oct 1–16 last year ($1,000)
        $this->assertStringContainsString('Ocusafe <b>$400</b>', $strip);
        $this->assertStringContainsString('href="'.route('sales').'"', $html);

        $staffHtml = $this->actingAs($this->staff)->get('/')->getContent();
        $this->assertStringNotContainsString('class="rev wrap"', $staffHtml);
        $this->assertStringNotContainsString('>Sales</a>', $staffHtml);
        $this->actingAs($this->staff)->get('/sales')->assertForbidden();

        config(['revenue.viewers' => ['kay@39dollarglasses.com']]);
        $this->actingAs($this->staff)->get('/sales')->assertOk();
    }

    public function test_sales_page_breaks_revenue_down_by_channel_and_brand(): void
    {
        $this->fakeGa4($this->sampleData());
        Revenue::refresh(true);

        $html = $this->actingAs($this->admin)->get('/sales')->assertOk()->getContent();
        $this->assertStringContainsString('Revenue by channel', $html);
        $this->assertMatchesRegularExpression('#<b>Organic search</b></td>\s*<td class="n">\$1,500</td>#', $html);
        $this->assertStringContainsString('<b>AI assistants</b>', $html);
        $this->assertStringContainsString('<b>Affiliate</b>', $html);
        $this->assertStringContainsString('<b>Email &amp; SMS</b>', $html);
        $this->assertStringContainsString('By brand', $html);
        $this->assertStringContainsString('<svg class="spark"', $html);

        $one = $this->actingAs($this->admin)->get('/sales?brand=ocusafe&period=last30')->getContent();
        $this->assertMatchesRegularExpression('#aria-current="true"\s*><span class="dot"[^>]*></span>Ocusafe#', $one);
        $this->assertStringNotContainsString('<b>Organic search</b>', $one);
        $this->assertStringNotContainsString('By brand', $one);
    }

    public function test_failures_are_explained_to_admins_and_old_numbers_are_labelled(): void
    {
        $this->fakeGa4([], 403);
        $this->assertFalse(Revenue::refresh());
        $html = $this->actingAs($this->admin)->get('/sales')->getContent();
        $this->assertStringContainsString('The last update from GA4 failed.', $html);
        $this->assertStringContainsString('isn’t a Viewer on this exact property', $html);
        $this->assertStringContainsString('property IDs are 9 digits', $html);
        $this->assertStringContainsString('(HTTP 403)', $html);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $html);
    }

    public function test_a_switched_off_data_api_is_named_as_the_cause(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/token'
            ? Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600])
            : Http::response(['error' => ['status' => 'PERMISSION_DENIED', 'message' => 'Google Analytics Data API has not been used in project 123 before or it is disabled.',
                'details' => [['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => 'SERVICE_DISABLED']]]], 403));
        $this->assertFalse(Revenue::refresh());
        $this->assertStringContainsString('Google Analytics Data API is turned off', $this->actingAs($this->admin)->get('/sales')->getContent());
    }

    public function test_not_connected_shows_setup_steps_and_no_strip(): void
    {
        config(['revenue.ga4.credentials' => null]);
        Http::preventStrayRequests();
        $this->actingAs($this->admin)->get('/sales')->assertOk()->assertSee('Connect Google Analytics 4')->assertSee('GA4_PROPERTIES');
        $this->assertStringNotContainsString('class="rev wrap"', $this->actingAs($this->admin)->get('/')->getContent());
    }

    public function test_page_views_refresh_only_when_stale(): void
    {
        Http::preventStrayRequests();
        Cache::forever('revenue:last-sync', now()->timestamp);
        $this->actingAs($this->admin)->get('/')->assertOk();
        Http::assertNothingSent();
    }
}
