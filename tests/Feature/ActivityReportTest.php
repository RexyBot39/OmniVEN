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

class ActivityReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    private function marc(): User
    {
        return User::firstOrCreate(['email' => 'drmarcweinstein@39dollarglasses.com'], ['name' => 'Marc Weinstein', 'is_admin' => true, 'is_active' => true]);
    }

    public function test_only_marc_and_dr_marlow_can_open_the_report(): void
    {
        $this->actingAs($this->marc())->get('/activity')->assertOk()->assertSee('Weekly usage by person');
        $this->actingAs(User::where('email', 'drmarlow@39dollarglasses.com')->first())->get('/activity')->assertOk();

        // Leona is an admin but not a report viewer.
        $this->actingAs(User::where('email', 'leona.p@39dollarglasses.com')->first())->get('/activity')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/activity')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/activity/export')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/')->assertDontSee('/activity');
    }

    public function test_actions_are_logged_with_timestamps(): void
    {
        $pat = User::factory()->create(['name' => 'Pat Lee']);
        $task = Task::create(['title' => 'Fix GTINs', 'assignee_id' => $pat->id, 'hot' => true]);
        $routine = Routine::where('cadence', 'daily')->first();

        $this->actingAs($pat)->get('/');
        $this->actingAs($pat)->post("/tasks/{$task->id}/toggle");
        $this->actingAs($pat)->post("/routines/{$routine->id}/toggle");
        $this->actingAs($pat)->post('/notes', ['title' => 'Fall sale live']);

        $actions = ActivityLog::where('user_id', $pat->id)->pluck('action')->all();
        $this->assertEqualsCanonicalizing(['visited', 'task.completed', 'routine.done', 'note.posted'], $actions);
        $this->assertNotNull(ActivityLog::where('action', 'task.completed')->first()->created_at);

        $this->actingAs($this->marc())->get('/activity')
            ->assertSee('Completed task “Fix GTINs”')
            ->assertSee('Ticked routine')
            ->assertSee('Pat Lee');
    }

    public function test_visits_are_logged_at_most_hourly(): void
    {
        $pat = User::factory()->create();
        $this->actingAs($pat)->get('/');
        $this->actingAs($pat)->get('/');
        $this->assertSame(1, ActivityLog::where('user_id', $pat->id)->where('action', 'visited')->count());
        $this->travel(61)->minutes();
        $this->actingAs($pat)->withSession(['signed_in_at' => now()->timestamp])->get('/');
        $this->assertSame(2, ActivityLog::where('user_id', $pat->id)->where('action', 'visited')->count());
    }

    public function test_postit_text_stays_private_in_the_log(): void
    {
        $pat = User::factory()->create();
        $this->actingAs($pat)->post('/postits', ['title' => 'Secret plans', 'color' => 'pink']);
        $note = PostIt::first();
        $this->actingAs($pat)->post("/postits/{$note->id}/items", ['body' => 'Ask for a raise']);

        $this->assertSame(0, ActivityLog::where('description', 'like', '%raise%')->orWhere('description', 'like', '%Secret%')->count());
        $this->assertSame(2, ActivityLog::where('user_id', $pat->id)->where('action', 'like', 'postit.%')->count());
    }

    public function test_weekly_summary_counts_and_flags_inactive_people(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(10, 0));
        $pat = User::factory()->create(['name' => 'Pat Active']);
        $sam = User::factory()->create(['name' => 'Sam Quiet']);
        $t1 = Task::create(['title' => 'One', 'assignee_id' => $pat->id]);
        $t2 = Task::create(['title' => 'Two', 'assignee_id' => $pat->id]);
        Task::create(['title' => 'Late one', 'assignee_id' => $sam->id, 'due_on' => today()->subDays(2)]);

        $this->actingAs($pat)->post("/tasks/{$t1->id}/toggle");
        $this->actingAs($pat)->post("/tasks/{$t2->id}/toggle");

        $page = $this->actingAs($this->marc())->get('/activity')->assertOk();
        $page->assertSee('No activity this week:')->assertSee('Sam Quiet (never signed in)');

        $csv = $this->actingAs($this->marc())->get('/activity/export')->streamedContent();
        $line = collect(explode("\n", $csv))->first(fn ($l) => str_contains($l, 'Pat Active'));
        $cols = str_getcsv($line);
        $this->assertSame('1', $cols[4]);   // days active
        $this->assertSame('2', $cols[6]);   // tasks completed
        $samLine = str_getcsv(collect(explode("\n", $csv))->first(fn ($l) => str_contains($l, 'Sam Quiet')));
        $this->assertSame('0', $samLine[4]);
        $this->assertSame('1', $samLine[8]); // overdue
    }

    public function test_previous_weeks_are_browsable_and_log_export_works(): void
    {
        $pat = User::factory()->create(['name' => 'Pat Lee']);
        ActivityLog::create(['user_id' => $pat->id, 'action' => 'task.completed', 'description' => 'Completed task “Old work”', 'created_at' => now()->subWeeks(2)]);

        $weekOf = now()->subWeeks(2)->startOfWeek()->format('Y-m-d');
        $this->actingAs($this->marc())->get('/activity?week='.$weekOf)->assertSee('Old work');
        $this->actingAs($this->marc())->get('/activity')->assertDontSee('Old work');

        $csv = $this->actingAs($this->marc())->get("/activity/export?type=log&week={$weekOf}")->streamedContent();
        $this->assertStringContainsString('Old work', $csv);
        $this->assertStringContainsString('Timestamp (ET)', $csv);
    }
}
