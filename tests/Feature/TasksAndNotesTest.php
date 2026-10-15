<?php

namespace Tests\Feature;

use App\Models\PostIt;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksAndNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    public function test_marlow_and_leona_are_preloaded_as_admins(): void
    {
        foreach (['drmarlow@39dollarglasses.com', 'leona.p@39dollarglasses.com'] as $email) {
            $u = User::where('email', $email)->first();
            $this->assertNotNull($u, $email);
            $this->assertTrue($u->is_admin);
        }
    }

    public function test_admin_can_assign_to_another_admin_and_it_shows_on_hotlist(): void
    {
        $leona = User::where('email', 'leona.p@39dollarglasses.com')->first();
        $marlow = User::where('email', 'drmarlow@39dollarglasses.com')->first();

        $this->actingAs($leona)->post('/tasks', [
            'title' => 'Approve Q4 Ocusafe ad creative', 'assignee_id' => $marlow->id, 'hot' => '1', 'due_on' => today()->addDay()->toDateString(),
        ])->assertRedirect();

        $task = Task::first();
        $this->assertSame($marlow->id, $task->assignee_id);
        $this->assertSame($leona->id, $task->created_by);
        $this->assertTrue($task->hot);

        $this->actingAs(User::factory()->create())->get('/')->assertSee('Hotlist')->assertSee('Approve Q4 Ocusafe ad creative');
    }

    public function test_staff_can_add_tasks_but_not_edit_or_delete_them(): void
    {
        $staff = User::factory()->create();
        $this->actingAs($staff)->post('/tasks', ['title' => 'Draft the Ocusleep FAQ', 'assignee_id' => $staff->id])->assertRedirect();
        $task = Task::where('title', 'Draft the Ocusleep FAQ')->firstOrFail();
        $this->assertSame($staff->id, $task->created_by);

        $this->actingAs($staff)->get("/tasks/{$task->id}/edit")->assertForbidden();
        $this->actingAs($staff)->delete("/tasks/{$task->id}")->assertForbidden();
    }

    public function test_only_assignee_or_admin_can_complete(): void
    {
        $pat = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::create(['title' => 'Fix feed', 'assignee_id' => $pat->id, 'hot' => true]);

        $this->actingAs($other)->post("/tasks/{$task->id}/toggle")->assertForbidden();
        $this->actingAs($pat)->post("/tasks/{$task->id}/toggle")->assertRedirect();
        $this->assertTrue($task->fresh()->isDone());
        $this->assertSame(0, Task::hotlist()->count());
    }

    public function test_daily_popup_shows_once_per_day_with_my_hot_items(): void
    {
        $pat = User::factory()->create();
        Task::create(['title' => 'Reply to Trustpilot complaint', 'assignee_id' => $pat->id, 'hot' => true]);
        Task::create(['title' => 'Someone else’s job', 'assignee_id' => User::factory()->create()->id, 'hot' => true]);

        $first = $this->actingAs($pat)->get('/');
        $first->assertSee('hotlist-popup', false)->assertSee('Your Hotlist for today');
        $this->assertStringNotContainsString('Someone else’s job</strong>', $first->getContent());

        $this->actingAs($pat)->get('/')->assertDontSee('hotlist-popup', false);

        // Next day, after signing in again.
        $this->travel(1)->days();
        $this->actingAs($pat->fresh())->withSession(['signed_in_at' => now()->timestamp])->get('/')->assertSee('hotlist-popup', false);
    }

    public function test_no_popup_when_nothing_is_hot_for_me(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertDontSee('hotlist-popup', false);
    }

    public function test_session_ends_after_16_hours(): void
    {
        $pat = User::factory()->create();
        $this->actingAs($pat)->withSession(['signed_in_at' => now()->subHours(15)->timestamp])->get('/')->assertOk();
        $this->actingAs($pat)->withSession(['signed_in_at' => now()->subHours(16)->subMinute()->timestamp])
            ->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_postit_notes_are_private_and_checkable(): void
    {
        $pat = User::factory()->create();
        $sam = User::factory()->create();

        $this->actingAs($pat)->post('/postits', ['title' => 'This week', 'color' => 'yellow'])->assertRedirect();
        $note = PostIt::first();
        $this->actingAs($pat)->post("/postits/{$note->id}/items", ['body' => 'Draft Neurolux email'])->assertRedirect();
        $item = $note->items()->first();

        $this->actingAs($pat)->post("/postit-items/{$item->id}/toggle");
        $this->assertNotNull($item->fresh()->done_at);

        $this->actingAs($sam)->post("/postit-items/{$item->id}/toggle")->assertForbidden();
        $this->actingAs($sam)->delete("/postits/{$note->id}")->assertForbidden();
        $this->actingAs($sam)->get('/')->assertDontSee('Draft Neurolux email');
        $this->actingAs($pat)->get('/')->assertSee('Draft Neurolux email');

        $this->actingAs($pat)->post("/postits/{$note->id}/clear");
        $this->assertSame(0, $note->items()->count());
    }

    public function test_admin_can_add_a_person_before_first_sign_in(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/people', ['name' => 'Sam Coordinator', 'email' => 'sam@39dollarglasses.com'])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'sam@39dollarglasses.com', 'is_admin' => false]);
        $this->actingAs($admin)->post('/people', ['name' => 'X', 'email' => 'x@gmail.com'])->assertSessionHasErrors('email');
    }
}
