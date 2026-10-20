<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\SeoAudit;
use App\Models\SeoKeyword;
use App\Models\User;
use App\Support\Seo\DataForSeo;
use App\Support\Seo\RankTracker;
use App\Support\Seo\SeoSuite;
use App\Support\Seo\SiteAudit;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SeoSuiteTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.dataforseo.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        Http::preventStrayRequests();
        config(['dataforseo.login' => null, 'dataforseo.password' => null, 'dataforseo.domains' => [], 'dataforseo.backlinks' => false]);
    }

    private function connect(): void
    {
        config(['dataforseo.login' => 'api@example.com', 'dataforseo.password' => 's3cret-api-pass']);
    }

    private function member(): User
    {
        return User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    /** A DataForSEO envelope with one task and one result. */
    private static function ok(array $result, float $cost = 0.01, int $taskCode = 20000): array
    {
        return ['status_code' => 20000, 'status_message' => 'Ok.', 'cost' => $cost,
            'tasks' => [['id' => 'task-1', 'status_code' => $taskCode, 'status_message' => 'Ok.', 'cost' => $cost, 'result' => $result === [] ? null : [$result]]]];
    }

    private static function kd(string $kw, int $vol, int $kd): array
    {
        return ['keyword' => $kw, 'keyword_info' => ['search_volume' => $vol, 'cpc' => 1.25, 'monthly_searches' => [['year' => 2026, 'month' => 9, 'search_volume' => $vol]]],
            'keyword_properties' => ['keyword_difficulty' => $kd], 'search_intent_info' => ['main_intent' => 'transactional']];
    }

    public function test_it_is_hidden_from_staff_until_dataforseo_is_set_up_and_admins_see_the_steps(): void
    {
        $member = $this->member();
        $this->actingAs($member)->get('/seo-suite')->assertForbidden();
        $this->actingAs($member)->get('/')->assertOk()->assertDontSee('href="'.route('seo-suite.rankings').'"', false);

        $this->actingAs($this->admin())->get('/seo-suite')->assertOk()
            ->assertSee('Connect DataForSEO to turn on the SEO suite')
            ->assertSee('DATAFORSEO_LOGIN')
            ->assertDontSee('Loading from DataForSEO');
    }

    public function test_once_connected_staff_get_the_seo_menu_and_every_page_but_backlinks(): void
    {
        $this->connect();
        Http::fake(['*' => Http::response(self::ok([]))]);
        $member = $this->member();

        $home = $this->actingAs($member)->get('/')->assertOk();
        $home->assertSee('href="'.route('seo-suite').'"', false)->assertSee('href="'.route('seo-suite.audit').'"', false)
            ->assertDontSee('href="'.route('seo-suite.backlinks').'"', false);
        foreach (['seo-suite', 'seo-suite.keywords', 'seo-suite.rankings', 'seo-suite.competitors', 'seo-suite.audit', 'seo-suite.ai'] as $route) {
            $this->actingAs($member)->get(route($route))->assertOk()->assertSee('39DollarGlasses')->assertDontSee('DATAFORSEO_LOGIN');
        }
        $this->actingAs($member)->get(route('seo-suite.backlinks'))->assertForbidden();

        config(['dataforseo.backlinks' => true]);
        $this->actingAs($member)->get(route('seo-suite.backlinks'))->assertOk();
    }

    public function test_brands_are_measured_on_their_own_websites_unless_domains_are_set(): void
    {
        // Neurolux and Lens Replacement are pages on 39DollarGlasses, so they aren't measured on their own.
        $this->assertSame(['39dg' => '39dollarglasses.com', 'ocusafe' => 'ocusafe.com', 'ocusleep' => 'ocusleep.com', 'trailview' => 'trailview.co'], SeoSuite::domains());

        config(['dataforseo.domains' => ['ocusafe' => 'ocusafe.com']]);
        $this->assertSame(['ocusafe'], SeoSuite::brands()->keys()->all());
    }

    public function test_the_overview_reads_rankings_history_keywords_and_pages(): void
    {
        $this->connect();
        Http::fake([
            self::API.'/v3/dataforseo_labs/google/domain_rank_overview/live' => Http::response(self::ok(['items' => [['metrics' => ['organic' => [
                'count' => 1200, 'etv' => 5400.6, 'estimated_paid_traffic_cost' => 8100, 'pos_1' => 40, 'pos_2_3' => 60, 'pos_4_10' => 200, 'pos_11_20' => 300,
                'pos_21_30' => 100, 'pos_91_100' => 5, 'is_new' => 12, 'is_up' => 30, 'is_down' => 20, 'is_lost' => 8]]]]])),
            self::API.'/v3/dataforseo_labs/google/historical_rank_overview/live' => Http::response(self::ok(['items' => [
                ['year' => 2026, 'month' => 9, 'metrics' => ['organic' => ['count' => 1150, 'etv' => 5000]]],
                ['year' => 2026, 'month' => 8, 'metrics' => ['organic' => ['count' => 1100, 'etv' => 4800]]],
            ]])),
            self::API.'/v3/dataforseo_labs/google/ranked_keywords/live' => Http::response(self::ok(['total_count' => 1200, 'items' => [
                ['keyword_data' => self::kd('cheap glasses online', 9900, 65), 'ranked_serp_element' => ['serp_item' => ['rank_group' => 3, 'url' => 'https://www.39dollarglasses.com/eyeglasses', 'etv' => 950.2]]],
            ]])),
            self::API.'/v3/dataforseo_labs/google/relevant_pages/live' => Http::response(self::ok(['items' => [
                ['page_address' => 'https://www.39dollarglasses.com/eyeglasses', 'metrics' => ['organic' => ['etv' => 1900, 'count' => 340]]],
            ]])),
        ]);
        $brand = Brand::where('slug', '39dg')->first();

        $this->assertTrue(SeoSuite::refresh('overview', $brand));
        $d = SeoSuite::report('overview', $brand)['data'];
        $this->assertSame(1200, $d['now']['keywords']);
        $this->assertSame(100, $d['now']['top3']);
        $this->assertSame(300, $d['now']['top10']);
        $this->assertSame(105, $d['now']['p21_100']);
        $this->assertSame(['2026-08', '2026-09'], array_column($d['months'], 'month'));
        $this->assertSame(3, $d['keywords'][0]['position']);
        $this->assertSame('Hard', SeoSuite::difficulty($d['keywords'][0]['difficulty'])[0]);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'ranked_keywords') && $r[0]['target'] === '39dollarglasses.com' && $r[0]['location_code'] === 2840
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('api@example.com:s3cret-api-pass')));

        $this->actingAs($this->member())->get('/seo-suite?brand=39dg')->assertOk()
            ->assertSee('Searches we rank for')->assertSee('1,200')->assertSee('cheap glasses online')->assertSee('/eyeglasses')->assertSee('Track');
        $this->assertSame(4, DB::table('dataforseo_calls')->count());
    }

    public function test_keyword_research_is_cached_a_week_and_rows_can_be_tracked(): void
    {
        $this->connect();
        Http::fake([self::API.'/v3/dataforseo_labs/google/keyword_ideas/live' => Http::response(self::ok(['items' => [
            self::kd('safety glasses prescription', 2400, 22), self::kd('prescription safety glasses', 5400, 41),
        ]]))]);
        $member = $this->member();

        $this->actingAs($member)->get('/seo-suite/keywords?brand=ocusafe&q=Safety+Glasses')->assertOk()
            ->assertSee('2 searches for “Safety Glasses”')->assertSee('prescription safety glasses')->assertSee('Easy')->assertSee('Ready to buy');
        $this->actingAs($member)->get('/seo-suite/keywords?brand=ocusafe&q=safety+glasses')->assertOk();
        Http::assertSentCount(1);

        $this->actingAs($member)->post('/seo-suite/rankings', ['brand' => 'ocusafe', 'keywords' => 'prescription safety glasses'])->assertRedirect();
        $this->assertDatabaseHas('seo_keywords', ['keyword' => 'prescription safety glasses', 'created_by' => $member->id]);
    }

    public function test_rank_tracking_queues_checks_collects_positions_and_shows_the_change(): void
    {
        $this->connect();
        $brand = Brand::where('slug', 'ocusafe')->first();
        $this->assertSame(2, RankTracker::add($brand, ['Safety Glasses ', 'safety glasses', 'z87 glasses'], null));
        $kws = SeoKeyword::where('brand_id', $brand->id)->with('brand')->get();

        Http::fake([
            self::API.'/v3/serp/google/organic/task_post' => fn (Request $r) => Http::response(['status_code' => 20000, 'cost' => 0.0012, 'tasks' => array_map(
                fn ($t, $i) => ['id' => 'serp-'.$t['tag'], 'status_code' => 20100, 'status_message' => 'Task Created.', 'data' => ['tag' => $t['tag']]], $r->data(), array_keys($r->data()))]),
            self::API.'/v3/serp/google/organic/task_get/advanced/*' => fn (Request $r) => Http::response(self::ok(['items' => [
                ['type' => 'paid', 'domain' => 'www.ocusafe.com', 'rank_group' => 1],
                ['type' => 'organic', 'domain' => 'www.zenni.com', 'rank_group' => 1, 'url' => 'https://www.zenni.com/'],
                ['type' => 'organic', 'domain' => 'www.ocusafe.com', 'rank_group' => str_contains($r->url(), 'serp-'.$kws[0]->id) ? 4 : 2, 'url' => 'https://www.ocusafe.com/safety-glasses'],
            ]], 0)),
        ]);

        $this->assertSame(2, RankTracker::queue($kws, null));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'task_post') && $r[0]['stop_crawl_on_match'][0]['match_value'] === 'ocusafe.com' && $r[0]['depth'] === 30);
        $this->assertSame(2, RankTracker::collect());
        $first = $kws[0]->fresh();
        $this->assertSame(4, $first->position);
        $this->assertSame('https://www.ocusafe.com/safety-glasses', $first->url);
        $this->assertNull($first->task_id);
        $this->assertDatabaseHas('seo_rank_checks', ['seo_keyword_id' => $first->id, 'position' => 4]);

        // The next check finds it two places higher.
        $this->travel(8)->days();
        RankTracker::record($first, 2, $first->url);
        $first->refresh();
        $this->assertSame(2, $first->change());
        $this->assertSame(2, $first->best_position);

        $this->actingAs($this->member())->get('/seo-suite/rankings?brand=ocusafe')->assertOk()
            ->assertSee('safety glasses')->assertSee('▲ 2')->assertSee('In the top 3');
    }

    public function test_the_monthly_budget_stops_billed_calls_before_they_are_sent(): void
    {
        $this->connect();
        config(['dataforseo.monthly_budget' => 5]);
        Http::fake();
        DB::table('dataforseo_calls')->insert(['path' => '/v3/x', 'cost' => 5.01, 'created_at' => now()]);

        try {
            DataForSeo::post('/v3/dataforseo_labs/google/keyword_ideas/live', ['keywords' => ['x']]);
            $this->fail('Expected the budget to stop the call.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('budget ($5.00) is used up', $e->getMessage());
        }
        Http::assertNothingSent();

        // Last month's spending doesn't count.
        DB::table('dataforseo_calls')->update(['created_at' => now()->subMonth()->startOfMonth()]);
        $this->assertFalse(DataForSeo::overBudget());
    }

    public function test_failures_are_explained_without_showing_the_login(): void
    {
        $this->connect();
        Http::fake([
            self::API.'/v3/dataforseo_labs/google/competitors_domain/live' => Http::response(['status_code' => 40100, 'status_message' => 'You are not authorized'], 401),
            self::API.'/v3/backlinks/*' => Http::response(['status_code' => 20000, 'cost' => 0, 'tasks' => [['status_code' => 40204, 'status_message' => 'Access denied. Visit Plans and Subscriptions']]]),
        ]);
        $brand = Brand::where('slug', '39dg')->first();

        $this->assertFalse(SeoSuite::refresh('competitors', $brand));
        $error = SeoSuite::report('competitors', $brand)['error'];
        $this->assertStringContainsString('didn’t accept the login', $error);
        $this->assertStringNotContainsString('s3cret-api-pass', $error);

        $this->assertFalse(SeoSuite::refresh('backlinks', $brand));
        $this->assertStringContainsString('Backlinks needs its own subscription', SeoSuite::report('backlinks', $brand)['error']);

        // Staff see that it failed; only admins see why.
        $this->actingAs($this->member())->get('/seo-suite/competitors?brand=39dg')->assertOk()->assertSee('Can’t read DataForSEO yet')->assertDontSee('didn’t accept the login');
        $this->actingAs($this->admin())->get('/seo-suite/competitors?brand=39dg')->assertOk()->assertSee('didn’t accept the login')->assertSee('Refresh now');
        // A failed refresh waits an hour before it's tried again on a page view, and a failure stops the rest of that report.
        Http::assertSentCount(2);
    }

    public function test_competitors_and_the_keyword_gap(): void
    {
        $this->connect();
        Http::fake([
            self::API.'/v3/dataforseo_labs/google/competitors_domain/live' => Http::response(self::ok(['items' => [
                ['domain' => '39dollarglasses.com', 'intersections' => 9000],
                ['domain' => 'zennioptical.com', 'intersections' => 820, 'avg_position' => 7.36, 'full_domain_metrics' => ['organic' => ['count' => 90000, 'etv' => 400000.4]]],
            ]])),
            self::API.'/v3/dataforseo_labs/google/domain_intersection/live' => Http::response(self::ok(['items' => [
                ['keyword_data' => self::kd('tinted glasses', 6600, 18), 'first_domain_serp_element' => ['rank_group' => 2, 'url' => 'https://www.zennioptical.com/tinted']],
            ]])),
        ]);
        $brand = Brand::where('slug', '39dg')->first();
        $this->assertTrue(SeoSuite::refresh('competitors', $brand));
        $this->assertSame(['zennioptical.com'], array_column(SeoSuite::report('competitors', $brand)['data'], 'domain'));

        $this->actingAs($this->member())->get('/seo-suite/competitors?brand=39dg&vs=https://www.ZenniOptical.com/')->assertOk()
            ->assertSee('zennioptical.com')->assertSee('7.4')->assertSee('tinted glasses')->assertSee('/tinted');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'domain_intersection') && $r[0]['target_1'] === 'zennioptical.com' && $r[0]['target_2'] === '39dollarglasses.com' && $r[0]['intersections'] === false);
    }

    public function test_site_audit_crawls_then_stores_the_score_issues_and_worst_pages(): void
    {
        $this->connect();
        Http::fake([
            self::API.'/v3/on_page/task_post' => Http::response(['status_code' => 20000, 'cost' => 0.06, 'tasks' => [['id' => 'crawl-1', 'status_code' => 20100, 'status_message' => 'Task Created.']]]),
            self::API.'/v3/on_page/summary/crawl-1' => Http::sequence()
                ->push(self::ok(['crawl_progress' => 'in_progress', 'crawl_status' => ['pages_crawled' => 40]], 0))
                ->push(self::ok(['crawl_progress' => 'finished', 'crawl_status' => ['pages_crawled' => 310], 'domain_info' => ['total_pages' => 310],
                    'page_metrics' => ['onpage_score' => 87.456, 'broken_links' => 3, 'duplicate_title' => 12, 'checks' => ['no_description' => 25, 'no_h1_tag' => 0, 'mystery_check' => 4]]], 0)),
            self::API.'/v3/on_page/pages' => Http::response(self::ok(['items' => [
                ['url' => 'https://www.ocusafe.com/old', 'status_code' => 404, 'onpage_score' => 20, 'meta' => ['title' => 'Old page'], 'checks' => ['is_4xx_code' => true, 'no_description' => true, 'seo_friendly_url' => true]],
            ]], 0)),
        ]);
        $brand = Brand::where('slug', 'ocusafe')->first();
        $member = $this->member();

        $this->actingAs($member)->post('/seo-suite/audit', ['brand' => 'ocusafe'])->assertRedirect()->assertSessionHas('status');
        $audit = SeoAudit::firstOrFail();
        $this->assertSame('crawling', $audit->status);
        $this->assertSame($member->id, $audit->started_by);

        SiteAudit::poll($audit);
        $this->assertSame(40, $audit->fresh()->pages_crawled);
        SiteAudit::poll($audit->fresh());
        $audit->refresh();
        $this->assertSame('done', $audit->status);
        $this->assertSame(87.5, $audit->score);
        $this->assertSame(['broken_links' => 3, 'duplicate_title' => 12, 'no_description' => 25], $audit->summary['issues']);
        $this->assertSame(['is_4xx_code', 'no_description'], $audit->pages[0]['issues']);

        $this->actingAs($member)->get('/seo-suite/audit?brand=ocusafe&issue=no_description')->assertOk()
            ->assertSee('88/100')->assertSee('No meta description')->assertSee('Broken links on the page')->assertSee('/old')->assertDontSee('mystery_check');
        // A second run within the hour is turned away.
        $this->actingAs($member)->post('/seo-suite/audit', ['brand' => 'ocusafe'])->assertSessionHas('error');
    }

    public function test_ai_visibility_reads_mentions_questions_and_share_of_voice(): void
    {
        $this->connect();
        $brand = Brand::where('slug', '39dg')->first();
        Cache::forever('dfs:competitors:39dg', ['at' => now()->timestamp, 'data' => [['domain' => 'zennioptical.com']]]);
        Http::fake([
            self::API.'/v3/ai_optimization/llm_mentions/aggregated_metrics/live' => fn (Request $r) => Http::response(self::ok(['total' => ['platform' => [
                ['type' => 'group_element', 'key' => $r[0]['platform'], 'mentions' => $r[0]['platform'] === 'google' ? 140 : 35, 'ai_search_volume' => 5000],
            ]]], 0.1)),
            self::API.'/v3/ai_optimization/llm_mentions/search/live' => Http::response(self::ok(['items' => [
                ['question' => 'Where can I buy cheap prescription glasses?', 'answer' => '<p>Try 39DollarGlasses…</p>', 'ai_search_volume' => 880,
                    'sources' => [['url' => 'https://www.39dollarglasses.com/', 'domain' => '39dollarglasses.com', 'title' => 'Glasses from $39']]],
            ]], 0.12)),
            self::API.'/v3/ai_optimization/llm_mentions/cross_aggregated_metrics/live' => Http::response(self::ok(['items' => [
                ['key' => '39dollarglasses.com', 'platform' => [['key' => 'google', 'mentions' => 140]]],
                ['key' => 'zennioptical.com', 'platform' => [['key' => 'google', 'mentions' => 300]]],
            ]], 0.1)),
        ]);

        $this->assertTrue(SeoSuite::refresh('ai', $brand));
        $d = SeoSuite::report('ai', $brand)['data'];
        $this->assertSame(['google' => 140, 'chat_gpt' => 35], array_map(fn ($p) => $p['mentions'], $d['platforms']));
        $this->assertSame('Try 39DollarGlasses…', $d['questions'][0]['answer']);
        $this->assertSame(['zennioptical.com', '39dollarglasses.com'], array_column($d['share'], 'domain'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'aggregated_metrics') && $r[0]['platform'] === 'chat_gpt' && $r[0]['location_code'] === 2840);

        $this->actingAs($this->member())->get('/seo-suite/ai?brand=39dg')->assertOk()
            ->assertSee('Google AI Overviews')->assertSee('140')->assertSee('Where can I buy cheap prescription glasses?')->assertSee('(us)');
    }

    public function test_spend_is_shown_to_admins_only_and_the_sync_command_needs_setup(): void
    {
        $this->artisan('omniven:seo-sync')->assertExitCode(1);

        $this->connect();
        Http::fake(['*' => Http::response(self::ok([]))]);
        DB::table('dataforseo_calls')->insert(['path' => '/v3/x', 'cost' => 1.234, 'created_at' => now()]);
        $this->actingAs($this->admin())->get('/seo-suite/keywords')->assertOk()->assertSee('$1.23</b> of the $50 budget', false);
        $this->actingAs($this->member())->get('/seo-suite/keywords')->assertOk()->assertDontSee('of the $50 budget');
    }
}
