<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\PostIt;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StuckPostItTest extends TestCase
{
    use RefreshDatabase;

    private User $pat;
    private User $sam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->pat = User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        $this->sam = User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function note(): PostIt
    {
        $this->actingAs($this->pat)->post('/postits', ['title' => 'Launch week', 'color' => 'pink'])->assertRedirect();
        $note = PostIt::where('title', 'Launch week')->firstOrFail();
        $note->items()->create(['body' => 'Brief the Ocusleep designer']);

        return $note;
    }

    public function test_a_new_note_is_stuck_and_floats_on_every_page(): void
    {
        $note = $this->note();
        $this->assertTrue($note->stuck);

        foreach (['/', '/help', '/calendar', '/work/new'] as $page) {
            $this->actingAs($this->pat)->get($page)->assertOk()
                ->assertSee('class="stuck-postits"', false)
                ->assertSee('Launch week')
                ->assertSee('Brief the Ocusleep designer')
                ->assertSee('Unstick');
        }
    }

    public function test_nobody_else_sees_it_not_even_an_admin(): void
    {
        $note = $this->note();
        $this->actingAs($this->sam)->get('/help')->assertOk()
            ->assertDontSee('class="stuck-postits"', false)
            ->assertDontSee('Brief the Ocusleep designer');
        $this->actingAs($this->sam)->post("/postits/{$note->id}/stick")->assertForbidden();
        $this->assertTrue($note->fresh()->stuck);
    }

    public function test_unstick_keeps_you_on_the_same_page_and_the_note_stays_on_the_tab(): void
    {
        $note = $this->note();

        $this->actingAs($this->pat)->from('/help')->post("/postits/{$note->id}/stick", ['from_sticky' => '1'])
            ->assertRedirect('/help');
        $this->assertFalse($note->fresh()->stuck);

        $this->actingAs($this->pat)->get('/help')->assertOk()->assertDontSee('class="stuck-postits"', false);
        $this->actingAs($this->pat)->get('/')->assertOk()
            ->assertSee('Brief the Ocusleep designer')
            ->assertSee('Stick to screen');

        // Stick it again from the PostIT tab.
        $this->actingAs($this->pat)->post("/postits/{$note->id}/stick")->assertRedirect(route('dashboard').'#postit');
        $this->assertTrue($note->fresh()->stuck);
    }

    public function test_ticking_and_adding_from_the_stuck_note_stay_on_the_page(): void
    {
        $note = $this->note();
        $item = $note->items()->first();

        $this->actingAs($this->pat)->from('/calendar')->post("/postit-items/{$item->id}/toggle", ['from_sticky' => '1'])->assertRedirect('/calendar');
        $this->assertNotNull($item->fresh()->done_at);

        $this->actingAs($this->pat)->from('/calendar')->post("/postits/{$note->id}/items", ['body' => 'Book the shoot', 'from_sticky' => '1'])->assertRedirect('/calendar');
        $this->assertSame(2, $note->items()->count());
    }

    public function test_note_text_stays_out_of_the_activity_log(): void
    {
        $note = $this->note();
        $this->actingAs($this->pat)->post("/postits/{$note->id}/stick", ['from_sticky' => '1']);

        $logged = ActivityLog::pluck('description')->implode(' ');
        $this->assertStringContainsString('Unstuck a PostIT note', $logged);
        $this->assertStringNotContainsString('Launch week', $logged);
        $this->assertStringNotContainsString('Ocusleep designer', $logged);
    }
}
