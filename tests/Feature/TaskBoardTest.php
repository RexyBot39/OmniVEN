<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $leona;
    private User $chanley;
    private User $kaylie;
    private User $marc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $this->marc = $this->person('Marc Weinstein', 'drmarcweinstein@39dollarglasses.com', true);
        // Created out of order on purpose: columns follow the board order, not sign-up order.
        $this->kaylie = $this->person('Kaylie Ross', 'kaylie@39dollarglasses.com');
        $this->leona = $this->person('Leona Park', 'leona.p@39dollarglasses.com', true);
        $this->chanley = $this->person('Chanley Diaz', 'chanley@39dollarglasses.com');
    }

    /** The starter seed may already have created the configured admins. */
    private function person(string $name, string $email, bool $admin = false): User
    {
        $u = User::where('email', $email)->first() ?? User::factory()->create(['name' => $name, 'email' => $email]);
        $u->update(['name' => $name, 'is_admin' => $admin, 'is_active' => true, 'hotlist_popup_on' => today(), 'board_position' => $u->board_position ?? User::boardPositionFor($name, $email)]);

        return $u->fresh();
    }

    private function brand(string $slug): Brand
    {
        return Brand::where('slug', $slug)->firstOrFail();
    }

    public function test_leona_chanley_and_kaylie_get_the_first_three_columns(): void
    {
        $this->assertSame([1, 2, 3], [$this->leona->board_position, $this->chanley->board_position, $this->kaylie->board_position]);
        $this->assertNull($this->marc->board_position);

        $html = $this->actingAs($this->chanley)->get('/')->assertOk()->getContent();
        // Tasks is the first tab, shown on arrival; Routines is a click away.
        $this->assertMatchesRegularExpression('/data-tab="tasks" aria-selected="true".*data-tab="routines" aria-selected="false"/s', $html);
        $this->assertStringContainsString('<section data-view="tasks">', $html);
        $this->assertStringContainsString('<section data-view="routines" hidden>', $html);
        // Columns in board order, then you if you're not on the board.
        $this->assertMatchesRegularExpression('/data-person="'.$this->leona->id.'".*data-person="'.$this->chanley->id.'".*data-person="'.$this->kaylie->id.'"/s', $html);
        $this->actingAs($this->marc)->get('/')->assertSee('data-person="'.$this->marc->id.'"', false)->assertSee('Marc (you)');
    }

    public function test_people_add_their_own_tasks_with_a_brand_from_their_column(): void
    {
        $this->actingAs($this->kaylie)->post('/tasks', ['title' => 'Schedule the Ocusafe reel', 'assignee_id' => $this->kaylie->id, 'brand_id' => $this->brand('ocusafe')->id, 'due_on' => '2026-10-20'])
            ->assertRedirect(route('dashboard').'#tasks')->assertSessionHas('status', 'Added “Schedule the Ocusafe reel” to your list.');
        $task = Task::where('title', 'Schedule the Ocusafe reel')->firstOrFail();
        $this->assertSame('ocusafe', $task->brand->slug);

        $html = $this->actingAs($this->leona)->get('/')->getContent();
        $this->assertMatchesRegularExpression('/class="bt task[^"]*" style="--bc:var\(--c'.$this->brand('ocusafe')->colorSlot().'\)"[^>]*data-brands="ocusafe"/', $html);
        $this->assertStringContainsString('<span class="bpill"', $html);

        // Only 39DollarGlasses, Ocusafe and Ocusleep can label a task.
        $this->actingAs($this->kaylie)->post('/tasks', ['title' => 'x', 'assignee_id' => $this->kaylie->id, 'brand_id' => $this->brand('neurolux')->id])->assertSessionHasErrors('brand_id');
        $this->actingAs($this->kaylie)->get('/work/new')->assertSee('name="brand_id"', false)->assertSee('39DollarGlasses')->assertSee('Ocusleep');
    }

    public function test_assigned_routines_show_in_the_owners_column_until_ticked_for_the_period(): void
    {
        $r = Routine::where('cadence', 'weekly')->firstOrFail();
        $r->update(['assignee_id' => $this->chanley->id]);

        $html = $this->actingAs($this->leona)->get('/')->getContent();
        $col = $this->column($html, $this->chanley);
        $this->assertStringContainsString('class="bt routine "', $col);
        $this->assertStringContainsString(e($r->title), $col);
        $this->assertStringContainsString('↻ Weekly routine', $col);
        $this->assertStringNotContainsString(e($r->title), $this->column($html, $this->kaylie));

        $this->actingAs($this->chanley)->post("/routines/{$r->id}/toggle", ['return' => 'tasks'])->assertRedirect(route('dashboard').'#tasks');
        $col = $this->column($this->actingAs($this->chanley)->get('/')->getContent(), $this->chanley);
        $this->assertStringContainsString('class="bt routine done"', $col);
        $this->assertStringContainsString('Done this week', $col);

        // Next week it's due again.
        $this->travelTo(Carbon::parse('2026-10-20 09:00', config('app.timezone')));
        $this->flushSession();
        $col = $this->column($this->actingAs($this->chanley)->withSession(['signed_in_at' => now()->timestamp])->get('/')->getContent(), $this->chanley);
        $this->assertStringContainsString('class="bt routine "', $col);
    }

    public function test_completed_tasks_from_the_last_14_days_are_crossed_out_below(): void
    {
        $recent = Task::create(['title' => 'Publish the Ocusleep FAQ', 'assignee_id' => $this->leona->id, 'created_by' => $this->leona->id, 'brand_id' => $this->brand('ocusleep')->id, 'completed_at' => now()->subDays(3), 'completed_by' => $this->leona->id]);
        Task::create(['title' => 'Old launch checklist', 'assignee_id' => $this->kaylie->id, 'created_by' => $this->kaylie->id, 'completed_at' => now()->subDays(20), 'completed_by' => $this->kaylie->id]);

        $html = $this->actingAs($this->chanley)->get('/')->getContent();
        $done = substr($html, strpos($html, 'Completed in the last 14 days'));
        $this->assertStringContainsString('<s>Publish the Ocusleep FAQ</s>', $done);
        $this->assertStringContainsString('✓ Done · Leona', $done);
        $this->assertStringNotContainsString('Old launch checklist', $html);
        // Open-task columns don't repeat finished work.
        $this->assertStringNotContainsString('Publish the Ocusleep FAQ', $this->column($html, $this->leona));
    }

    public function test_admins_choose_who_gets_a_column(): void
    {
        $this->actingAs($this->leona)->patch(route('people.update', $this->marc), ['board_position' => 4])->assertRedirect();
        $this->assertSame(4, $this->marc->fresh()->board_position);
        $this->actingAs($this->leona)->patch(route('people.update', $this->kaylie), ['board_position' => ''])->assertRedirect();
        $this->assertNull($this->kaylie->fresh()->board_position);
        $this->actingAs($this->chanley)->patch(route('people.update', $this->marc), ['board_position' => 1])->assertForbidden();
        $this->actingAs($this->leona)->get('/people')->assertSee('Task board')->assertSee('Column 4');
    }

    private function column(string $html, User $u): string
    {
        $start = strpos($html, 'data-person="'.$u->id.'"');
        $this->assertNotFalse($start, "no column for {$u->name}");
        $end = strpos($html, 'data-person="', $start + 20);
        $end = $end === false ? strpos($html, 'Completed in the last 14 days', $start) : $end;

        return substr($html, $start, $end - $start);
    }
}
