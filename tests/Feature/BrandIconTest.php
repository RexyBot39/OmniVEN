<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use App\Support\BrandIcon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_and_platforms_map_to_the_right_mark(): void
    {
        $this->assertSame('instagram', BrandIcon::forUrl('https://www.instagram.com/ocusafeglasses'));
        $this->assertSame('meta', BrandIcon::forUrl('https://business.facebook.com/events_manager2'));
        $this->assertSame('facebook', BrandIcon::forUrl('https://www.facebook.com/OcusafeGlasses'));
        $this->assertSame('googleads', BrandIcon::forUrl('https://ads.google.com'));
        $this->assertSame('googleanalytics', BrandIcon::forUrl('https://analytics.google.com'));
        $this->assertSame('google', BrandIcon::forUrl('https://merchants.google.com'));
        $this->assertSame('linkedin', BrandIcon::forUrl('https://www.linkedin.com/showcase/104508825/admin/dashboard/'));
        $this->assertSame('reddit', BrandIcon::forUrl('https://ads.reddit.com'));
        $this->assertSame('klaviyo', BrandIcon::forUrl('https://www.klaviyo.com/login'));
        $this->assertSame('tiktok', BrandIcon::forUrl('https://business.tiktok.com'));
        $this->assertSame('microsoft', BrandIcon::forUrl('https://clarity.microsoft.com'));
        $this->assertNull(BrandIcon::forUrl('https://www.39dollarglasses.com'));
        $this->assertNull(BrandIcon::forUrl('https://notgoogle.com'));
        $this->assertNull(BrandIcon::forUrl(null));

        $this->assertSame('googlesearchconsole', BrandIcon::forPlatform('search_console'));
        $this->assertSame('meta', BrandIcon::forPlatform('meta_ads'));
        $this->assertNull(BrandIcon::forPlatform('other'));
        $this->assertSame('klaviyo', BrandIcon::forName('Klaviyo'));
        $this->assertNull(BrandIcon::forName('Sloan AI'));
    }

    public function test_every_mapped_mark_exists_in_the_sprite_with_a_color(): void
    {
        $sprite = file_get_contents(public_path('images/brand-icons.svg'));
        foreach (config('brand_icons') as $slug => $m) {
            $this->assertStringContainsString('id="bi-'.$slug.'"', $sprite, $slug);
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $m['hex']);
        }
        $html = (string) BrandIcon::html('linkedin', 'md');
        $this->assertStringContainsString('--bi:#0A66C2', $html);
        $this->assertStringContainsString('#bi-linkedin', $html);
        $this->assertStringContainsString('aria-label="LinkedIn"', $html);
        $this->assertStringContainsString('aria-hidden="true"', (string) BrandIcon::html('reddit', 'xs'));
        $this->assertSame('', (string) BrandIcon::html('nothing'));
    }

    public function test_marks_show_on_pages_and_in_the_launcher(): void
    {
        $this->seed(OmniVenSeeder::class);
        $user = User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);

        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();
        foreach (['instagram', 'facebook', 'tiktok', 'linkedin', 'googleads', 'klaviyo', 'reddit', 'slack'] as $slug) {
            $this->assertStringContainsString('#bi-'.$slug.'"', $html, $slug);
        }
        $this->actingAs($user)->get('/brands/ocusafe')->assertOk()->assertSee('#bi-linkedin', false)->assertSee('#bi-instagram', false);

        $json = collect($this->actingAs($user)->getJson('/launcher.json')->json());
        $this->assertSame('klaviyo', $json->firstWhere('t', 'Klaviyo')['ic']['s']);
        $this->assertSame('#FF4500', $json->firstWhere('t', 'Reddit Ads')['ic']['h']);
        $this->assertNull($json->firstWhere('t', Tool::where('name', 'Sloan AI')->value('name'))['ic']);
    }
}
