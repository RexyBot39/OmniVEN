<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MegaMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $kay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Marc W', 'email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->kay = User::factory()->create(['name' => 'Kay Lee', 'email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function nav(User $u, string $url = '/help'): string
    {
        $html = $this->actingAs($u)->get($url)->assertOk()->getContent();

        return substr($html, strpos($html, '<header class="appbar"'), strpos($html, '</header>') - strpos($html, '<header class="appbar"'));
    }

    public function test_every_section_is_grouped_with_a_hamburger_for_small_screens(): void
    {
        $nav = $this->nav($this->admin);
        foreach (['Work', 'Revenue', 'Growth', 'Search &amp; AI', 'Reputation', 'Brands &amp; tools'] as $g) {
            $this->assertStringContainsString('<h2>'.$g.'</h2>', $nav);
        }
        foreach (['Tasks', 'My day', 'Action calendar', 'Sales', 'Monthly report', 'Campaigns', 'Outreach', 'Affiliate assets', 'Search', 'Content gaps', 'Site check', 'Reviews', 'Reddit', 'Coverage', 'Access'] as $item) {
            $this->assertStringContainsString('<b>'.$item.'</b>', $nav);
        }
        // Help and admin pages live in the account menu.
        $this->assertMatchesRegularExpression('/class="mm-panel".*<b>Help<\/b>.*<b>People<\/b>.*Sign out/s', $nav);
        $this->assertStringContainsString('id="nav-toggle" aria-expanded="false" aria-controls="mainnav"', $nav);
        // No inline scripts or handlers (the CSP allows only omniven.js).
        $this->assertDoesNotMatchRegularExpression('/<script(?! src)|\son[a-z]+=/i', $nav);
    }

    public function test_people_only_see_what_they_can_open(): void
    {
        $nav = $this->nav($this->kay);
        $this->assertStringNotContainsString('<h2>Revenue</h2>', $nav);
        foreach (['Sales', 'Monthly report', 'Email &amp; SMS', 'People', 'Access review', 'Activity report'] as $hidden) {
            $this->assertStringNotContainsString('<b>'.$hidden.'</b>', $nav);
        }
        $this->assertStringContainsString('<b>Help</b>', $nav);
    }

    public function test_ask_omniven_ai_appears_once_it_is_switched_on(): void
    {
        config(['assistant.key' => null]);
        $this->assertStringNotContainsString('Ask OmniVEN AI', $this->nav($this->kay));

        config(['assistant.key' => 'sk-test']);
        $nav = $this->nav($this->kay);
        $this->assertStringContainsString('<span>Ask OmniVEN AI</span>', $nav);
        $this->assertStringContainsString('Ask OmniVEN AI about search &amp; ai →', $nav);
        $this->assertStringContainsString(route('assistant', ['focus' => 'reputation']), $nav);
        $this->assertStringNotContainsString('>Ask</a>', $nav);
    }

    public function test_the_current_section_is_marked(): void
    {
        $nav = $this->nav($this->kay, '/outreach');
        $this->assertMatchesRegularExpression('/class="mg-sum is-here">Growth/', $nav);
        $this->assertMatchesRegularExpression('/href="[^"]*\/outreach"\s+aria-current="page"\s*><b>Outreach/', $nav);
    }
}
