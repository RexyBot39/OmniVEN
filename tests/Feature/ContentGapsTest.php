<?php

namespace Tests\Feature;

use App\Models\AiCheck;
use App\Models\AiQuestion;
use App\Models\Brand;
use App\Models\ContentGap;
use App\Models\Task;
use App\Models\User;
use App\Support\Seo\ContentGaps;
use App\Support\Seo\SearchConsole;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentGapsTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;
    private User $sam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        config(['revenue.ga4.credentials' => json_encode(['client_email' => 'sa@x', 'private_key' => 'k']), 'search_console.sites' => ['39dg' => 'sc-domain:39dollarglasses.com']]);
        Cache::forever('seo:last', now()->timestamp);
        $this->kay = User::factory()->create(['name' => 'Kay Lee', 'email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->sam = User::factory()->create(['name' => 'Sam Ortiz', 'email' => 'sam@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function summary(array $rows): void
    {
        Cache::forever('seo:39dg', ['gap_rows' => array_map(fn ($r) => array_combine(['query', 'page', 'clicks', 'impressions', 'position'], $r), $rows)]);
    }

    public function test_searches_are_sorted_into_kinds_with_steps(): void
    {
        $page = 'https://www.39dollarglasses.com/progressive-lenses';
        $this->summary([
            ['cheap progressive glasses', $page, 40, 4000, 3.2],          // top 5, 1% clicked → few clicks
            ['progressive lenses online', $page, 30, 900, 11.4],          // position 11 → almost there
            ['how long do progressive lenses last', null, 0, 80, 24.0],    // question past the top 10
            ['glasses frames', $page, 3, 50, 35.0],                        // not a question, too far → nothing
            ['blue light glasses', $page, 300, 3000, 2.0],                 // 10% clicked → fine
        ]);
        $gaps = ContentGaps::open()->keyBy('query');

        $this->assertSame(['cheap progressive glasses', 'progressive lenses online', 'how long do progressive lenses last'], ContentGaps::open()->pluck('query')->all(), 'most promising first');
        $this->assertSame('ctr', $gaps['cheap progressive glasses']['kind']);
        $this->assertSame('Rewrite the Google title and description of /progressive-lenses for “cheap progressive glasses”', $gaps['cheap progressive glasses']['title']);
        $this->assertSame('striking', $gaps['progressive lenses online']['kind']);
        $this->assertSame('question', $gaps['how long do progressive lenses last']['kind']);
        $this->assertSame('39dg', $gaps['progressive lenses online']['brand']->slug);

        $html = $this->actingAs($this->kay)->get('/content-gaps')->assertOk()->getContent();
        $this->assertStringContainsString('“cheap progressive glasses”', $html);
        $this->assertStringContainsString('4,000 searches in 28 days · position 3.2 · 1.0% clicked', $html);
        $this->assertStringContainsString('Few clicks <span>1</span>', $html);
        $this->assertStringNotContainsString('glasses frames', $html);
        $this->assertStringNotContainsString('how long do progressive', $this->actingAs($this->kay)->get('/content-gaps?kind=ctr')->getContent());

        $this->assertTrue(ContentGaps::isQuestion('progressive vs bifocal glasses'));
        $this->assertFalse(ContentGaps::isQuestion('cheap glasses'));
    }

    public function test_a_gap_becomes_a_task_and_leaves_the_queue(): void
    {
        $this->summary([['progressive lenses online', 'https://www.39dollarglasses.com/progressive-lenses', 30, 900, 11.4]]);
        $key = ContentGaps::open()->first()['key'];

        $this->actingAs($this->kay)->from('/content-gaps')->post('/content-gaps/task', ['key' => $key, 'assignee_id' => $this->sam->id, 'due_on' => '2026-10-23'])
            ->assertRedirect('/content-gaps')->assertSessionHas('status', 'Assigned to Sam.');
        $task = Task::latest('id')->first();
        $this->assertSame('Improve /progressive-lenses so it ranks for “progressive lenses online”', $task->title);
        $this->assertStringContainsString('Add a short FAQ', $task->details);
        $this->assertSame($this->sam->id, $task->assignee_id);
        $this->assertSame(Brand::where('slug', '39dg')->value('id'), $task->brand_id);
        $this->assertSame('2026-10-23', $task->due_on->format('Y-m-d'));
        $this->assertSame($task->id, ContentGap::where('key', $key)->value('task_id'));

        $this->assertTrue(ContentGaps::open()->isEmpty());
        $html = $this->actingAs($this->kay)->get('/content-gaps')->getContent();
        $this->assertStringContainsString('No open gaps', $html);
        $this->assertStringContainsString('Task for Sam', $html);
    }

    public function test_set_aside_and_restore(): void
    {
        $this->summary([['progressive lenses online', null, 30, 900, 11.4]]);
        $key = ContentGaps::open()->first()['key'];
        $this->actingAs($this->kay)->post('/content-gaps/dismiss', ['key' => $key]);
        $this->assertTrue(ContentGaps::open()->isEmpty());
        $this->assertStringContainsString('Set aside by Kay', $this->actingAs($this->kay)->get('/content-gaps')->getContent());

        $this->actingAs($this->kay)->delete('/content-gaps/'.ContentGap::first()->id);
        $this->assertCount(1, ContentGaps::open());
        $this->actingAs($this->kay)->post('/content-gaps/task', ['key' => str_repeat('a', 40), 'assignee_id' => $this->kay->id])->assertSessionHas('status');
        $this->assertSame(0, Task::where('title', 'like', '%progressive%')->count(), 'unknown keys make nothing');
    }

    public function test_ai_questions_nobody_mentioned_us_for_become_gaps(): void
    {
        $miss = AiQuestion::create(['question' => 'Where can I buy cheap prescription glasses online?']);
        $hit = AiQuestion::create(['question' => 'Best place for blue light glasses?']);
        foreach (['chatgpt', 'perplexity'] as $e) {
            AiCheck::create(['ai_question_id' => $miss->id, 'engine' => $e, 'month' => '2026-10-01', 'result' => 'absent']);
        }
        AiCheck::create(['ai_question_id' => $hit->id, 'engine' => 'chatgpt', 'month' => '2026-10-01', 'result' => 'absent']);
        AiCheck::create(['ai_question_id' => $hit->id, 'engine' => 'perplexity', 'month' => '2026-10-01', 'result' => 'mentioned']);
        AiCheck::create(['ai_question_id' => $hit->id, 'engine' => 'chatgpt', 'month' => '2026-09-01', 'result' => 'absent']);

        $gaps = ContentGaps::open();
        $this->assertSame(['Where can I buy cheap prescription glasses online?'], $gaps->pluck('query')->all());
        $this->assertSame('ai', $gaps[0]['kind']);
        $this->assertSame('Checked in October: ChatGPT, Perplexity', $gaps[0]['stats']);
        $this->assertStringContainsString('AI leaves us out', $this->actingAs($this->kay)->get('/content-gaps')->getContent());
    }

    public function test_search_console_sync_collects_gap_rows_with_their_page(): void
    {
        $pk = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pk, $pem);
        config(['revenue.ga4.credentials' => json_encode(['client_email' => 'sa@x', 'private_key' => $pem])]);
        Cache::forget('revenue:ga4:token');
        Http::preventStrayRequests();
        Http::fake(function (Request $r) {
            if ($r->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 't', 'expires_in' => 3600]);
            }
            $row = fn ($keys, $c, $i, $p) => ['keys' => $keys, 'clicks' => $c, 'impressions' => $i, 'position' => $p];

            return Http::response(['rows' => match (implode(',', $r['dimensions'])) {
                'query' => [$row(['39dollarglasses'], 900, 2000, 1.1), $row(['progressive lenses online'], 30, 900, 11.4), $row(['rare search'], 0, 5, 40.0)],
                'query,page' => [$row(['progressive lenses online', 'https://www.39dollarglasses.com/a'], 1, 100, 30.0), $row(['progressive lenses online', 'https://www.39dollarglasses.com/progressive-lenses'], 29, 800, 10.0)],
                default => [],
            }]);
        });
        $this->assertTrue(SearchConsole::refresh());
        $rows = SearchConsole::summary('39dg')['gap_rows'];
        $this->assertSame([['query' => 'progressive lenses online', 'page' => 'https://www.39dollarglasses.com/progressive-lenses', 'clicks' => 30, 'impressions' => 900, 'position' => 11.4]], $rows, 'branded and rare searches left out; the page showing most wins');
    }

    public function test_page_explains_where_gaps_come_from_when_nothing_is_connected(): void
    {
        config(['search_console.sites' => []]);
        $this->actingAs($this->kay)->get('/content-gaps')->assertOk()->assertSee('Gaps come from two places');
    }
}
