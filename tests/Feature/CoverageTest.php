<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $kay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-15 10:00', config('app.timezone')));
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->kay = User::factory()->create(['email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function counts(): array
    {
        $html = $this->actingAs($this->kay)->get('/coverage')->assertOk()->getContent();
        preg_match('/Coverage<\/span><span class="tv">(\d+)%/', $html, $s);
        preg_match('/Gaps<\/span><span class="tv">(\d+)</', $html, $g);

        return [(int) $s[1], (int) $g[1], $html];
    }

    public function test_not_needed_marks_take_gaps_out_of_the_score(): void
    {
        Account::query()->delete();
        $brands = Brand::whereNull('accounts_from_id')->count();
        $platforms = count(config('omniven.platforms')) - 1;
        $dg = Brand::where('slug', '39dg')->first();
        Account::create(['brand_id' => $dg->id, 'platform' => 'instagram', 'url' => 'https://instagram.com/39dg', 'verified_on' => today()]);

        [$score, $gaps] = $this->counts();
        $this->assertSame($brands * $platforms - 1, $gaps);

        $this->actingAs($this->kay)->post('/coverage/skip', ['brand_id' => $dg->id, 'platform' => 'tiktok_ads'])->assertForbidden();
        $this->actingAs($this->admin)->post('/coverage/skip', ['brand_id' => $dg->id, 'platform' => 'tiktok_ads'])->assertSessionHas('status', '39DollarGlasses doesn’t need TikTok Ads.');
        [, $gaps2, $html] = $this->counts();
        $this->assertSame($gaps - 1, $gaps2);
        $this->assertStringContainsString('1 of '.($platforms - 1), $html, '39DG score');

        // Toggling again counts it once more.
        $this->actingAs($this->admin)->post('/coverage/skip', ['brand_id' => $dg->id, 'platform' => 'tiktok_ads']);
        $this->assertSame(0, DB::table('brand_platform_skips')->count());
    }

    public function test_gaps_offer_a_task_and_all_brand_accounts_cover_everyone(): void
    {
        Account::query()->delete();
        Account::create(['brand_id' => null, 'platform' => 'shopper_approved', 'url' => 'https://www.shopperapproved.com/x', 'verified_on' => today()]);
        $ocusafe = Brand::where('slug', 'ocusafe')->first();

        $html = $this->actingAs($this->kay)->get('/coverage')->getContent();
        $this->assertStringContainsString('Set%20up%20Ocusafe%20on%20TikTok', $html);
        $this->assertStringNotContainsString('Not needed for', $html, 'staff can’t mark gaps');
        $this->assertStringNotContainsString('Set%20up%20Ocusafe%20on%20Shopper%20Approved', $html, 'the all-brands account covers it');
        $this->assertStringContainsString('Not needed for Ocusafe', $this->actingAs($this->admin)->get('/coverage')->getContent());
    }

    public function test_accounts_needing_attention_are_listed(): void
    {
        Account::query()->delete();
        $dg = Brand::where('slug', '39dg')->first();
        Account::create(['brand_id' => $dg->id, 'platform' => 'facebook', 'handle' => '39DollarGlasses', 'verified_on' => today()]);
        Account::create(['brand_id' => $dg->id, 'platform' => 'klaviyo', 'url' => 'https://www.klaviyo.com/x', 'verified_on' => today()->subDays(200)]);
        Account::create(['brand_id' => $dg->id, 'platform' => 'ga4', 'url' => 'https://analytics.google.com/x', 'verified_on' => today()]);

        $html = $this->actingAs($this->admin)->get('/coverage')->getContent();
        $att = substr($html, strpos($html, 'Need attention</h2>'));
        $this->assertStringContainsString('39DollarGlasses · Facebook · 39DollarGlasses</b> <span class="sub">· No direct link', $att);
        $this->assertStringContainsString('Access last checked Mar 29, 2026', $att);
        $this->assertStringNotContainsString('Google Analytics', $att);
        $this->assertStringContainsString('Need attention</span><span class="tv">2<', $html);
    }

    public function test_dashboard_grid_links_to_coverage(): void
    {
        $this->assertStringContainsString(route('coverage'), $this->actingAs($this->admin)->get('/')->getContent());
    }
}
