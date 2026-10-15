<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\Tool;
use App\Models\User;
use App\Support\BrandIcon;
use Database\Seeders\OmniVenSeeder;
use Database\Seeders\ShopperApprovedSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopperApprovedTest extends TestCase
{
    use RefreshDatabase;

    private const DASH = 'https://www.shopperapproved.com/account/dashboard';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    public function test_tool_routine_and_account_point_at_the_dashboard(): void
    {
        $tool = Tool::where('name', 'Shopper Approved')->firstOrFail();
        $this->assertSame(self::DASH, $tool->url);
        $this->assertTrue($tool->featured);
        $this->assertStringContainsString('main third-party review platform', $tool->description);

        $r = Routine::where('title', 'Check new reviews and escalate')->firstOrFail();
        $this->assertSame(self::DASH, $r->link);
        $this->assertStringStartsWith('Shopper Approved first', $r->description);

        $acct = Account::where(['brand_id' => Brand::where('slug', '39dg')->value('id'), 'platform' => 'shopper_approved'])->firstOrFail();
        $this->assertSame(self::DASH, $acct->url);
        $this->assertSame('Shopper Approved', $acct->platformLabel());
    }

    public function test_upgrades_an_existing_install_but_keeps_admin_edits(): void
    {
        $tool = Tool::where('name', 'Shopper Approved')->first();
        $tool->update(['url' => 'https://www.shopperapproved.com', 'description' => 'Post-purchase site and product reviews; seller ratings feed.', 'featured' => false]);
        $r = Routine::where('title', 'Check new reviews and escalate')->first();
        $r->update(['description' => 'Trustpilot, ConsumerAffairs, Shopper Approved. Escalate any chargeback, BBB, legal or prescription-error mention the same day.', 'link' => 'https://business.trustpilot.com']);
        Account::where('platform', 'shopper_approved')->delete();

        (new ShopperApprovedSeeder)->run();
        $this->assertSame(self::DASH, $tool->fresh()->url);
        $this->assertTrue($tool->fresh()->featured);
        $this->assertSame(self::DASH, $r->fresh()->link);
        $this->assertSame(1, Account::where('platform', 'shopper_approved')->count());

        $tool->update(['url' => 'https://www.shopperapproved.com/custom', 'description' => 'Admin text']);
        $r->update(['description' => 'Admin routine text', 'link' => 'https://example.com']);
        (new ShopperApprovedSeeder)->run();
        $this->assertSame('https://www.shopperapproved.com/custom', $tool->fresh()->url);
        $this->assertSame('Admin text', $tool->fresh()->description);
        $this->assertSame('https://example.com', $r->fresh()->link);
        $this->assertSame(1, Account::where('platform', 'shopper_approved')->count());
    }

    public function test_logo_shows_in_platform_consoles_and_the_hub(): void
    {
        $this->assertSame('shopperapproved', BrandIcon::forUrl(self::DASH));
        $this->assertSame('shopperapproved', BrandIcon::forPlatform('shopper_approved'));
        $this->assertStringContainsString('id="bi-shopperapproved"', file_get_contents(public_path('images/brand-icons.svg')));

        $admin = User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        $html = $this->actingAs($admin)->get('/')->assertOk()->getContent();
        preg_match('#<div class="consoles">.*?</div>\s*</section>#s', $html, $consoles);
        $this->assertStringContainsString('#bi-shopperapproved', $consoles[0] ?? $html);
        $this->actingAs($admin)->get('/brands/39dg')->assertOk()->assertSee('Shopper Approved')->assertSee('#bi-shopperapproved', false);
    }
}
