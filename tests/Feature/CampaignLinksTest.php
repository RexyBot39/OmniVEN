<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\UtmLink;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignLinksTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->kay = User::factory()->create(['name' => 'Kaylie Ross', 'email' => 'kaylie@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    public function test_anyone_can_make_a_tagged_link_from_a_preset(): void
    {
        $this->actingAs($this->kay)->get('/links')->assertOk()->assertSee('Campaign links')->assertSee('Klaviyo email');
        $brand = Brand::where('slug', 'ocusafe')->first();

        $this->actingAs($this->kay)->post('/links', [
            'brand_id' => $brand->id, 'base_url' => 'https://ocusafe.com/collections/safety?ref=nav&utm_source=old#top',
            'preset' => 'instagram', 'campaign' => '  Fall Sale 2026 ', 'content' => 'Reel: Blue Light!',
        ])->assertRedirect(route('links.index'));

        $link = UtmLink::firstOrFail();
        $this->assertSame('https://ocusafe.com/collections/safety?ref=nav&utm_source=instagram&utm_medium=social&utm_campaign=fall-sale-2026&utm_content=reel-blue-light#top', $link->url);
        $this->assertSame($this->kay->id, $link->user_id);
        $this->assertSame('Organic social', $link->channelLabel());

        $page = $this->actingAs($this->kay)->get('/links')->getContent();
        $this->assertStringContainsString('Your link is ready.', $page);
        $this->assertStringContainsString('value="'.e($link->url).'"', $page);
        $this->assertStringContainsString('count as <b>Organic social</b>', $page);
        $this->assertStringContainsString('<option value="fall-sale-2026">', $page); // reuse the name next time
    }

    public function test_the_same_link_twice_is_one_row_and_presets_fix_source_and_medium(): void
    {
        $data = ['base_url' => 'https://www.39dollarglasses.com/', 'preset' => 'email', 'campaign' => 'black-friday', 'source' => 'ignored', 'medium' => 'ignored'];
        $this->actingAs($this->kay)->post('/links', $data);
        $this->actingAs($this->kay)->post('/links', $data);
        $this->assertSame(1, UtmLink::count());
        $this->assertStringContainsString('utm_source=klaviyo&utm_medium=email&utm_campaign=black-friday', UtmLink::first()->url);
    }

    public function test_custom_and_partner_links_need_their_own_source(): void
    {
        $this->actingAs($this->kay)->post('/links', ['base_url' => 'https://www.39dollarglasses.com/', 'preset' => 'partner', 'campaign' => 'press'])
            ->assertSessionHasErrors('source');
        $this->actingAs($this->kay)->post('/links', ['base_url' => 'https://www.39dollarglasses.com/', 'preset' => 'partner', 'campaign' => 'press', 'source' => 'Eyewear Blog'])
            ->assertSessionHasNoErrors();
        $this->assertStringContainsString('utm_source=eyewear-blog&utm_medium=referral', UtmLink::first()->url);

        $this->actingAs($this->kay)->post('/links', ['base_url' => 'javascript:alert(1)', 'preset' => 'email', 'campaign' => 'x'])->assertSessionHasErrors('base_url');
        $this->actingAs($this->kay)->post('/links', ['base_url' => 'https://x.com', 'preset' => 'email', 'campaign' => '!!!'])->assertSessionHasErrors('campaign');
    }

    public function test_search_filter_and_who_can_remove(): void
    {
        $other = User::factory()->create(['email' => 'chanley@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($this->kay)->post('/links', ['base_url' => 'https://ocusafe.com/', 'preset' => 'cj', 'campaign' => 'safety-week']);
        $this->actingAs($this->kay)->post('/links', ['base_url' => 'https://ocusafe.com/', 'preset' => 'tiktok', 'campaign' => 'spring']);
        $link = UtmLink::where('campaign', 'safety-week')->first();

        $found = $this->actingAs($other)->get('/links?q=safety')->getContent();
        $this->assertStringContainsString('<b>safety-week</b>', $found);
        $this->assertStringNotContainsString('<b>spring</b>', $found);

        $this->actingAs($other)->delete("/links/{$link->id}")->assertForbidden();
        $this->actingAs($this->kay)->delete("/links/{$link->id}")->assertRedirect();
        $this->assertSame(1, UtmLink::count());
    }

    public function test_make_another_like_this_fills_the_form(): void
    {
        $this->actingAs($this->kay)->post('/links', ['base_url' => 'https://ocusafe.com/z87', 'preset' => 'awin', 'campaign' => 'z87-launch', 'content' => 'banner-a']);
        $id = UtmLink::first()->id;
        $html = $this->actingAs($this->kay)->get('/links?copy='.$id)->getContent();
        $this->assertStringContainsString('value="https://ocusafe.com/z87"', $html);
        $this->assertStringContainsString('value="z87-launch"', $html);
        $this->assertMatchesRegularExpression('#<option value="awin"[^>]*selected#', $html);
    }
}
