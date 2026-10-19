<?php

namespace Tests\Feature;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Brand;
use App\Models\PostIt;
use App\Models\Task;
use App\Models\User;
use App\Support\Assistant\Assistant;
use App\Support\Assistant\Tools;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $kay;
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        config(['assistant.key' => 'sk-test', 'assistant.model' => 'gpt-6.1-sol', 'assistant.effort' => 'high']);
        $this->admin = User::factory()->create(['name' => 'Marc W', 'email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->kay = User::factory()->create(['name' => 'Kay Lee', 'email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    /** Round 1: the model asks for two lookups. Round 2: it answers. */
    private function fakeOpenAi(string $answer = "## Short answer\nOne overdue task.\n\n<script>alert(1)</script>\n\n- Do it today"): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use ($answer) {
            $this->requests[] = $r;
            $hasOutputs = collect($r['input'])->contains(fn ($i) => ($i['type'] ?? '') === 'function_call_output');

            return Http::response($hasOutputs
                ? ['status' => 'completed', 'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $answer]]]], 'usage' => ['input_tokens' => 3000, 'output_tokens' => 800]]
                : ['status' => 'completed', 'output' => [
                    ['type' => 'reasoning', 'id' => 'rs_1', 'summary' => [], 'encrypted_content' => 'enc-abc'],
                    ['type' => 'function_call', 'id' => 'fc_1', 'call_id' => 'call_1', 'name' => 'overview', 'arguments' => '{}'],
                    ['type' => 'function_call', 'id' => 'fc_2', 'call_id' => 'call_2', 'name' => 'tasks', 'arguments' => '{"status":"overdue"}'],
                ], 'usage' => ['input_tokens' => 2000, 'output_tokens' => 300]]);
        });
    }

    public function test_hidden_until_configured_with_setup_steps_for_admins(): void
    {
        config(['assistant.key' => null]);
        $this->actingAs($this->kay)->get('/assistant')->assertForbidden();
        $this->actingAs($this->admin)->get('/assistant')->assertOk()->assertSee('OPENAI_API_KEY');
        $this->assertStringNotContainsString(route('assistant'), $this->actingAs($this->kay)->get('/')->getContent());
    }

    public function test_a_question_is_answered_with_lookups(): void
    {
        Task::create(['title' => 'Fix the Ocusafe feed', 'assignee_id' => $this->kay->id, 'due_on' => '2026-10-10']);
        PostIt::create(['user_id' => $this->kay->id, 'title' => 'My private sticky words', 'color' => 'yellow']);
        $this->fakeOpenAi();

        $this->actingAs($this->kay)->post('/assistant', ['question' => 'What is overdue?', 'focus' => 'brand:ocusafe'])->assertRedirect();
        $conv = AssistantConversation::first();
        $this->assertSame('What is overdue?', $conv->title);
        $answer = AssistantMessage::where('role', 'assistant')->first();
        if ($answer->status === 'pending') {
            Assistant::answer($answer);
            $answer->refresh();
        }
        $this->assertSame('done', $answer->status, (string) $answer->error);
        $this->assertSame(['overview', 'tasks'], $answer->tools);
        $this->assertSame(5000, $answer->input_tokens);

        [$first, $second] = $this->requests;
        $this->assertSame('https://api.openai.com/v1/responses', $first->url());
        $this->assertSame('Bearer sk-test', $first->header('Authorization')[0]);
        $this->assertSame('gpt-6.1-sol', $first['model']);
        $this->assertSame(['effort' => 'high'], $first['reasoning']);
        $this->assertFalse($first['store']);
        $this->assertSame(['reasoning.encrypted_content'], $first['include']);
        $this->assertStringContainsString('one brand: ocusafe', $first['instructions']);
        $this->assertNotContains('revenue', array_column($first['tools'], 'name'), 'Kay can’t see revenue');

        $items = collect($second['input']);
        $this->assertSame('enc-abc', $items->firstWhere('type', 'reasoning')['encrypted_content'], 'reasoning passed back');
        $out = $items->where('type', 'function_call_output')->keyBy('call_id');
        $this->assertStringContainsString('Fix the Ocusafe feed', $out['call_2']['output']);
        $this->assertStringNotContainsString('My private sticky words', json_encode($this->requests[0]->data()).json_encode($second->data()));

        $html = $this->actingAs($this->kay)->get('/assistant/c/'.$conv->id)->assertOk()->getContent();
        $this->assertStringContainsString('<h2>Short answer</h2>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'raw HTML stripped');
        $this->assertStringContainsString('Looked at: overview, tasks', $html);
        $this->assertStringNotContainsString('tokens', substr($html, strpos($html, 'Looked at')), 'cost only for admins');
    }

    public function test_revenue_tools_follow_permissions(): void
    {
        $this->assertContains('revenue', array_column((new Tools($this->admin))->definitions(), 'name'));
        $this->assertArrayHasKey('error', (new Tools($this->kay))->call('revenue', []));
        $this->assertArrayHasKey('error', (new Tools($this->kay))->call('drop_tables', []));
        $people = (new Tools($this->kay))->call('people', []);
        $this->assertContains('Kay Lee', array_column($people['people'], 'name'));
    }

    public function test_conversations_are_private_and_follow_ups_carry_history(): void
    {
        $this->fakeOpenAi('First answer.');
        $this->actingAs($this->kay)->post('/assistant', ['question' => 'First question']);
        $conv = AssistantConversation::first();
        $pending = AssistantMessage::where('status', 'pending')->first();
        if ($pending) {
            Assistant::answer($pending);
        }

        $this->actingAs($this->admin)->get('/assistant/c/'.$conv->id)->assertNotFound();
        $msg = AssistantMessage::where('role', 'assistant')->first();
        $this->actingAs($this->admin)->get('/assistant/m/'.$msg->id)->assertNotFound();
        $this->actingAs($this->kay)->getJson('/assistant/m/'.$msg->id)->assertOk()->assertJson(['status' => 'done']);

        $this->requests = [];
        $this->actingAs($this->kay)->post('/assistant', ['question' => 'And a follow-up?', 'conversation_id' => $conv->id]);
        $pending = AssistantMessage::where('status', 'pending')->first();
        if ($pending) {
            Assistant::answer($pending);
        }
        $input = collect($this->requests[0]['input']);
        $this->assertSame(['First question', 'First answer.', 'And a follow-up?'], $input->pluck('content')->filter()->values()->all());

        $this->actingAs($this->kay)->delete('/assistant/c/'.$conv->id)->assertRedirect('/assistant');
        $this->assertSame(0, AssistantMessage::count());
    }

    public function test_daily_limit_and_errors_are_explained(): void
    {
        config(['assistant.daily_limit' => 1]);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['error' => ['message' => 'bad key']], 401)]);
        $this->actingAs($this->kay)->post('/assistant', ['question' => 'One']);
        $pending = AssistantMessage::where('status', 'pending')->first();
        if ($pending) {
            Assistant::answer($pending);
        }
        $this->assertStringContainsString('OPENAI_API_KEY', AssistantMessage::where('role', 'assistant')->first()->error);
        $this->actingAs($this->kay)->post('/assistant', ['question' => 'Two'])->assertSessionHasErrors('question');
    }

    public function test_every_lookup_runs_without_errors(): void
    {
        config(['revenue.ga4.credentials' => json_encode(['client_email' => 'x@y', 'private_key' => 'k']), 'revenue.ga4.properties' => ['39dg' => '1']]);
        DB::table('revenue_days')->insert(['brand_id' => Brand::where('slug', '39dg')->value('id'), 'day' => '2026-10-10', 'channel' => 'organic_search', 'source' => 'ga4', 'sessions' => 100, 'orders' => 5, 'revenue_cents' => 50000, 'created_at' => now(), 'updated_at' => now()]);
        Task::create(['title' => 'Done thing', 'assignee_id' => $this->kay->id, 'completed_at' => now()->subDay(), 'completed_by' => $this->kay->id]);
        $tools = new Tools($this->admin);
        foreach ($tools->definitions() as $d) {
            $out = $tools->call($d['name'], $d['name'] === 'tasks' ? ['status' => 'done'] : []);
            $this->assertIsArray($out);
            $this->assertStringNotContainsString('failed inside OmniVEN', json_encode($out), $d['name']);
        }
        $this->assertSame('$500', $tools->call('revenue', ['period' => 'mtd'])['total']['revenue']);
        $this->assertSame('Done thing', $tools->call('tasks', ['status' => 'done', 'person' => 'kay'])['tasks'][0]['title']);
    }
}
