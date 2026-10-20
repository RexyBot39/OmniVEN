<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarTest extends TestCase
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

    /** The sidebar and top bar, without the page itself. */
    private function chrome(User $u, string $url = '/help'): string
    {
        $html = $this->actingAs($u)->get($url)->assertOk()->getContent();

        return substr($html, strpos($html, '<aside class="sidebar"'), strpos($html, '</header>') - strpos($html, '<aside class="sidebar"'));
    }

    public function test_every_section_is_in_the_sidebar_with_a_top_bar(): void
    {
        $nav = $this->chrome($this->admin);
        foreach (['Work', 'Revenue', 'Growth', 'Search &amp; AI', 'Reputation', 'Brands &amp; tools'] as $g) {
            $this->assertStringContainsString('<span class="sb-text">'.$g.'</span>', $nav);
        }
        foreach (['Tasks', 'My day', 'Action calendar', 'Sales', 'Monthly report', 'Campaigns', 'Outreach', 'Affiliate assets', 'Search', 'Content gaps', 'Site check', 'Reviews', 'Reddit', 'Coverage', 'Access'] as $item) {
            $this->assertMatchesRegularExpression('#<a href="[^"]+" title="[^"]*"[^>]*>'.preg_quote($item, '#').'</a>#', $nav);
        }
        // Help and admin pages under Others, and in the account menu with Sign out.
        $this->assertMatchesRegularExpression('/<span>Others<\/span>.*<span class="sb-text">Help<\/span>.*<span class="sb-text">People<\/span>/s', $nav);
        $this->assertMatchesRegularExpression('/class="mm-panel".*<b>Help<\/b>.*<b>People<\/b>.*Sign out/s', $nav);
        $this->assertStringContainsString('id="nav-toggle" aria-expanded="false" aria-controls="sidebar"', $nav);
        $this->assertStringContainsString('data-theme-toggle', $nav);
        // No inline scripts or handlers (the CSP allows only files from the site).
        $this->assertDoesNotMatchRegularExpression('/<script(?! src)|\son[a-z]+=/i', $this->actingAs($this->admin)->get('/help')->getContent());
        $this->assertStringContainsString('js/theme.js', $this->actingAs($this->admin)->get('/help')->getContent());
    }

    public function test_people_only_see_what_they_can_open(): void
    {
        $nav = $this->chrome($this->kay);
        $this->assertStringNotContainsString('<span class="sb-text">Revenue</span>', $nav);
        foreach (['Sales', 'Monthly report', 'Email &amp; SMS', 'People', 'Access review', 'Activity report'] as $hidden) {
            $this->assertStringNotContainsString('>'.$hidden.'</a>', $nav);
            $this->assertStringNotContainsString('<b>'.$hidden.'</b>', $nav);
        }
        $this->assertStringContainsString('<span class="sb-text">Help</span>', $nav);
    }

    public function test_ask_omniven_ai_appears_once_it_is_switched_on(): void
    {
        config(['assistant.key' => null]);
        $this->assertStringNotContainsString('Ask OmniVEN AI', $this->chrome($this->kay));

        config(['assistant.key' => 'sk-test']);
        $nav = $this->chrome($this->kay);
        $this->assertStringContainsString('<span>Ask OmniVEN AI</span>', $nav);
        $this->assertStringContainsString('<b>Ask OmniVEN AI</b>', $nav, 'the sidebar card');
        $this->assertStringContainsString('>TaskBuddy AI</a>', $nav);
    }

    public function test_the_current_section_is_open_and_marked(): void
    {
        $nav = $this->chrome($this->kay, '/outreach');
        $this->assertMatchesRegularExpression('/data-sb-group="growth"\s+open\s*>\s*<summary class="sb-item is-here"/', $nav);
        $this->assertMatchesRegularExpression('#href="[^"]*/outreach" title="[^"]*"\s+aria-current="page"\s*>Outreach</a>#', $nav);
        $this->assertDoesNotMatchRegularExpression('/data-sb-group="revenue"\s+open/', $nav);
    }
}
