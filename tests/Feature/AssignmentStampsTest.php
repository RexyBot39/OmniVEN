<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentStampsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $leona;
    private User $pat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->admin = User::factory()->admin()->create(['is_active' => true, 'name' => 'Marc Weinstein', 'hotlist_popup_on' => today()]);
        $this->leona = User::factory()->create(['is_active' => true, 'name' => 'Leona Park', 'hotlist_popup_on' => today()]);
        $this->pat = User::factory()->create(['is_active' => true, 'name' => 'Pat Member', 'hotlist_popup_on' => today()]);
    }

    private function routineData(Routine $r, array $extra = []): array
    {
        return array_merge(['title' => $r->title, 'cadence' => $r->cadence, 'brands' => ['all']], $extra);
    }

    public function test_task_assignment_and_completion_are_stamped(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 09:02', config('app.timezone')));
        $this->actingAs($this->admin)->post('/tasks', ['title' => 'Fix Ocusafe feed', 'assignee_id' => $this->leona->id])->assertRedirect();
        $task = Task::where('title', 'Fix Ocusafe feed')->firstOrFail();
        $this->assertSame($this->admin->id, $task->assigned_by);
        $this->assertTrue($task->assigned_at->equalTo(now()));

        // Reassigning moves the stamp; other edits don't.
        $this->travelTo(Carbon::parse('2026-10-15 11:30', config('app.timezone')));
        $this->actingAs($this->admin)->put("/tasks/{$task->id}", ['title' => 'Fix Ocusafe feed', 'assignee_id' => $this->leona->id, 'details' => 'Edited'])->assertRedirect();
        $this->assertSame('09:02', $task->fresh()->assigned_at->timezone(config('app.timezone'))->format('H:i'));
        $this->actingAs($this->admin)->put("/tasks/{$task->id}", ['title' => 'Fix Ocusafe feed', 'assignee_id' => $this->pat->id])->assertRedirect();
        $this->assertSame('11:30', $task->fresh()->assigned_at->timezone(config('app.timezone'))->format('H:i'));

        $this->travelTo(Carbon::parse('2026-10-15 15:10', config('app.timezone')));
        $this->actingAs($this->pat)->post("/tasks/{$task->id}/toggle")->assertRedirect();
        $this->assertSame($this->pat->id, $task->fresh()->completed_by);
    }

    public function test_only_admins_see_task_timestamps(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 09:02', config('app.timezone')));
        $this->actingAs($this->admin)->post('/tasks', ['title' => 'Fix Ocusafe feed', 'assignee_id' => $this->leona->id]);
        $task = Task::where('title', 'Fix Ocusafe feed')->first();
        $this->travelTo(Carbon::parse('2026-10-15 15:10', config('app.timezone')));
        $this->actingAs($this->leona)->post("/tasks/{$task->id}/toggle");

        $this->actingAs($this->admin)->get('/')->assertOk()
            ->assertSee('Oct 15, 2026 9:02am')->assertSee('Oct 15, 2026 3:10pm')->assertSee('by Marc')->assertSee('by Leona');

        $this->actingAs($this->leona)->get('/')->assertOk()
            ->assertSee('✓ Done · Leona')->assertDontSee('9:02am')->assertDontSee('3:10pm')->assertDontSee('Assigned Oct');
    }

    public function test_routine_assignment_is_stamped_and_cleared(): void
    {
        $r = Routine::where('cadence', 'weekly')->first();
        $this->travelTo(Carbon::parse('2026-10-15 10:45', config('app.timezone')));
        $this->actingAs($this->admin)->put("/routines/{$r->id}", $this->routineData($r, ['assignee_id' => $this->leona->id]));
        $r->refresh();
        $this->assertSame($this->admin->id, $r->assigned_by);
        $this->assertSame('10:45', $r->assigned_at->timezone(config('app.timezone'))->format('H:i'));

        // Unrelated edits keep the stamp.
        $this->travelTo(Carbon::parse('2026-10-15 12:00', config('app.timezone')));
        $this->actingAs($this->admin)->put("/routines/{$r->id}", $this->routineData($r, ['assignee_id' => $this->leona->id, 'description' => 'New text']));
        $this->assertSame('10:45', $r->fresh()->assigned_at->timezone(config('app.timezone'))->format('H:i'));

        $this->actingAs($this->admin)->put("/routines/{$r->id}", $this->routineData($r, ['assignee_id' => '']));
        $this->assertNull($r->fresh()->assigned_at);
        $this->assertNull($r->fresh()->assigned_by);
    }

    public function test_only_admins_see_routine_timestamps(): void
    {
        $r = Routine::where('cadence', 'daily')->first();
        $this->travelTo(Carbon::parse('2026-10-15 08:15', config('app.timezone')));
        $this->actingAs($this->admin)->put("/routines/{$r->id}", $this->routineData($r, ['assignee_id' => $this->leona->id]));
        $this->travelTo(Carbon::parse('2026-10-15 09:40', config('app.timezone')));
        $this->actingAs($this->leona)->post("/routines/{$r->id}/toggle");

        $this->actingAs($this->admin)->get('/')->assertOk()
            ->assertSee('Oct 15, 2026 8:15am to Leona by Marc')->assertSee('Oct 15, 2026 9:40am by Leona');
        $this->actingAs($this->admin)->get("/people/{$this->leona->id}")->assertOk()
            ->assertSee('Oct 15, 2026 8:15am')->assertSee('Oct 15, 2026 9:40am');

        $this->actingAs($this->pat)->get('/')->assertOk()
            ->assertSee('✓ Done · Leona')->assertDontSee('8:15am')->assertDontSee('9:40am');
    }
}
