<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Seo\SearchConsole;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchConsoleTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        // Data runs 3 days behind: now = Sep 16 – Oct 13, before = Aug 19 – Sep 15.
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $pk = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pk, $pem);
        config([
            'revenue.ga4.credentials' => json_encode(['client_email' => 'sa@proj.iam.gserviceaccount.com', 'private_key' => $pem]),
            'search_console.sites' => ['39dg' => 'sc-domain:39dollarglasses.com'],
        ]);
        SearchConsole::forget();
        Cache::forget('revenue:ga4:token');
        $this->kay = User::factory()->create(['email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function row(string $key, int $clicks, int $impr, float $pos): array
    {
        return ['keys' => [$key], 'clicks' => $clicks, 'impressions' => $impr, 'position' => $pos];
    }

    private function fake(int $status = 200): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use ($status) {
            if ($r->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'ya29.sc', 'expires_in' => 3600]);
            }
            if (! str_contains($r->url(), 'searchAnalytics/query')) {
                return null;
            }
            if ($status !== 200) {
                return Http::response(['error' => ['message' => 'no']], $status);
            }
            $dim = $r['dimensions'][0];
            $now = $r['startDate'] === '2026-09-16';
            if ($dim === 'date') {
                $rows = [];
                for ($d = Carbon::parse('2026-08-19'); $d->lte(Carbon::parse('2026-10-13')); $d->addDay()) {
                    $recent = $d->gte(Carbon::parse('2026-09-16'));
                    $rows[] = $this->row($d->format('Y-m-d'), $recent ? 110 : 100, 4000, $recent ? 8.0 : 9.0);
                }

                return Http::response(['rows' => $rows]);
            }
            if ($dim === 'query') {
                return Http::response(['rows' => $now
                    ? [$this->row('39dollarglasses', 900, 2000, 1.1), $this->row('cheap progressive glasses', 120, 9000, 6.2), $this->row('blue light glasses <b>online</b>', 40, 5000, 14.0)]
                    : [$this->row('39dollarglasses', 800, 1900, 1.1), $this->row('cheap progressive glasses', 60, 8000, 9.1), $this->row('blue light glasses <b>online</b>', 140, 6000, 7.5)]]);
            }

            return Http::response(['rows' => $now
                ? [$this->row('https://www.39dollarglasses.com/progressive-lenses', 300, 9000, 5.0), $this->row('https://www.39dollarglasses.com/blue-light', 50, 4000, 13.0)]
                : [$this->row('https://www.39dollarglasses.com/progressive-lenses', 200, 8000, 7.0), $this->row('https://www.39dollarglasses.com/blue-light', 180, 6000, 7.0)]]);
        });
    }

    public function test_summary_compares_windows_splits_branded_and_finds_movers(): void
    {
        $this->fake();
        $this->assertTrue(SearchConsole::refresh());
        Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/token' && str_contains(base64_decode(strtr(explode('.', $r['assertion'])[1], '-_', '+/')), 'webmasters.readonly'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'sites/sc-domain%3A39dollarglasses.com/searchAnalytics/query') && $r->hasHeader('Authorization', 'Bearer ya29.sc'));

        $s = SearchConsole::summary('39dg');
        $this->assertSame(28 * 110, $s['now']['clicks']);
        $this->assertSame(28 * 100, $s['before']['clicks']);
        $this->assertSame(8.0, $s['now']['position']);
        $this->assertSame(['now' => 900, 'before' => 800, 'has_terms' => true], $s['branded']);
        $this->assertSame('blue light glasses <b>online</b>', $s['queries']['down'][0]['key']);
        $this->assertSame(-100, $s['queries']['down'][0]['change']);
        $this->assertSame('cheap progressive glasses', $s['queries']['up'][0]['key']);
        $this->assertNotContains('39dollarglasses', array_column($s['queries']['up'], 'key'), 'branded searches are left out of movers');
        $this->assertSame('https://www.39dollarglasses.com/blue-light', $s['pages']['down'][0]['key']);
    }

    public function test_page_shows_tiles_movers_and_task_links_to_everyone(): void
    {
        $this->fake();
        SearchConsole::refresh();
        Cache::forever('seo:last', now()->timestamp);

        $html = $this->actingAs($this->kay)->get('/seo')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#Clicks</span><span class="tv">3,080</span>\s*<span class="delta up">▲ 10.0%#', $html);
        $this->assertStringContainsString('▲ up 1.0', $html);
        $this->assertStringContainsString('Branded searches', $html);
        $this->assertStringContainsString('blue light glasses &lt;b&gt;online&lt;/b&gt;', $html);
        $this->assertStringContainsString('/blue-light', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*/work/new\?[^"]*title=Win(\+|%20)back#', $html);

        // The task link fills the new-task form.
        $this->actingAs($this->kay)->get('/work/new?type=task&title=Fix+falling+page+%2Fblue-light&details=Clicks+180')
            ->assertSee('value="Fix falling page /blue-light"', false)->assertSee('Clicks 180</textarea>', false);
    }

    public function test_failures_and_setup(): void
    {
        $this->fake(403);
        $this->assertFalse(SearchConsole::refresh());
        $admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($admin)->get('/seo')->assertSee('Restricted user');

        config(['search_console.sites' => []]);
        $this->actingAs($admin)->get('/seo')->assertSee('Connect Google Search Console')->assertSee('SEARCH_CONSOLE_SITES');
    }
}
