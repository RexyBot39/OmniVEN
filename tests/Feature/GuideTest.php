<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    private function member(): User
    {
        return User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/guide')->assertRedirect('/login');
    }

    public function test_staff_see_every_chapter_they_can_use_but_not_revenue_or_admin_playbooks(): void
    {
        $this->actingAs($this->member())->get('/guide')->assertOk()
            ->assertSee('OmniVEN Staff Guide')
            ->assertSee('Start here: learn these five')
            ->assertSee('id="work"', false)
            ->assertSee('id="growth"', false)
            ->assertSee('id="g-finder-q"', false)
            ->assertSee('/img/guide/home.jpg', false)
            ->assertSee('href="'.route('links.index').'"', false)
            ->assertDontSee('id="revenue"', false)
            ->assertDontSee('href="#revenue"', false)
            ->assertDontSee('/img/guide/sales.jpg', false)
            ->assertDontSee('Weekly one-on-one')
            ->assertDontSee('<script>', false);
    }

    public function test_admins_also_get_revenue_and_admin_playbooks(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);

        $this->actingAs($admin)->get('/guide')->assertOk()
            ->assertSee('id="revenue"', false)
            ->assertSee('href="'.route('sales').'"', false)
            ->assertSee('Weekly one-on-one')
            ->assertSee('Quarterly access review');
    }

    public function test_the_ai_chapter_shows_only_when_the_assistant_is_set_up(): void
    {
        config(['assistant.key' => null]);
        $this->actingAs($this->member())->get('/guide')->assertOk()->assertDontSee('id="ai"', false);

        config(['assistant.key' => 'test-key']);
        $this->actingAs($this->member())->get('/guide')->assertOk()->assertSee('id="ai"', false);
    }

    public function test_it_is_in_the_sidebar_launcher_and_help(): void
    {
        $member = $this->member();

        $this->actingAs($member)->get('/')->assertOk()->assertSee('href="'.route('guide').'"', false);
        $this->actingAs($member)->get('/help')->assertOk()->assertSee('href="'.route('guide').'"', false);
        $items = collect($this->actingAs($member)->getJson('/launcher.json')->assertOk()->json());
        $this->assertTrue($items->contains(fn ($i) => ($i['t'] ?? null) === 'Staff guide' && ($i['u'] ?? null) === route('guide')));
    }

    public function test_every_screenshot_it_uses_ships_with_the_app(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        preg_match_all('#/img/guide/([a-z-]+\.jpg)#', $this->actingAs($admin)->get('/guide')->getContent(), $m);

        $this->assertNotEmpty($m[1]);
        foreach (array_unique($m[1]) as $file) {
            $this->assertFileExists(public_path('img/guide/'.$file));
        }
    }
}
