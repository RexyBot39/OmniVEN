<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/help')->assertRedirect('/login');
    }

    public function test_staff_see_the_guide_with_search_but_not_the_admin_sections(): void
    {
        $member = User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);

        $this->actingAs($member)->get('/help')->assertOk()
            ->assertSee('id="help-q"', false)
            ->assertSee('Your first ten minutes')
            ->assertSee('The seven tabs, plus the action calendar')
            ->assertSee('Playbooks that save the most time')
            ->assertSee('House rules')
            ->assertSee('/images/brand-icons.svg', false)
            ->assertDontSee('id="customize"', false)
            ->assertDontSee('id="instance"', false)
            ->assertDontSee('id="checklist"', false)
            ->assertDontSee('OMNIVEN_ADMIN_EMAILS')
            ->assertDontSee('href="#customize"', false);
    }

    public function test_admins_also_see_customize_run_and_checklist(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);

        $this->actingAs($admin)->get('/help')->assertOk()
            ->assertSee('id="customize"', false)
            ->assertSee('Action calendar items')
            ->assertSee('id="instance"', false)
            ->assertSee('OMNIVEN_ADMIN_EMAILS')
            ->assertSee('id="help-checks"', false)
            ->assertSee('href="#checklist"', false);
    }

    public function test_help_is_in_the_top_bar_and_the_launcher(): void
    {
        $member = User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);

        $this->actingAs($member)->get('/')->assertOk()->assertSee('href="'.route('help').'"', false);
        $items = collect($this->actingAs($member)->getJson('/launcher.json')->assertOk()->json());
        $this->assertTrue($items->contains(fn ($i) => ($i['t'] ?? null) === 'Help' && ($i['u'] ?? null) === route('help')));
    }

    public function test_brand_spelling_in_the_guide(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        $html = $this->actingAs($admin)->get('/help')->getContent();

        foreach (['OcuSafe', 'OcuSleep', 'Ocu-safe', 'Ocu-sleep'] as $wrong) {
            $this->assertStringNotContainsString($wrong, $html);
        }
    }
}
