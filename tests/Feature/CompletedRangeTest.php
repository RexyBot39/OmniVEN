<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletedRangeTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $this->me = User::factory()->create(['email' => 'kaylie@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        foreach (['Recent win' => '2026-10-12 15:00', 'September launch' => '2026-09-10 11:00', 'August cleanup' => '2026-08-03 09:00', 'Last year recap' => '2025-12-30 16:00'] as $title => $at) {
            Task::create(['title' => $title, 'assignee_id' => $this->me->id, 'created_by' => $this->me->id, 'completed_at' => Carbon::parse($at, config('app.timezone')), 'completed_by' => $this->me->id]);
        }
    }

    private function done(array $query = []): string
    {
        $html = $this->actingAs($this->me)->get('/?'.http_build_query($query))->assertOk()->getContent();

        return substr($html, strpos($html, 'id="done-list"'));
    }

    public function test_default_is_the_last_14_days_with_presets_to_go_further(): void
    {
        $done = $this->done();
        $this->assertStringContainsString('Completed in the last 14 days', $done);
        $this->assertStringContainsString('<s>Recent win</s>', $done);
        $this->assertStringNotContainsString('September launch', $done);
        $this->assertStringContainsString('name="done_from"', $done);
        $this->assertStringContainsString('done_from=2026-07-19&amp;done_to=2026-10-16', $done); // Last 90 days
        $this->assertStringContainsString('done_from=2026-09-01&amp;done_to=2026-09-30', $done); // Last month
    }

    public function test_a_date_range_shows_tasks_finished_in_it(): void
    {
        $done = $this->done(['done_from' => '2026-08-01', 'done_to' => '2026-09-30']);
        $this->assertStringContainsString('Completed Aug 1 – Sep 30, 2026', $done);
        $this->assertStringContainsString('<s>September launch</s>', $done);
        $this->assertStringContainsString('<s>August cleanup</s>', $done);
        $this->assertStringNotContainsString('Recent win', $done);
        $this->assertStringContainsString('Back to the last 14 days', $done);
        $this->assertStringContainsString('value="2026-08-01"', $done);
        $this->assertMatchesRegularExpression('#aria-current="true"\s*>Last month</a>#', $this->done(['done_from' => '2026-09-01', 'done_to' => '2026-09-30']));
    }

    public function test_older_years_show_the_year_and_odd_input_is_handled(): void
    {
        $done = $this->done(['done_from' => '2025-12-01', 'done_to' => '2025-12-31']);
        $this->assertStringContainsString('Completed Dec 1 – Dec 31, 2025', $done);
        $this->assertStringContainsString('Dec 30, 2025', $done);

        // Ends the wrong way round are swapped.
        $this->assertStringContainsString('<s>August cleanup</s>', $this->done(['done_from' => '2026-08-31', 'done_to' => '2026-08-01']));
        // Junk dates fall back to the last 14 days.
        $this->assertStringContainsString('Completed in the last 14 days', $this->done(['done_from' => 'yesterday', 'done_to' => '2026-02-31']));
        // Only a start date: from then until today.
        $this->assertStringContainsString('Completed Sep 1 – Oct 16, 2026', $this->done(['done_from' => '2026-09-01']));
        // A single day.
        $this->assertStringContainsString('Completed on Aug 3, 2026', $this->done(['done_from' => '2026-08-03', 'done_to' => '2026-08-03']));
    }

    public function test_a_task_finished_late_on_the_last_day_is_in_the_range(): void
    {
        // 10:30 pm Eastern on Aug 31 is already Sep 1 in UTC; it still belongs to August.
        Task::create(['title' => 'Late-night wrap-up', 'assignee_id' => $this->me->id, 'created_by' => $this->me->id, 'completed_at' => Carbon::parse('2026-08-31 22:30', config('app.timezone')), 'completed_by' => $this->me->id]);
        $this->assertStringContainsString('<s>Late-night wrap-up</s>', $this->done(['done_from' => '2026-08-01', 'done_to' => '2026-08-31']));
        $this->assertStringNotContainsString('Late-night wrap-up', $this->done(['done_from' => '2026-09-01', 'done_to' => '2026-09-30']));
    }
}
