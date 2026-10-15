<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use App\Support\BrandIcon;
use Database\Seeders\AffiliateSeeder;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    public function test_both_networks_are_tools_under_affiliate(): void
    {
        $cj = Tool::where('name', 'CJ Affiliate (Commission Junction)')->firstOrFail();
        $awin = Tool::where('name', 'Awin')->firstOrFail();
        $this->assertSame([AffiliateSeeder::CJ, 'Affiliate'], [$cj->url, $cj->category]);
        $this->assertSame([AffiliateSeeder::AWIN, 'Affiliate'], [$awin->url, $awin->category]);
        $this->assertStringContainsString('95501', $awin->access_note);
        $this->assertSame(['39dg'], $cj->brands->pluck('slug')->all());
        $this->assertContains('Affiliate', config('omniven.categories'));
    }

    public function test_they_show_with_logos_on_the_tools_tab_and_in_the_launcher(): void
    {
        $this->assertSame('cj', BrandIcon::forUrl(AffiliateSeeder::CJ));
        $this->assertSame('awin', BrandIcon::forUrl(AffiliateSeeder::AWIN));
        $this->assertSame(['cj', 'awin'], BrandIcon::mentions('Check Commission Junction, then Awin'));
        $sprite = file_get_contents(public_path('images/brand-icons.svg'));
        $this->assertStringContainsString('id="bi-cj"', $sprite);
        $this->assertStringContainsString('id="bi-awin"', $sprite);

        $user = User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('CJ Affiliate (Commission Junction)')
            ->assertSee(e(AffiliateSeeder::AWIN), false)
            ->assertSee('#bi-cj', false)
            ->assertSee('#bi-awin', false);

        $items = collect($this->actingAs($user)->getJson('/launcher.json')->json());
        $this->assertTrue($items->contains(fn ($i) => $i['t'] === 'Awin' && $i['u'] === AffiliateSeeder::AWIN && ($i['ic']['s'] ?? null) === 'awin'));
    }

    public function test_running_again_keeps_admin_edits(): void
    {
        Tool::where('name', 'Awin')->update(['url' => 'https://app.awin.com/custom']);
        (new AffiliateSeeder)->run();
        $this->assertSame('https://app.awin.com/custom', Tool::where('name', 'Awin')->value('url'));
        $this->assertSame(1, Tool::where('name', 'Awin')->count());
    }
}
