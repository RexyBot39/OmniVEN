<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Email\Klaviyo;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailHealthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        config(['klaviyo.key' => 'pk_secret']);
        Klaviyo::forget();
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    /** Shaped like our account on Oct 17, 2026: orders are credited but carry no value. */
    private function fake(float $orderValue = 0, bool $valuesRejected = true, int $status = 200): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use ($orderValue, $valuesRejected, $status) {
            if ($status !== 200) {
                return Http::response(['errors' => [['detail' => 'nope']]], $status);
            }
            $u = $r->url();
            $wantsValue = in_array('conversion_value', $r['data']['attributes']['statistics'] ?? [], true);
            if (str_contains($u, '/campaign-values-reports/')) {
                if ($wantsValue && $valuesRejected) {
                    return Http::response(['errors' => [['detail' => 'This metric does not support value statistics.']]], 400);
                }
                $v = $wantsValue ? ['conversion_value' => 1234.5] : [];

                return Http::response(['data' => ['attributes' => ['results' => [
                    ['groupings' => ['campaign_id' => 'C1', 'send_channel' => 'email'], 'statistics' => ['recipients' => 151657, 'delivered' => 150924, 'opens_unique' => 43984, 'clicks_unique' => 896, 'unsubscribe_uniques' => 1494, 'spam_complaints' => 20, 'conversions' => 130, 'conversion_uniques' => 112] + $v],
                    ['groupings' => ['campaign_id' => 'C2', 'send_channel' => 'email'], 'statistics' => ['recipients' => 101005, 'delivered' => 100412, 'opens_unique' => 24177, 'clicks_unique' => 680, 'unsubscribe_uniques' => 148, 'spam_complaints' => 5, 'conversions' => 140, 'conversion_uniques' => 118] + $v],
                    ['groupings' => ['campaign_id' => 'T1', 'send_channel' => 'email'], 'statistics' => ['recipients' => 14, 'delivered' => 14, 'opens_unique' => 8, 'clicks_unique' => 0, 'unsubscribe_uniques' => 0, 'spam_complaints' => 0, 'conversions' => 0] + $v],
                ]]]]);
            }
            if (str_contains($u, '/flow-values-reports/')) {
                $v = $wantsValue ? ['conversion_value' => 100] : [];

                return Http::response(['data' => ['attributes' => ['results' => [
                    ['groupings' => ['flow_id' => 'F1', 'flow_message_id' => 'm1'], 'statistics' => ['recipients' => 821, 'delivered' => 819, 'opens_unique' => 341, 'clicks_unique' => 30, 'unsubscribe_uniques' => 8, 'spam_complaints' => 0, 'conversions' => 12, 'conversion_uniques' => 9] + $v],
                    ['groupings' => ['flow_id' => 'F1', 'flow_message_id' => 'm2'], 'statistics' => ['recipients' => 720, 'delivered' => 718, 'opens_unique' => 270, 'clicks_unique' => 18, 'unsubscribe_uniques' => 6, 'spam_complaints' => 0, 'conversions' => 5, 'conversion_uniques' => 4] + $v],
                ]]]]);
            }
            if (str_contains($u, '/campaigns/')) {
                return Http::response(['data' => str_contains(urldecode($u), "'email'") ? [
                    ['id' => 'C1', 'attributes' => ['name' => 'Halloween Sale 2026 - REAL SEND REMINDER', 'send_time' => '2026-10-16T14:00:00+00:00']],
                    ['id' => 'C2', 'attributes' => ['name' => 'Halloween Sale 2026 - <b>REAL</b> SEND', 'send_time' => '2026-10-12T18:00:00+00:00']],
                ] : [], 'links' => ['next' => null]]);
            }
            if (str_contains($u, '/flows/')) {
                return Http::response(['data' => [['id' => 'F1', 'attributes' => ['name' => 'Cart Abandonment v2']]], 'links' => ['next' => null]]);
            }
            if (str_contains($u, '/metric-aggregates/')) {
                return Http::response(['data' => ['attributes' => ['data' => [['measurements' => ['count' => [2000, 602], 'sum_value' => [$orderValue, 0]]]]]]]);
            }
            if (str_contains($u, '/lists/TEKDQ9/')) {
                return Http::response(['data' => ['attributes' => ['profile_count' => 160240]]]);
            }
        });
    }

    public function test_sync_reads_reports_drops_tests_and_flags_orders_without_value(): void
    {
        $this->fake();
        $this->assertTrue(Klaviyo::refresh());

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'campaign-values-reports') && $r->hasHeader('Authorization', 'Klaviyo-API-Key pk_secret')
            && $r['data']['attributes']['conversion_metric_id'] === 'RsvUas' && $r->hasHeader('revision'));
        $r = Klaviyo::report();
        $this->assertFalse($r['values_ok']);
        $this->assertCount(2, $r['campaigns'], 'the 14-person test send is left out');
        $this->assertSame('Halloween Sale 2026 - REAL SEND REMINDER', $r['campaigns'][0]['name'], 'newest first');
        $this->assertSame(13, $r['flows'][0]['orders'], 'flow messages add up per flow');
        $this->assertSame(2602, $r['order_events']);
        $this->assertSame(160240, DB::table('email_list_days')->where('list_id', 'TEKDQ9')->value('profiles'));

        $html = $this->actingAs($this->admin)->get('/email')->assertOk()->getContent();
        $this->assertStringContainsString('Klaviyo received 2,602 order events in 30 days but none carried an order value', $html);
        $this->assertMatchesRegularExpression('#Buyers from email &amp; SMS</span><span class="tv">243</span>#', $html);
        $this->assertMatchesRegularExpression('#Revenue from email &amp; SMS</span><span class="tv">—</span>#', $html);
        $this->assertStringContainsString('Halloween Sale 2026 - &lt;b&gt;REAL&lt;/b&gt; SEND', $html);
        $this->assertStringContainsString('Cart Abandonment v2', $html);
        $this->assertStringContainsString('160,240', $html);
        $this->assertStringContainsString('Unsubscribe rate', $html);
        $this->assertStringNotContainsString('pk_secret', $html);
    }

    public function test_with_order_values_revenue_shows_and_the_warning_goes(): void
    {
        $this->fake(orderValue: 250000.0, valuesRejected: false);
        Klaviyo::refresh();
        DB::table('email_list_days')->insert(['list_id' => 'TEKDQ9', 'day' => '2026-09-10', 'profiles' => 158000, 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->actingAs($this->admin)->get('/email')->getContent();
        $this->assertStringNotContainsString('none carried an order value', $html);
        $this->assertMatchesRegularExpression('#Revenue from email &amp; SMS</span><span class="tv">\$2,669</span>#', $html); // 2 × 1,234.50 + 2 × 100
        $this->assertStringContainsString('+2,240 in 30 days', $html);
        // 1,494 of 150,924 delivered unsubscribed on one send: above 0.5% overall.
        $this->assertStringContainsString('Campaign unsubscribe rate is', $html);
    }

    public function test_access_failures_and_setup(): void
    {
        $staff = User::factory()->create(['email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($staff)->get('/email')->assertForbidden();

        $this->fake(status: 401);
        $this->assertFalse(Klaviyo::refresh());
        $this->actingAs($this->admin)->get('/email')->assertSee('Check KLAVIYO_PRIVATE_KEY');

        config(['klaviyo.key' => null]);
        $this->actingAs($this->admin)->get('/email')->assertSee('Connect Klaviyo')->assertSee('Read');
        $this->assertStringContainsString('href="'.route('email').'"', $this->actingAs($this->admin)->get('/sales')->getContent());
    }
}
