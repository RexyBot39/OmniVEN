<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalmLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    private function user(bool $admin = false): User
    {
        return User::factory()->create(['name' => 'Dr. Smith', 'email' => ($admin ? 'boss' : 'kay').'@39dollarglasses.com', 'is_admin' => $admin, 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    public function test_brands_sit_in_one_picker_and_the_greeting_is_one_line(): void
    {
        $html = $this->actingAs($this->user())->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<details class="brandpick" id="brandpick">', $html);
        $this->assertMatchesRegularExpression('#<details class="brandpick".*<div class="chips" role="group" aria-label="Filter by brand">.*data-brand="ocusafe".*</details>#s', $html);
        $this->assertStringNotContainsString('class="eyebrow"', $html);
        // No double period after a name that ends in one.
        $this->assertStringContainsString(', Dr.</h1>', $html);
    }

    public function test_only_admins_get_the_show_times_switch(): void
    {
        $this->actingAs($this->user())->get('/')->assertDontSee('data-stamps', false);
        $this->actingAs($this->user(true))->get('/')->assertSee('data-stamps', false)->assertSee('Show times');
    }

    public function test_tool_cards_leave_logins_to_the_access_tab_and_skip_default_labels(): void
    {
        $t = Tool::where('all_brands', true)->where('status', 'active')->firstOrFail();
        $t->update(['owner' => 'Owner Person', 'access_note' => 'Ask Owner Person nicely']);
        $html = $this->actingAs($this->user())->get('/')->getContent();
        preg_match('#<article class="tool" [^>]*data-search="'.preg_quote(strtolower(e($t->name)), '#').'.*?</article>#s', $html, $m);
        $card = $m[0];
        $this->assertStringNotContainsString('Ask Owner Person nicely', $card);
        $this->assertStringNotContainsString('All brands', $card);
        $this->assertStringNotContainsString('class="pill active"', $card);
        // Still on the Access tab.
        $this->assertStringContainsString('Ask Owner Person nicely', $html);
    }
}
