<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Affiliate\Affiliates;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AffiliateScoreboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        config([
            'affiliates.cj.token' => 'cj-secret', 'affiliates.cj.advertiser_id' => '7654321',
            'affiliates.awin.token' => 'awin-secret', 'affiliates.awin.advertiser_id' => '95501',
            'affiliates.history_months' => 3,
        ]);
        Affiliates::forget();
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->staff = User::factory()->create(['email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function fake(int $cjStatus = 200): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use ($cjStatus) {
            if ($r->url() === 'https://commissions.api.cj.com/query') {
                if ($cjStatus !== 200) {
                    return Http::response('nope', $cjStatus);
                }
                $october = str_contains($r['query'], 'sincePostingDate: "2026-10-01');
                $page2 = str_contains($r['query'], 'sinceCommissionId');
                if (! $october) {
                    return Http::response(['data' => ['advertiserCommissions' => ['count' => 0, 'payloadComplete' => true, 'records' => []]]]);
                }

                return Http::response(['data' => ['advertiserCommissions' => $page2
                    ? ['count' => 1, 'payloadComplete' => true, 'maxCommissionId' => '3', 'records' => [
                        ['commissionId' => '3', 'actionStatus' => 'locked', 'publisherId' => '111', 'publisherName' => 'Glasses Deals <b>Blog</b>', 'saleAmountAdvCurrency' => '50.00', 'advCommissionAmountAdvCurrency' => '5.00'],
                    ]]
                    : ['count' => 2, 'payloadComplete' => false, 'maxCommissionId' => '2', 'records' => [
                        ['commissionId' => '1', 'actionStatus' => 'new', 'publisherId' => '111', 'publisherName' => 'Glasses Deals <b>Blog</b>', 'saleAmountAdvCurrency' => '100.00', 'advCommissionAmountAdvCurrency' => '10.00'],
                        ['commissionId' => '2', 'actionStatus' => 'closed', 'publisherId' => '222', 'publisherName' => 'Coupon Hub', 'saleAmountAdvCurrency' => '80', 'advCommissionAmountAdvCurrency' => '4'],
                    ]]]]);
            }
            if (str_starts_with($r->url(), 'https://api.awin.com/advertisers/95501/reports/publisher')) {
                return Http::response(str_contains($r->url(), 'startDate=2026-10-01') ? [
                    ['publisherId' => 900, 'publisherName' => 'Specs Review Site', 'pendingNo' => 2, 'pendingValue' => 150.5, 'pendingComm' => 12, 'confirmedNo' => 1, 'confirmedValue' => 60, 'confirmedComm' => 6],
                    ['publisherId' => 901, 'publisherName' => 'Idle Pub', 'pendingNo' => 0, 'pendingValue' => 0, 'pendingComm' => 0, 'confirmedNo' => 0, 'confirmedValue' => 0, 'confirmedComm' => 0],
                ] : [['publisherId' => 900, 'publisherName' => 'Specs Review Site', 'pendingNo' => 0, 'pendingValue' => 0, 'pendingComm' => 0, 'confirmedNo' => 4, 'confirmedValue' => 300, 'confirmedComm' => 30]]);
            }
        });
    }

    public function test_sync_pages_through_cj_and_reads_awin_per_publisher(): void
    {
        $this->fake();
        $this->assertTrue(Affiliates::refresh(true));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://commissions.api.cj.com/query' && $r->hasHeader('Authorization', 'Bearer cj-secret') && str_contains($r['query'], 'forAdvertisers: ["7654321"]'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'region=US') && $r->hasHeader('Authorization', 'Bearer awin-secret'));

        $cj = DB::table('affiliate_months')->where('network', 'cj')->where('month', '2026-10-01')->where('publisher_id', '111')->first();
        $this->assertSame(2, (int) $cj->orders);
        $this->assertSame(15000, (int) $cj->sale_cents);
        $this->assertSame(1500, (int) $cj->commission_cents);
        $this->assertSame(1000, (int) $cj->pending_cents, 'only new/extended count as pending');

        $awin = DB::table('affiliate_months')->where('network', 'awin')->where('month', '2026-10-01')->first();
        $this->assertSame(3, (int) $awin->orders);
        $this->assertSame(21050, (int) $awin->sale_cents);
        $this->assertSame(1200, (int) $awin->pending_cents);
        $this->assertSame(0, DB::table('affiliate_months')->where('publisher_id', '901')->count(), 'publishers with nothing are skipped');
        $this->assertSame(3, DB::table('affiliate_months')->where('network', 'awin')->count(), 'Oct, Sep and Aug');

        // A second refresh replaces months instead of adding to them.
        Affiliates::refresh();
        $this->assertSame(15000, (int) DB::table('affiliate_months')->where('network', 'cj')->where('publisher_id', '111')->value('sale_cents'));
    }

    public function test_scoreboard_shows_totals_networks_and_top_publishers_to_revenue_viewers(): void
    {
        $this->fake();
        Affiliates::refresh(true);
        \Illuminate\Support\Facades\Cache::forever('affiliates:cj:last', now()->timestamp);
        \Illuminate\Support\Facades\Cache::forever('affiliates:awin:last', now()->timestamp);

        $html = $this->actingAs($this->admin)->get('/affiliates')->assertOk()->getContent();
        $this->assertStringContainsString('showing <b>October 2026</b> against <b>September 2026</b>', $html);
        $this->assertMatchesRegularExpression('#Affiliate sales</span><span class="tv">\$441</span>#', $html); // 230 CJ + 210.50 Awin
        $this->assertStringContainsString('$300 then', $html);
        $this->assertMatchesRegularExpression('#Pending commission</span><span class="tv">\$22</span>#', $html);
        $this->assertStringContainsString('Glasses Deals &lt;b&gt;Blog&lt;/b&gt;', $html, 'publisher names are escaped');
        $this->assertStringContainsString('Specs Review Site', $html);
        $this->assertStringContainsString('href="'.route('affiliates').'"', $this->actingAs($this->admin)->get('/sales')->getContent());

        $this->actingAs($this->staff)->get('/affiliates')->assertForbidden();
    }

    public function test_failures_are_explained_and_not_connected_shows_setup(): void
    {
        $this->fake(401);
        $this->assertFalse(Affiliates::refresh());
        $html = $this->actingAs($this->admin)->get('/affiliates')->getContent();
        $this->assertStringContainsString('The last update from CJ failed.', $html);
        $this->assertStringContainsString('Check CJ_API_TOKEN', $html);
        $this->assertStringNotContainsString('cj-secret', $html);
        $this->assertNotNull(DB::table('affiliate_months')->where('network', 'awin')->first(), 'Awin still updates when CJ fails');

        config(['affiliates.cj.token' => null, 'affiliates.awin.token' => null]);
        $this->actingAs($this->admin)->get('/affiliates')->assertSee('Connect CJ and Awin')->assertSee('AWIN_API_TOKEN');
    }
}
