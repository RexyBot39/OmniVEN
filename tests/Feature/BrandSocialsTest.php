<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\User;
use Database\Seeders\BrandSocialsSeeder;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandSocialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_seed_adds_the_footer_profiles_as_accounts(): void
    {
        $this->seed(OmniVenSeeder::class);

        $this->assertSame(11, Account::whereNotIn('platform', ['linkedin', 'shopper_approved'])->count());
        $ocusafe = Brand::where('slug', 'ocusafe')->first();
        $ig = Account::where(['brand_id' => $ocusafe->id, 'platform' => 'instagram'])->firstOrFail();
        $this->assertSame(['@ocusafeglasses', 'https://www.instagram.com/ocusafeglasses'], [$ig->handle, $ig->url]);
        $this->assertNull($ig->verified_on);

        $sleep = Brand::where('slug', 'ocusleep')->first();
        $this->assertEqualsCanonicalizing(['facebook', 'x', 'instagram', 'linkedin'], $sleep->accounts()->pluck('platform')->all());
        $this->assertCount(4, $sleep->socials);

        // Brands with no profiles of their own get none.
        $this->assertSame(0, Brand::where('slug', 'lumeo')->first()->accounts()->count());

        $trail = Brand::where('slug', 'trailview')->first();
        $this->assertSame('https://trailview.co', $trail->site);
        $this->assertSame(['instagram'], $trail->accounts()->pluck('platform')->all()); // no LinkedIn page
        $this->assertSame('https://www.instagram.com/trailview.co/', $trail->accounts()->first()->url);

        $lumeo = Brand::where('slug', 'lumeo')->first();
        $this->assertSame('https://www.39dollarglasses.com', $lumeo->site);
        $this->assertStringContainsString('39DollarGlasses', $lumeo->notes);
        $this->assertSame('https://www.39dollarglasses.com/help/re-lens/your-old-frame', Brand::where('slug', 'lensreplacement')->value('site'));

        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/brands/39dg')->assertOk()->assertSee('@39dollarglasses')->assertSee('39dollarglassesVideo');
    }

    public function test_running_again_keeps_admin_edits_and_adds_no_duplicates(): void
    {
        $this->seed(OmniVenSeeder::class);
        $brand = Brand::where('slug', '39dg')->first();
        $fb = Account::where(['brand_id' => $brand->id, 'platform' => 'facebook'])->first();
        $fb->update(['handle' => 'Edited by admin', 'login' => 'social@39dollarglasses.com']);
        $brand->update(['socials' => [['label' => 'Custom', 'url' => 'https://example.com/custom']]]);

        $lumeo = Brand::where('slug', 'lumeo')->first();
        $lumeo->update(['site' => 'https://lumeo.example.com', 'notes' => 'Admin note']);

        (new BrandSocialsSeeder)->run();
        (new \Database\Seeders\BrandDetailsSeeder)->run();

        $this->assertSame(11, Account::whereNotIn('platform', ['linkedin', 'shopper_approved'])->count());
        $this->assertSame(['https://lumeo.example.com', 'Admin note'], [$lumeo->fresh()->site, $lumeo->fresh()->notes]);
        $this->assertSame('Edited by admin', $fb->fresh()->handle);
        $this->assertSame('Custom', $brand->fresh()->socials[0]['label']);
    }

    public function test_seeder_skips_missing_brands(): void
    {
        (new BrandSocialsSeeder)->run();
        $this->assertSame(0, Account::count());
    }
}
