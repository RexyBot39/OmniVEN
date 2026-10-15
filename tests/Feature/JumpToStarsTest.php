<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Favorite;
use App\Models\Tool;
use App\Models\User;
use App\Support\Launchpad;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JumpToStarsTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->me = User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function star(string $type, int $id, ?User $u = null)
    {
        return $this->actingAs($u ?? $this->me)->postJson('/launchpad/favorite', ['type' => $type, 'id' => $id]);
    }

    private function launcher(?User $u = null)
    {
        return collect($this->actingAs($u ?? $this->me)->getJson('/launcher.json')->assertOk()->json());
    }

    public function test_pages_and_categories_have_stars(): void
    {
        $cat = Launchpad::categoryId('Affiliate');
        $this->actingAs($this->me)->get('/')->assertOk()
            ->assertSee('data-star="page:1"', false)   // Routines
            ->assertSee('data-star="page:2"', false)   // Tasks
            ->assertSee('data-star="page:6"', false)   // Tools
            ->assertSee('data-star="category:'.$cat.'"', false)
            ->assertSee('data-cat="affiliate"', false);
        $this->actingAs($this->me)->get('/calendar')->assertOk()->assertSee('data-star="page:8"', false);
        $this->actingAs($this->me)->get('/help')->assertOk()->assertSee('data-star="page:9"', false);
    }

    public function test_starred_page_and_category_lead_jump_to_and_the_launchpad(): void
    {
        $cat = Launchpad::categoryId('Affiliate');
        $this->star('page', 8)->assertOk()->assertJson(['starred' => true]);
        $this->star('category', $cat)->assertOk()->assertJson(['starred' => true]);

        $items = $this->launcher();
        $calendar = $items->firstWhere('key', 'page:8');
        $affiliate = $items->firstWhere('key', 'category:'.$cat);
        $this->assertSame([0, 1], [$calendar['f'], $affiliate['f']]);
        $this->assertSame(route('dashboard', ['cat' => 'affiliate']).'#tools', $affiliate['u']);

        $this->actingAs($this->me)->get('/')->assertOk()
            ->assertSeeInOrder(['My launchpad', 'Action calendar', 'Affiliate']);
    }

    public function test_the_order_can_be_changed(): void
    {
        $tool = Tool::orderBy('id')->first();
        $brand = Brand::first();
        $this->star('tool', $tool->id);
        $this->star('brand', $brand->id);
        $this->star('page', 2);

        $this->actingAs($this->me)->postJson('/launchpad/order', ['keys' => ['page:2', "brand:{$brand->id}", "tool:{$tool->id}"]])
            ->assertOk()->assertJsonPath('html', fn ($h) => str_contains($h, 'arranging') && str_contains($h, 'lp-mv'));

        $this->assertSame(['page:2', "brand:{$brand->id}", "tool:{$tool->id}"], Launchpad::forUser($this->me)['favorites']->pluck('key')->all());
        $items = $this->launcher();
        $this->assertSame(0, $items->firstWhere('key', 'page:2')['f']);
        $this->assertSame(2, $items->firstWhere('key', "tool:{$tool->id}")['f']);

        // Leaving arrange mode returns the normal strip; new stars go to the end.
        $this->actingAs($this->me)->postJson('/launchpad/order', ['keys' => ['page:2'], 'arranging' => false])
            ->assertJsonPath('html', fn ($h) => ! str_contains($h, 'lp-mv') && str_contains($h, 'Arrange'));
        $this->star('page', 9);
        $this->assertSame('page:9', Launchpad::forUser($this->me)['favorites']->last()['key']);
    }

    public function test_you_can_only_order_your_own_stars(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $this->star('page', 1, $other);
        $this->star('page', 2);
        $this->actingAs($this->me)->postJson('/launchpad/order', ['keys' => ['page:1', 'page:2']])->assertOk();
        $this->assertSame(['page:2'], Launchpad::forUser($this->me)['favorites']->pluck('key')->all());
        $this->assertSame(1, Favorite::where('user_id', $other->id)->count());
        $this->actingAs($this->me)->postJson('/launchpad/order', ['keys' => ['nope']])->assertStatus(422);
    }

    public function test_admin_pages_stay_admin_only(): void
    {
        $this->star('page', 12)->assertNotFound();          // People
        $this->star('page', 99)->assertNotFound();
        $this->star('category', 123)->assertNotFound();
        $this->assertNull($this->launcher()->firstWhere('key', 'page:12'));

        $admin = User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        $this->star('page', 12, $admin)->assertOk()->assertJson(['starred' => true]);
        $this->assertSame(0, $this->launcher($admin)->firstWhere('key', 'page:12')['f']);
        // If they stop being an admin, the star quietly drops out of their list.
        $admin->update(['is_admin' => false]);
        $this->assertSame([], Launchpad::forUser($admin->fresh())['favorites']->pluck('key')->all());
    }

    public function test_star_works_without_javascript_on_a_page(): void
    {
        $this->actingAs($this->me)->from('/help')->post('/launchpad/favorite', ['type' => 'page', 'id' => 9])->assertRedirect('/help');
        $this->assertSame(['page:9'], Launchpad::forUser($this->me)['favorites']->pluck('key')->all());
    }
}
