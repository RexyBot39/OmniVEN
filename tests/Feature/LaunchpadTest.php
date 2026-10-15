<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Favorite;
use App\Models\RecentOpen;
use App\Models\Tool;
use App\Models\User;
use App\Support\Launchpad;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchpadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    private function star(User $u, string $type, int $id)
    {
        return $this->actingAs($u)->postJson('/launchpad/favorite', ['type' => $type, 'id' => $id]);
    }

    public function test_starring_toggles_and_shows_on_the_dashboard_in_starred_order(): void
    {
        $me = $this->user();
        [$a, $b] = Tool::orderBy('id')->take(2)->get();
        $brand = Brand::first();

        $this->star($me, 'tool', $b->id)->assertOk()->assertJson(['starred' => true]);
        $this->star($me, 'brand', $brand->id)->assertOk()->assertJson(['starred' => true]);
        $this->star($me, 'tool', $a->id)->assertOk()->assertJsonPath('starred', true)
            ->assertJsonPath('html', fn ($html) => str_contains($html, e($a->name)));

        $favs = Launchpad::forUser($me)['favorites']->pluck('key')->all();
        $this->assertSame(["tool:{$b->id}", "brand:{$brand->id}", "tool:{$a->id}"], $favs);

        $this->actingAs($me)->get('/')->assertOk()->assertSee('My launchpad')
            ->assertSeeInOrder([e($b->name), e($brand->name), e($a->name)]);

        // Second press un-stars.
        $this->star($me, 'tool', $b->id)->assertJson(['starred' => false]);
        $this->assertSame(2, Favorite::where('user_id', $me->id)->count());
    }

    public function test_star_works_without_javascript(): void
    {
        $me = $this->user();
        $tool = Tool::first();
        $this->actingAs($me)->from('/')->post('/launchpad/favorite', ['type' => 'tool', 'id' => $tool->id])
            ->assertRedirect('/');
        $this->assertTrue(Favorite::where(['user_id' => $me->id, 'item_type' => 'tool', 'item_id' => $tool->id])->exists());
    }

    public function test_launchpads_are_private_to_each_person(): void
    {
        $me = $this->user();
        $other = $this->user();
        $tool = Tool::first();

        $this->star($other, 'tool', $tool->id)->assertOk();
        $this->actingAs($other)->postJson('/launchpad/open', ['type' => 'tool', 'id' => Tool::skip(1)->first()->id])->assertNoContent();

        $mine = Launchpad::forUser($me);
        $this->assertCount(0, $mine['favorites']);
        $this->assertCount(0, $mine['recent']);

        $json = $this->actingAs($me)->getJson('/launcher.json')->assertOk()->json();
        foreach ($json as $it) {
            $this->assertArrayNotHasKey('f', $it);
            $this->assertArrayNotHasKey('r', $it);
        }
    }

    public function test_rejects_bad_types_and_missing_items(): void
    {
        $me = $this->user();
        $this->star($me, 'user', $me->id)->assertStatus(422);
        $this->star($me, 'note', 1)->assertStatus(422);
        $this->star($me, 'tool', 999999)->assertNotFound();
        $this->actingAs($me)->postJson('/launchpad/open', ['type' => 'tool', 'id' => 'abc'])->assertStatus(422);
        $this->assertSame(0, Favorite::count());
    }

    public function test_guests_cannot_use_the_launchpad(): void
    {
        $this->post('/launchpad/favorite', ['type' => 'tool', 'id' => Tool::first()->id])->assertRedirect('/login');
        $this->post('/launchpad/open', ['type' => 'tool', 'id' => Tool::first()->id])->assertRedirect('/login');
    }

    public function test_recent_keeps_only_the_last_twenty_and_hides_starred_items(): void
    {
        $me = $this->user();
        $tools = collect(range(1, 25))->map(fn ($i) => Tool::create(['name' => "Recent tool $i", 'url' => "https://example.com/$i", 'status' => 'active']));

        foreach ($tools as $i => $t) {
            $this->travel(1)->seconds();
            $this->actingAs($me)->postJson('/launchpad/open', ['type' => 'tool', 'id' => $t->id])->assertNoContent();
        }
        $this->assertSame(Launchpad::KEEP_RECENT, RecentOpen::where('user_id', $me->id)->count());
        $this->assertFalse(RecentOpen::where('item_id', $tools[0]->id)->exists());

        // Opening again moves it to the front without duplicating.
        $this->travel(1)->seconds();
        $this->actingAs($me)->postJson('/launchpad/open', ['type' => 'tool', 'id' => $tools[10]->id]);
        $recent = Launchpad::forUser($me)['recent'];
        $this->assertSame("tool:{$tools[10]->id}", $recent[0]['key']);
        $this->assertCount(Launchpad::SHOW_RECENT, $recent);

        // A starred item shows under favorites, not recent.
        $this->star($me, 'tool', $tools[10]->id);
        $this->assertNotContains("tool:{$tools[10]->id}", Launchpad::forUser($me)['recent']->pluck('key')->all());
    }

    public function test_opening_a_brand_hub_counts_as_recent(): void
    {
        $me = $this->user();
        $brand = Brand::where('slug', 'ocusafe')->first() ?? Brand::first();
        $this->actingAs($me)->get("/brands/{$brand->slug}")->assertOk()->assertSee('Star '.$brand->name, false);
        $this->assertSame("brand:{$brand->id}", Launchpad::forUser($me)['recent'][0]['key']);
    }

    public function test_deleting_an_item_removes_it_from_every_launchpad(): void
    {
        $me = $this->user();
        $tool = Tool::first();
        $acct = Account::create(['brand_id' => Brand::first()->id, 'platform' => 'instagram', 'handle' => '@x', 'login' => 'social@39dollarglasses.com']);

        $this->star($me, 'tool', $tool->id);
        $this->star($me, 'account', $acct->id);
        $this->actingAs($me)->postJson('/launchpad/open', ['type' => 'account', 'id' => $acct->id]);

        $tool->delete();
        $acct->delete();
        $this->assertSame(0, Favorite::count());
        $this->assertSame(0, RecentOpen::count());
    }

    public function test_launcher_marks_favorites_and_recents_and_never_exposes_logins_in_the_strip(): void
    {
        $me = $this->user();
        $acct = Account::create(['brand_id' => Brand::first()->id, 'platform' => 'instagram', 'handle' => '@x', 'login' => 'secret-login@39dollarglasses.com', 'notes' => 'private note']);
        $tool = Tool::first();
        $this->star($me, 'account', $acct->id)
            ->assertJsonPath('html', fn ($html) => ! str_contains($html, 'secret-login') && ! str_contains($html, 'private note'));
        $this->actingAs($me)->postJson('/launchpad/open', ['type' => 'tool', 'id' => $tool->id]);

        $json = collect($this->actingAs($me)->getJson('/launcher.json')->assertOk()->json())->keyBy(fn ($i) => $i['key'] ?? $i['t']);
        $this->assertSame(0, $json["account:{$acct->id}"]['f']);
        $this->assertSame(0, $json["tool:{$tool->id}"]['r']);
    }
}
