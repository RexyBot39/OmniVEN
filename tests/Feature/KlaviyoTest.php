<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\Tool;
use App\Models\User;
use Database\Seeders\KlaviyoSeeder;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KlaviyoTest extends TestCase
{
    use RefreshDatabase;

    private const DASH = 'https://www.klaviyo.com/dashboard';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    private function account(): Account
    {
        return Account::where(['brand_id' => Brand::where('slug', '39dg')->value('id'), 'platform' => 'klaviyo'])->firstOrFail();
    }

    public function test_tool_routine_and_39dg_account_open_the_dashboard(): void
    {
        $this->assertSame(self::DASH, Tool::where('name', 'Klaviyo')->value('url'));
        $this->assertSame(self::DASH, Routine::where('title', 'Review email performance')->value('link'));

        $acct = $this->account();
        $this->assertSame(self::DASH, $acct->openUrl());
        $this->assertTrue($acct->hasDirectLink());
        $this->assertSame('WQR2ZT', $acct->account_ref);
    }

    public function test_it_shows_on_the_hub_grid_and_launcher(): void
    {
        $user = User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($user)->get('/brands/39dg')->assertOk()->assertSee(self::DASH, false)->assertSee('#bi-klaviyo', false);
        $this->actingAs($user)->get('/')->assertOk()->assertSee(self::DASH, false);

        $items = collect($this->actingAs($user)->getJson('/launcher.json')->assertOk()->json());
        $this->assertTrue($items->contains(fn ($i) => $i['k'] === 'Account' && $i['u'] === self::DASH && str_contains($i['t'], '39DollarGlasses · Klaviyo')));
    }

    public function test_upgrades_an_existing_install_but_keeps_admin_edits(): void
    {
        Tool::where('name', 'Klaviyo')->update(['url' => 'https://www.klaviyo.com/login']);
        Routine::where('title', 'Review email performance')->update(['link' => 'https://www.klaviyo.com/login']);
        Account::where('platform', 'klaviyo')->delete();

        (new KlaviyoSeeder)->run();
        $this->assertSame(self::DASH, Tool::where('name', 'Klaviyo')->value('url'));
        $this->assertSame(self::DASH, Routine::where('title', 'Review email performance')->value('link'));
        $this->assertSame(self::DASH, $this->account()->url);

        Tool::where('name', 'Klaviyo')->update(['url' => 'https://www.klaviyo.com/campaigns']);
        $this->account()->update(['url' => 'https://www.klaviyo.com/flows']);
        (new KlaviyoSeeder)->run();
        $this->assertSame('https://www.klaviyo.com/campaigns', Tool::where('name', 'Klaviyo')->value('url'));
        $this->assertSame('https://www.klaviyo.com/flows', $this->account()->url);
        $this->assertSame(1, Account::where('platform', 'klaviyo')->count());
    }
}
