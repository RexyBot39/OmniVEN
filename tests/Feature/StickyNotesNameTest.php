<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StickyNotesNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_says_sticky_notes_not_postit(): void
    {
        $this->seed(OmniVenSeeder::class);
        $admin = User::factory()->admin()->create(['email' => 'drmarcweinstein@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($admin)->post('/postits', ['title' => 'This week', 'color' => 'yellow']);

        foreach (['/', '/help', '/activity', route('people.show', $admin)] as $page) {
            $html = $this->actingAs($admin)->get($page)->assertOk()->getContent();
            $this->assertStringNotContainsString('PostIT', $html, $page);
            $this->assertStringNotContainsStringIgnoringCase('post-it', $html, $page);
        }
        $this->actingAs($admin)->get('/')->assertSee('>Sticky Notes</button>', false);
        $this->assertTrue(ActivityLog::where('description', 'Created a sticky note')->exists());

        $items = collect($this->actingAs($admin)->getJson('/launcher.json')->json());
        $page = $items->firstWhere('t', 'Sticky Notes');
        $this->assertNotNull($page);
        $this->assertStringContainsString('postit', $page['a']);
    }

    public function test_old_activity_entries_are_reworded(): void
    {
        ActivityLog::create(['action' => 'postit.created', 'description' => 'Created a PostIT note']);
        ActivityLog::create(['action' => 'postit.item_done', 'description' => 'Checked off a PostIT to-do']);

        $migration = require database_path('migrations/2026_10_31_000001_rename_postit_to_sticky_notes.php');
        $migration->up();

        $this->assertSame(['Created a sticky note', 'Checked off a sticky note to-do'], ActivityLog::orderBy('id')->pluck('description')->all());
    }
}
