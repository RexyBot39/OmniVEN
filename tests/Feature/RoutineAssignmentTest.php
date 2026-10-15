<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\PostIt;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $leona;
    private User $pat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->admin = User::factory()->admin()->create(['is_active' => true, 'name' => 'Marc Weinstein']);
        $this->leona = User::factory()->create(['is_active' => true, 'name' => 'Leona Park']);
        $this->pat = User::factory()->create(['is_active' => true, 'name' => 'Pat Member']);
    }

    private function routineData(Routine $r, array $extra = []): array
    {
        return array_merge(['title' => $r->title, 'cadence' => $r->cadence, 'description' => $r->description, 'link' => $r->link, 'sort' => $r->sort, 'brands' => ['all']], $extra);
    }

    public function test_admin_assigns_and_unassigns_a_routine(): void
    {
        $r = Routine::where('cadence', 'weekly')->first();
        $this->actingAs($this->admin)->get("/routines/{$r->id}/edit")->assertOk()->assertSee('Assigned to')->assertSee('Leona Park');

        $this->actingAs($this->admin)->put("/routines/{$r->id}", $this->routineData($r, ['assignee_id' => $this->leona->id]))->assertRedirect();
        $this->assertSame($this->leona->id, $r->fresh()->assignee_id);
        $this->assertTrue(ActivityLog::where('description', 'like', '%assigned to Leona Park%')->exists());

        $this->actingAs($this->admin)->put("/routines/{$r->id}", $this->routineData($r, ['assignee_id' => '']))->assertRedirect();
        $this->assertNull($r->fresh()->assignee_id);
    }

    public function test_admin_can_assign_when_creating(): void
    {
        $this->actingAs($this->admin)->post('/routines', ['title' => 'Post Trailview reels', 'cadence' => 'weekly', 'brands' => ['all'], 'assignee_id' => $this->pat->id])->assertRedirect();
        $this->assertSame($this->pat->id, Routine::where('title', 'Post Trailview reels')->value('assignee_id'));
    }

    public function test_staff_cannot_assign_routines(): void
    {
        $r = Routine::first();
        $this->actingAs($this->pat)->put("/routines/{$r->id}", $this->routineData($r, ['assignee_id' => $this->pat->id]))->assertForbidden();
        $this->assertNull($r->fresh()->assignee_id);
    }

    public function test_cannot_newly_assign_someone_without_access_but_can_keep_them(): void
    {
        $r = Routine::first();
        $gone = User::factory()->create(['is_active' => false, 'name' => 'Former Person']);
        $this->actingAs($this->admin)->put("/routines/{$r->id}", $this->routineData($r, ['assignee_id' => $gone->id]))->assertSessionHasErrors('assignee_id');

        $r->update(['assignee_id' => $gone->id]);
        $this->actingAs($this->admin)->put("/routines/{$r->id}", $this->routineData($r, ['assignee_id' => $gone->id, 'title' => 'Renamed']))->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $r->fresh()->title);
    }

    public function test_assignee_sees_it_marked_as_theirs(): void
    {
        $r = Routine::where('cadence', 'daily')->first();
        $r->update(['assignee_id' => $this->leona->id]);

        $this->actingAs($this->leona)->get('/')->assertOk()->assertSee('→ You')->assertSee('assigned to you still due');
        $this->actingAs($this->pat)->get('/')->assertOk()->assertSee('→ Leona')->assertDontSee('assigned to you still due');
        // Anyone can still tick it if they cover.
        $this->actingAs($this->pat)->post("/routines/{$r->id}/toggle")->assertRedirect();
        $this->assertTrue($r->fresh()->isDone());
    }

    public function test_admin_sees_a_staff_members_routines_and_tasks(): void
    {
        $mine = Routine::where('cadence', 'daily')->first();
        $mine->update(['assignee_id' => $this->leona->id]);
        $other = Routine::where('cadence', 'weekly')->first();
        $other->update(['assignee_id' => $this->pat->id]);
        $this->actingAs($this->leona)->post("/routines/{$mine->id}/toggle");

        Task::create(['title' => 'Fix the Ocusafe feed', 'assignee_id' => $this->leona->id, 'created_by' => $this->admin->id, 'hot' => true, 'due_on' => today()->subDay()]);
        Task::create(['title' => 'Pat task', 'assignee_id' => $this->pat->id, 'created_by' => $this->admin->id]);
        Task::create(['title' => 'Old finished task', 'assignee_id' => $this->leona->id, 'created_by' => $this->admin->id, 'completed_at' => now()->subDays(2), 'completed_by' => $this->leona->id]);
        PostIt::create(['user_id' => $this->leona->id, 'title' => 'Secret list', 'color' => 'yellow']);

        $this->actingAs($this->admin)->get("/people/{$this->leona->id}")->assertOk()
            ->assertSee('Leona Park')
            ->assertSee($mine->title)->assertDontSee($other->title)
            ->assertSee('Fix the Ocusafe feed')->assertDontSee('Pat task')
            ->assertSee('Old finished task')
            ->assertSee('✓ Done')
            ->assertSee('PostIT notes are private')
            ->assertDontSee('Secret list');

        $this->actingAs($this->admin)->get('/people')->assertSee(route('people.show', $this->leona), false);
    }

    public function test_staff_pages_are_admin_only(): void
    {
        $this->actingAs($this->pat)->get("/people/{$this->leona->id}")->assertForbidden();
        $this->actingAs($this->leona)->get("/people/{$this->leona->id}")->assertForbidden();
    }

    public function test_deleting_a_person_unassigns_their_routines(): void
    {
        $r = Routine::first();
        $r->update(['assignee_id' => $this->pat->id]);
        $this->pat->delete();
        $this->assertNull($r->fresh()->assignee_id);
    }
}
