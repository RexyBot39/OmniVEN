<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskOutcomeTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private User $other;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $this->me = User::factory()->create(['name' => 'Kaylie Ray', 'email' => 'kaylie@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->other = User::factory()->create(['email' => 'sam@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->task = Task::create(['title' => 'Publish the blue light guide', 'assignee_id' => $this->me->id, 'created_by' => $this->me->id]);
    }

    private function doneList(): string
    {
        $html = $this->actingAs($this->me)->get('/')->assertOk()->getContent();

        return substr($html, strpos($html, 'id="done-list"'));
    }

    public function test_finishing_a_task_opens_its_result_box_and_the_result_shows(): void
    {
        $this->actingAs($this->me)->post("/tasks/{$this->task->id}/toggle")->assertSessionHas('outcome_for', $this->task->id);
        $this->assertMatchesRegularExpression('/<details class="outcome-f"\s+open\s*>/', $this->doneList(), 'box opens right after finishing');
        $this->assertDoesNotMatchRegularExpression('/<details class="outcome-f"\s+open\s*>/', $this->doneList(), 'and only that once');

        $this->actingAs($this->me)->post("/tasks/{$this->task->id}/outcome", [
            'outcome_task' => $this->task->id, 'outcome' => '  Guide live with 3 affiliate links ', 'outcome_url' => 'https://www.39dollarglasses.com/blog/blue-light',
        ])->assertRedirect(route('dashboard').'#tasks')->assertSessionHas('outcome_saved', $this->task->id);
        $this->task->refresh();
        $this->assertSame('Guide live with 3 affiliate links', $this->task->outcome);
        $this->assertSame('task.outcome', ActivityLog::latest('id')->value('action'));

        $done = $this->doneList();
        $this->assertStringContainsString('→ Guide live with 3 affiliate links', $done);
        $this->assertStringContainsString('href="https://www.39dollarglasses.com/blog/blue-light"', $done);
        $this->assertStringContainsString('Edit result', $done);

        // Both blank removes it.
        $this->actingAs($this->me)->post("/tasks/{$this->task->id}/outcome", ['outcome' => '', 'outcome_url' => ''])->assertSessionHas('status', 'Result removed.');
        $this->assertNull($this->task->refresh()->outcome);
    }

    public function test_only_http_links_only_finished_tasks_and_only_whoever_can_tick_it(): void
    {
        $this->actingAs($this->me)->post("/tasks/{$this->task->id}/outcome", ['outcome' => 'Too soon'])->assertStatus(422);
        $this->task->update(['completed_at' => now(), 'completed_by' => $this->me->id]);

        $this->actingAs($this->me)->from('/')->post("/tasks/{$this->task->id}/outcome", ['outcome_task' => $this->task->id, 'outcome_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('outcome_url');
        $this->assertNull($this->task->refresh()->outcome_url);

        $this->actingAs($this->other)->post("/tasks/{$this->task->id}/outcome", ['outcome' => 'Not mine'])->assertForbidden();
        $this->assertStringNotContainsString('tasks/'.$this->task->id.'/outcome', $this->actingAs($this->other)->get('/')->getContent());
    }

    public function test_results_appear_in_the_monthly_report_and_on_the_launch(): void
    {
        $admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->task->update(['completed_at' => '2026-10-05 14:00:00', 'completed_by' => $this->me->id, 'outcome_url' => 'https://www.instagram.com/p/abc123/']);
        Task::create(['title' => 'No result here', 'assignee_id' => $this->me->id, 'completed_at' => '2026-10-06 14:00:00', 'completed_by' => $this->me->id]);

        $html = $this->actingAs($admin)->get('/reports/monthly')->assertOk()->getContent();
        $this->assertStringContainsString('What we shipped', $html);
        $this->assertStringContainsString('1 finished task with a result', $html);
        $this->assertStringContainsString('instagram.com/p/abc123/</a>', $html);
        $this->assertStringNotContainsString('No result here', substr($html, strpos($html, 'What we shipped')));

        $this->assertStringNotContainsString('What we shipped', $this->actingAs($admin)->get('/reports/monthly?month=2026-09')->getContent());
    }
}
