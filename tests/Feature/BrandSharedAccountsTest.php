<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandSharedAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    private function brand(string $slug): Brand
    {
        return Brand::where('slug', $slug)->firstOrFail();
    }

    public function test_lumeo_and_lens_replacement_use_39dollarglasses_accounts(): void
    {
        $dg = $this->brand('39dg');
        $this->assertSame($dg->id, $this->brand('lumeo')->accounts_from_id);
        $this->assertSame($dg->id, $this->brand('lensreplacement')->accounts_from_id);
        $this->assertNull($this->brand('trailview')->accounts_from_id);
        $this->assertNull($this->brand('ocusafe')->accounts_from_id);
    }

    public function test_shared_hub_shows_the_source_accounts_without_gaps(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $this->actingAs($admin)->get('/brands/lumeo')->assertOk()
            ->assertSee('uses the')
            ->assertSee('https://www.instagram.com/39dollarglasses/', false)
            ->assertSee('39dollarglassesVideo')
            ->assertDontSee('Not set up yet')
            ->assertDontSee('+ Add account');

        // The 39DollarGlasses hub itself is unchanged.
        $this->actingAs($admin)->get('/brands/39dg')->assertOk()->assertSee('+ Add account')->assertDontSee('uses the');
    }

    public function test_grid_rows_for_shared_brands_link_to_the_source_accounts(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $html = $this->actingAs($admin)->get('/')->assertOk()->assertSee('uses 39DollarGlasses')->getContent();

        preg_match('#<tr data-brand-row="lumeo".*?</tr>#s', $html, $row);
        $this->assertStringContainsString('https://www.instagram.com/39dollarglasses/', $row[0]);
        $this->assertStringNotContainsString('cell-add', $row[0]);
    }

    public function test_admin_can_change_sharing_on_the_brand_form(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $trail = $this->brand('trailview');
        $dg = $this->brand('39dg');
        $base = ['name' => $trail->name, 'site' => $trail->site, 'sort' => $trail->sort];

        $this->actingAs($admin)->get("/brands/{$trail->id}/edit")->assertOk()->assertSee('Same as 39DollarGlasses');
        $this->actingAs($admin)->put("/brands/{$trail->id}", $base + ['accounts_from_id' => $dg->id])->assertRedirect();
        $this->assertSame($dg->id, $trail->fresh()->accounts_from_id);

        $this->actingAs($admin)->put("/brands/{$trail->id}", $base + ['accounts_from_id' => ''])->assertRedirect();
        $this->assertNull($trail->fresh()->accounts_from_id);
    }

    public function test_sharing_is_one_level_only(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $dg = $this->brand('39dg');
        $lumeo = $this->brand('lumeo');
        $trail = $this->brand('trailview');

        // Can't point at a brand that itself shares, at itself, or make a source brand share.
        $this->actingAs($admin)->put("/brands/{$trail->id}", ['name' => 'Trailview', 'accounts_from_id' => $lumeo->id])->assertSessionHasErrors('accounts_from_id');
        $this->actingAs($admin)->put("/brands/{$trail->id}", ['name' => 'Trailview', 'accounts_from_id' => $trail->id])->assertSessionHasErrors('accounts_from_id');
        $this->actingAs($admin)->put("/brands/{$dg->id}", ['name' => $dg->name, 'accounts_from_id' => $this->brand('ocusafe')->id])->assertSessionHasErrors('accounts_from_id');
    }

    public function test_deleting_the_source_brand_unlinks_shared_brands(): void
    {
        $this->brand('39dg')->delete();
        $this->assertNull($this->brand('lumeo')->accounts_from_id);
    }
}
