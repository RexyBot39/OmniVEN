<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Task;
use App\Models\User;
use App\Support\Jira\Jira;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JiraTest extends TestCase
{
    use RefreshDatabase;

    private const J = 'https://39dg.atlassian.net';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        Http::preventStrayRequests();
        config(['jira.base_url' => null, 'jira.email' => null, 'jira.api_token' => null, 'jira.project' => null]);
    }

    private function connect(): void
    {
        config(['jira.base_url' => self::J, 'jira.email' => 'omniven@39dollarglasses.com', 'jira.api_token' => 'atl_token', 'jira.project' => 'MKT']);
    }

    private function person(array $extra = []): User
    {
        return User::factory()->create($extra + ['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    public function test_the_button_shows_only_once_jira_is_set_up(): void
    {
        $me = $this->person();
        Task::create(['title' => 'Refresh the homepage hero', 'assignee_id' => $me->id]);
        $this->actingAs($me)->get('/')->assertOk()->assertDontSee('Send to Jira');

        $this->connect();
        $this->actingAs($me)->get('/')->assertOk()->assertSee('Send to Jira');
    }

    public function test_sending_a_task_creates_a_ticket_with_its_details_and_shows_it_on_the_card(): void
    {
        $this->connect();
        $me = $this->person();
        $kaylie = $this->person(['email' => 'kaylie@39dollarglasses.com', 'name' => 'Kaylie Smith']);
        $task = Task::create(['title' => 'Draft the Ocusafe holiday email', 'details' => "Two versions.\nOne for B2B.", 'assignee_id' => $kaylie->id,
            'brand_id' => Brand::where('slug', 'ocusafe')->value('id'), 'due_on' => today()->addDays(5), 'hot' => true]);
        Http::fake([
            self::J.'/rest/api/3/user/search*' => Http::response([['accountId' => 'acc-kaylie', 'emailAddress' => 'kaylie@39dollarglasses.com']]),
            self::J.'/rest/api/3/issue' => Http::response(['id' => '10042', 'key' => 'MKT-42', 'self' => self::J.'/rest/api/3/issue/10042'], 201),
        ]);

        $this->actingAs($me)->post("/tasks/{$task->id}/jira")->assertRedirect()->assertSessionHas('status', 'Sent to Jira as MKT-42.');
        $task->refresh();
        $this->assertSame('MKT-42', $task->jira_key);
        $this->assertSame('To Do', $task->jira_status);
        Http::assertSent(function (Request $r) use ($task) {
            if (! str_ends_with($r->url(), '/rest/api/3/issue')) {
                return false;
            }
            $f = $r['fields'];
            $body = json_encode($f['description']);

            return $r->hasHeader('Authorization', 'Basic '.base64_encode('omniven@39dollarglasses.com:atl_token'))
                && $f['project'] === ['key' => 'MKT'] && $f['issuetype'] === ['name' => 'Task'] && $f['summary'] === 'Draft the Ocusafe holiday email'
                && $f['labels'] === ['omniven', 'ocusafe'] && $f['duedate'] === $task->due_on->format('Y-m-d') && $f['assignee'] === ['id' => 'acc-kaylie']
                && str_contains($body, 'One for B2B.') && str_contains($body, 'hardBreak') && str_contains($body, 'Brand: Ocusafe') && str_contains($body, 'Hotlist');
        });
        $this->assertDatabaseHas('activity_logs', ['action' => 'task.jira', 'description' => 'Sent task “Draft the Ocusafe holiday email” to Jira as MKT-42']);

        $this->actingAs($me)->get('/')->assertOk()->assertSee('MKT-42 · To Do')->assertSee(self::J.'/browse/MKT-42', false);
        // Sending twice doesn't make a second ticket.
        $this->actingAs($me)->post("/tasks/{$task->id}/jira")->assertSessionHas('status', 'Already on the Jira board as MKT-42.');
        Http::assertSentCount(2);
    }

    public function test_a_project_without_due_dates_or_that_assignee_still_gets_the_ticket(): void
    {
        $this->connect();
        $me = $this->person();
        $task = Task::create(['title' => 'Check Merchant Center', 'assignee_id' => $me->id, 'due_on' => today()->addDay()]);
        Http::fake([
            self::J.'/rest/api/3/user/search*' => Http::response([['accountId' => 'acc-me']]),
            self::J.'/rest/api/3/issue' => Http::sequence()
                ->push(['errorMessages' => [], 'errors' => ['duedate' => "Field 'duedate' cannot be set. It is not on the appropriate screen, or unknown."]], 400)
                ->push(['id' => '1', 'key' => 'MKT-7'], 201),
        ]);

        $this->actingAs($me)->post("/tasks/{$task->id}/jira")->assertSessionHas('status', 'Sent to Jira as MKT-7.');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/rest/api/3/issue') && ! isset($r['fields']['duedate']) && isset($r['fields']['assignee']));
    }

    public function test_failures_are_explained(): void
    {
        $this->connect();
        $me = $this->person();
        $task = Task::create(['title' => 'X', 'assignee_id' => $me->id]);
        Http::fake([self::J.'/rest/api/3/user/search*' => Http::response([]), self::J.'/rest/api/3/issue' => Http::response(['errorMessages' => ['You do not have permission to create issues in this project.']], 403)]);

        $this->actingAs($me)->post("/tasks/{$task->id}/jira")->assertRedirect()->assertSessionHas('error');
        $this->assertStringContainsString('isn’t allowed', session('error'));
        $this->assertNull($task->fresh()->jira_key);
    }

    public function test_done_in_jira_ticks_the_task_off_and_the_board_shows_the_column(): void
    {
        $this->connect();
        $me = $this->person();
        $done = Task::create(['title' => 'Shipped', 'assignee_id' => $me->id, 'jira_key' => 'MKT-1', 'jira_status' => 'In Progress']);
        $moving = Task::create(['title' => 'Still going', 'assignee_id' => $me->id, 'jira_key' => 'MKT-2', 'jira_status' => 'To Do']);
        Http::fake([self::J.'/rest/api/3/search/jql' => Http::response(['issues' => [
            ['key' => 'MKT-1', 'fields' => ['status' => ['name' => 'Done', 'statusCategory' => ['key' => 'done']]]],
            ['key' => 'MKT-2', 'fields' => ['status' => ['name' => 'In Review', 'statusCategory' => ['key' => 'indeterminate']]]],
        ]])]);

        $this->assertSame(1, Jira::sync());
        $this->assertNotNull($done->fresh()->completed_at);
        $this->assertSame('In Review', $moving->fresh()->jira_status);
        $this->assertNull($moving->fresh()->completed_at);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/search/jql') && str_contains($r['jql'], 'MKT-1') && str_contains($r['jql'], 'MKT-2'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'task.completed', 'description' => 'Completed task “Shipped” (done in Jira as MKT-1)']);

        // Opening the board runs it by itself, but not again within a few minutes.
        Cache::forget('jira:last');
        $this->actingAs($me)->get('/')->assertOk()->assertSee('MKT-2 · In Review');
        Http::assertSentCount(2);
        Jira::syncIfStale();
        $this->assertFalse(Cache::has('jira:busy'));
    }

    public function test_ticking_the_task_moves_the_ticket_to_done(): void
    {
        $this->connect();
        $me = $this->person();
        $task = Task::create(['title' => 'Post the reel', 'assignee_id' => $me->id, 'jira_key' => 'MKT-9', 'jira_status' => 'In Progress']);
        Http::fake([
            self::J.'/rest/api/3/issue/MKT-9/transitions' => fn (Request $r) => $r->method() === 'GET'
                ? Http::response(['transitions' => [
                    ['id' => '21', 'name' => 'Start', 'to' => ['name' => 'In Progress', 'statusCategory' => ['key' => 'indeterminate']]],
                    ['id' => '31', 'name' => 'Finish', 'to' => ['name' => 'Shipped', 'statusCategory' => ['key' => 'done']]],
                ]])
                : Http::response(null, 204),
            self::J.'/rest/api/3/search/jql' => Http::response(['issues' => []]),
        ]);

        $this->actingAs($me)->post("/tasks/{$task->id}/toggle")->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/MKT-9/transitions') && $r['transition'] === ['id' => '31']);
        $this->assertSame('Shipped', $task->fresh()->jira_status);
    }
}
