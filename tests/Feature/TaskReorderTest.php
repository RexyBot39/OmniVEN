<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskReorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    private function person(array $extra = []): User
    {
        return User::factory()->create($extra + ['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    /** The board's task titles in one person's column, top to bottom. */
    private function column(User $viewer, User $owner): array
    {
        $html = $this->actingAs($viewer)->get('/')->assertOk()->getContent();
        preg_match('#data-person="'.$owner->id.'">(.*?)class="bcol-list"(.*?)(?=<div class="bcol |\z)#s', $html, $m);
        preg_match_all('#<div class="bt-t">(.*?)</div>#', $m[2] ?? '', $t);

        return $t[1];
    }

    public function test_anyone_can_drag_their_own_tasks_into_their_own_order(): void
    {
        $me = $this->person();
        $a = Task::create(['title' => 'Alpha', 'assignee_id' => $me->id, 'hot' => false]);
        $b = Task::create(['title' => 'Bravo', 'assignee_id' => $me->id, 'hot' => false, 'due_on' => today()->addDay()]);
        $c = Task::create(['title' => 'Charlie', 'assignee_id' => $me->id, 'hot' => true]);
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->column($me, $me));

        $this->actingAs($me)->postJson('/tasks/reorder', ['assignee_id' => $me->id, 'ids' => [$a->id, $c->id, $b->id]])->assertOk()->assertJson(['ok' => true, 'moved' => 0]);
        $this->assertSame(['Alpha', 'Charlie', 'Bravo'], $this->column($me, $me));

        // A task added later goes on top until it's dragged somewhere.
        Task::create(['title' => 'Delta', 'assignee_id' => $me->id, 'hot' => false]);
        $this->assertSame(['Delta', 'Alpha', 'Charlie', 'Bravo'], $this->column($me, $me));

        $this->actingAs($me)->get('/')->assertSee('data-reorder="'.route('tasks.reorder').'"', false)->assertSee('data-grip', false);
    }

    public function test_staff_cant_arrange_or_take_someone_elses_tasks(): void
    {
        $me = $this->person();
        $other = $this->person();
        $theirs = Task::create(['title' => 'Theirs', 'assignee_id' => $other->id]);
        $mine = Task::create(['title' => 'Mine', 'assignee_id' => $me->id]);

        $this->actingAs($me)->postJson('/tasks/reorder', ['assignee_id' => $other->id, 'ids' => [$theirs->id]])->assertForbidden();
        $this->actingAs($me)->postJson('/tasks/reorder', ['assignee_id' => $me->id, 'ids' => [$theirs->id, $mine->id]])->assertForbidden();
        $this->actingAs($me)->postJson('/tasks/reorder', ['assignee_id' => $other->id, 'ids' => [$mine->id]])->assertForbidden();
        $this->assertSame($other->id, $theirs->fresh()->assignee_id);
        $this->assertNull($mine->fresh()->board_sort);

        // Only their own cards get a grip.
        $html = $this->actingAs($me)->get('/')->getContent();
        $this->assertStringContainsString('aria-label="Move “Mine”', $html);
        $this->assertStringNotContainsString('aria-label="Move “Theirs”', $html);
    }

    public function test_admins_can_move_a_task_to_another_persons_column(): void
    {
        $admin = $this->person(['is_admin' => true]);
        $from = $this->person();
        $to = $this->person();
        $move = Task::create(['title' => 'Write the sale email', 'assignee_id' => $from->id]);
        $stay = Task::create(['title' => 'Already there', 'assignee_id' => $to->id]);
        $done = Task::create(['title' => 'Finished', 'assignee_id' => $from->id, 'completed_at' => now()]);

        $this->actingAs($admin)->postJson('/tasks/reorder', ['assignee_id' => $to->id, 'ids' => [$stay->id, $move->id, $done->id]])
            ->assertOk()->assertJson(['moved' => 1]);

        $move->refresh();
        $this->assertSame($to->id, $move->assignee_id);
        $this->assertSame($admin->id, $move->assigned_by);
        $this->assertSame(2, $move->board_sort);
        $this->assertSame($from->id, $done->fresh()->assignee_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'task.updated', 'subject_id' => $move->id, 'description' => "Moved task “Write the sale email” to {$to->name}’s column"]);
        $this->assertSame(['Already there', 'Write the sale email'], $this->column($admin, $to));
        $this->actingAs($admin)->get('/')->assertSee('data-admin', false);

        // Reassigning on the edit form starts it fresh in the new column's usual order.
        $this->actingAs($admin)->put("/tasks/{$move->id}", ['title' => $move->title, 'assignee_id' => $from->id])->assertRedirect();
        $this->assertNull($move->fresh()->board_sort);
    }

    public function test_the_order_must_name_tasks_and_an_active_person(): void
    {
        $me = $this->person();
        $gone = $this->person(['is_active' => false]);
        $this->actingAs($me)->postJson('/tasks/reorder', ['assignee_id' => $me->id, 'ids' => []])->assertUnprocessable();
        $this->actingAs($me)->postJson('/tasks/reorder', ['assignee_id' => $gone->id, 'ids' => [1]])->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->postJson('/tasks/reorder', ['assignee_id' => $me->id, 'ids' => [1]])->assertUnauthorized();
    }
}
