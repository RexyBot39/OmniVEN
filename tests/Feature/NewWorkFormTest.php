<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewWorkFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $leona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->admin = User::factory()->admin()->create(['is_active' => true, 'name' => 'Marc Weinstein', 'hotlist_popup_on' => today()]);
        $this->leona = User::factory()->create(['is_active' => true, 'name' => 'Leona Park', 'hotlist_popup_on' => today()]);
    }

    public function test_one_form_offers_both_kinds_with_every_field(): void
    {
        $this->actingAs($this->admin)->get('/work/new')->assertOk()
            ->assertSee('New routine or task')
            ->assertSee('name="kind" value="task" checked', false)
            ->assertSee('name="kind" value="routine"', false)
            ->assertSee('name="due_on"', false)
            ->assertSee('name="hot"', false)
            ->assertSee('name="cadence"', false)
            ->assertSee('name="link"', false)
            ->assertSee('name="brands[]"', false)
            ->assertSee('Leona Park');

        $this->actingAs($this->admin)->get('/work/new?type=routine')->assertOk()
            ->assertSee('name="kind" value="routine" checked', false);
    }

    public function test_creates_a_task_with_a_due_date(): void
    {
        $this->actingAs($this->admin)->post('/work', [
            'kind' => 'task', 'title' => 'Fix Ocusafe feed', 'assignee_id' => $this->leona->id,
            'due_on' => '2026-10-20', 'details' => 'Merchant Center first', 'hot' => '1',
            'cadence' => 'weekly', // routine-only fields are ignored for a task
        ])->assertRedirect(route('dashboard').'#tasks');

        $task = Task::where('title', 'Fix Ocusafe feed')->firstOrFail();
        $this->assertSame($this->leona->id, $task->assignee_id);
        $this->assertSame('2026-10-20', $task->due_on->format('Y-m-d'));
        $this->assertSame('Merchant Center first', $task->details);
        $this->assertTrue((bool) $task->hot);
        $this->assertNotNull($task->assigned_at);
        $this->assertFalse(Routine::where('title', 'Fix Ocusafe feed')->exists());
    }

    public function test_a_task_needs_a_person(): void
    {
        $this->actingAs($this->admin)->from('/work/new')->post('/work', ['kind' => 'task', 'title' => 'Nobody'])
            ->assertRedirect('/work/new')->assertSessionHasErrors('assignee_id');
        $this->assertFalse(Task::where('title', 'Nobody')->exists());
    }

    public function test_creates_a_routine_for_the_whole_team_or_a_person(): void
    {
        $this->actingAs($this->admin)->post('/work', [
            'kind' => 'routine', 'title' => 'Check Trailview reels', 'cadence' => 'weekly', 'assignee_id' => '',
            'details' => 'Open Instagram, reply to comments', 'link' => 'https://www.instagram.com/trailview.co/', 'brands' => ['trailview'],
            'due_on' => '2026-10-20', // task-only fields are ignored for a routine
        ])->assertRedirect(route('dashboard').'#routines');

        $r = Routine::where('title', 'Check Trailview reels')->firstOrFail();
        $this->assertSame('weekly', $r->cadence);
        $this->assertNull($r->assignee_id);
        $this->assertSame('Open Instagram, reply to comments', $r->description);
        $this->assertFalse((bool) $r->all_brands);
        $this->assertSame(['trailview'], $r->brands->pluck('slug')->all());
        $this->assertFalse(Task::where('title', 'Check Trailview reels')->exists());

        $this->actingAs($this->admin)->post('/work', ['kind' => 'routine', 'title' => 'Monthly GA4 audit', 'cadence' => 'monthly', 'assignee_id' => $this->leona->id, 'brands' => ['all']]);
        $this->assertSame($this->leona->id, Routine::where('title', 'Monthly GA4 audit')->value('assignee_id'));
    }

    public function test_routine_limits_are_checked(): void
    {
        $this->actingAs($this->admin)->post('/work', ['kind' => 'routine', 'title' => str_repeat('x', 170), 'cadence' => 'daily'])
            ->assertSessionHasErrors('title');
    }

    public function test_from_a_staff_page_the_person_is_filled_in_and_you_go_back_there(): void
    {
        $this->actingAs($this->admin)->get(route('people.show', $this->leona))->assertOk()
            ->assertSee(e(route('work.create', ['type' => 'task', 'assignee' => $this->leona->id, 'from' => 'person'])), false);

        $this->actingAs($this->admin)->get('/work/new?type=task&assignee='.$this->leona->id.'&from=person')->assertOk()
            ->assertSee('value="'.$this->leona->id.'" selected', false)
            ->assertSee('name="back_to_person"', false);

        $this->actingAs($this->admin)->post('/work', ['kind' => 'task', 'title' => 'Weekly check-in prep', 'assignee_id' => $this->leona->id, 'back_to_person' => '1'])
            ->assertRedirect(route('people.show', $this->leona));
    }

    public function test_entry_points_lead_to_the_shared_form(): void
    {
        $this->actingAs($this->admin)->get('/')->assertOk()
            ->assertSee(route('work.create', ['type' => 'routine']), false)
            ->assertSee(route('work.create', ['type' => 'task']), false)
            ->assertDontSee('class="card task-form"', false);
        $this->actingAs($this->admin)->get('/routines/create')->assertRedirect(route('work.create', ['type' => 'routine']));
    }

    public function test_staff_add_tasks_for_anyone_but_not_routines(): void
    {
        $this->actingAs($this->leona)->get('/')->assertOk()
            ->assertSee(route('work.create', ['type' => 'task']), false)
            ->assertSee('+ New task')
            ->assertDontSee('+ New task or routine')
            ->assertDontSee(route('work.create', ['type' => 'routine']), false);

        // The form is task-only: no type picker, no routine fields, no whole-team option.
        $this->actingAs($this->leona)->get('/work/new?type=routine')->assertOk()
            ->assertSee('<h1 class="form-h">New task', false)
            ->assertSee('name="kind" value="task"', false)
            ->assertDontSee('value="routine"', false)
            ->assertDontSee('name="cadence"', false)
            ->assertDontSee('name="brands[]"', false)
            ->assertDontSee('Whole team')
            ->assertSee('name="due_on"', false)
            ->assertSee('Marc Weinstein');

        $this->actingAs($this->leona)->post('/work', ['kind' => 'task', 'title' => 'Review the Neurolux landing page', 'assignee_id' => $this->admin->id, 'due_on' => '2026-10-22'])
            ->assertRedirect(route('dashboard').'#tasks');
        $task = Task::where('title', 'Review the Neurolux landing page')->firstOrFail();
        $this->assertSame($this->admin->id, $task->assignee_id);
        $this->assertSame($this->leona->id, $task->created_by);
        $this->assertSame($this->leona->id, $task->assigned_by);
        $this->assertFalse((bool) $task->hot);

        $this->actingAs($this->leona)->post('/work', ['kind' => 'routine', 'title' => 'Sneaky routine', 'cadence' => 'daily', 'brands' => ['all']])->assertForbidden();
        $this->actingAs($this->leona)->post('/routines', ['title' => 'Sneaky routine', 'cadence' => 'daily', 'brands' => ['all']])->assertForbidden();
        $this->assertFalse(Routine::where('title', 'Sneaky routine')->exists());

        // A staff page link can't send them back to an admin-only page.
        $this->actingAs($this->leona)->post('/work', ['kind' => 'task', 'title' => 'Prep notes', 'assignee_id' => $this->leona->id, 'back_to_person' => '1'])
            ->assertRedirect(route('dashboard').'#tasks');
    }
}
