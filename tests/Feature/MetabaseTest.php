<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Metabase\Metabase;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MetabaseTest extends TestCase
{
    use RefreshDatabase;

    private const MB = 'https://metrics.39dg.net';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        Http::preventStrayRequests();
        config(['metabase.api_key' => null, 'metabase.url' => self::MB, 'revenue.viewers' => []]);
    }

    private function connect(): void
    {
        config(['metabase.api_key' => 'mb_secret_key_123']);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function member(): User
    {
        return User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private static function answer(array $cols, array $rows): array
    {
        return ['status' => 'completed', 'row_count' => count($rows), 'data' => ['rows' => $rows, 'cols' => array_map(fn ($c) => ['name' => $c[0], 'display_name' => $c[0], 'base_type' => $c[1]], $cols)]];
    }

    public function test_only_admins_and_revenue_viewers_see_it_and_admins_get_setup_steps_first(): void
    {
        $member = $this->member();
        $this->actingAs($member)->get('/metabase')->assertForbidden();
        $this->actingAs($this->admin())->get('/metabase')->assertOk()->assertSee('Connect Metabase')->assertSee('METABASE_API_KEY');

        // Once connected, staff still can't see it; a revenue viewer can.
        $this->connect();
        Http::fake([self::MB.'/api/collection/root/items*' => Http::response(['data' => []])]);
        $this->actingAs($member)->get('/metabase')->assertForbidden();
        $this->actingAs($member)->get('/')->assertOk()->assertDontSee('href="'.route('metabase').'"', false);

        config(['revenue.viewers' => [strtolower($member->email)]]);
        $this->actingAs($member)->get('/metabase')->assertOk()->assertDontSee('METABASE_API_KEY');
        $this->actingAs($member)->get('/')->assertOk()->assertSee('href="'.route('metabase').'"', false);
    }

    public function test_browsing_lists_collections_dashboards_and_questions_with_the_key_in_a_header(): void
    {
        $this->connect();
        Http::fake([
            self::MB.'/api/collection/root/items*' => Http::response(['total' => 3, 'data' => [
                ['id' => 7, 'model' => 'card', 'name' => 'Revenue by month', 'description' => 'GA4 and store', 'display' => 'line'],
                ['id' => 3, 'model' => 'collection', 'name' => 'Marketing'],
                ['id' => 2, 'model' => 'dashboard', 'name' => 'Weekly KPIs'],
                ['id' => 9, 'model' => 'snippet', 'name' => 'Ignored'],
            ]]),
            self::MB.'/api/search*' => Http::response(['data' => [['id' => 7, 'model' => 'card', 'name' => 'Revenue by month', 'collection' => ['name' => 'Finance']]]]),
        ]);
        $admin = $this->admin();

        $html = $this->actingAs($admin)->get('/metabase')->assertOk()
            ->assertSee('Marketing')->assertSee('Weekly KPIs')->assertSee('Revenue by month')->assertDontSee('Ignored')
            ->assertSee(route('metabase.question', 7), false)->assertSee(route('metabase.dashboard', 2), false)->assertSee(route('metabase', ['c' => 3]), false)
            ->assertDontSee('mb_secret_key_123')->getContent();
        // Folders first, then dashboards, then questions.
        $this->assertTrue(strpos($html, 'Marketing') < strpos($html, 'Weekly KPIs') && strpos($html, 'Weekly KPIs') < strpos($html, 'Revenue by month'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/collection/root/items') && str_contains($r->url(), 'models=card&models=dataset')
            && $r->hasHeader('x-api-key', 'mb_secret_key_123'));

        $this->actingAs($admin)->get('/metabase?q=revenue')->assertOk()->assertSee('Results for “revenue”')->assertSee('Finance');
        // Lists are cached for a few minutes.
        $this->actingAs($admin)->get('/metabase')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_a_question_is_drawn_from_its_result_and_never_cached(): void
    {
        $this->connect();
        Http::fake([
            self::MB.'/api/card/7' => Http::response(['id' => 7, 'name' => 'Revenue by month', 'display' => 'line', 'collection' => ['id' => 3, 'name' => 'Marketing'],
                'dataset_query' => ['type' => 'native', 'database' => 2, 'native' => ['query' => 'select month, revenue from monthly']]]),
            self::MB.'/api/card/7/query' => Http::response(self::answer([['Month', 'type/Date'], ['Revenue', 'type/Float']],
                [['2026-08-01T00:00:00Z', 101234.5], ['2026-09-01T00:00:00Z', 112000.25], ['2026-10-01T00:00:00Z', 98000]]), 202),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/metabase/question/7')->assertOk()
            ->assertSee('Revenue by month')->assertSee('class="trend"', false)->assertSee('Sep ’26')->assertSee('112,000.3')
            ->assertSee('Edit as a new query')->assertSee(self::MB.'/question/7', false);
        $this->actingAs($admin)->get('/metabase/question/7?as=table')->assertOk()->assertDontSee('class="trend"', false)->assertSee('Aug 1, 2026');
        Http::assertSentCount(4);
        $this->assertFalse(collect(array_keys(Cache::get('mb:keys', [])))->contains(fn ($k) => str_contains($k, 'card')));
    }

    public function test_a_dashboard_runs_its_cards_together(): void
    {
        $this->connect();
        Http::fake([
            self::MB.'/api/dashboard/2' => Http::response(['id' => 2, 'name' => 'Weekly KPIs', 'parameters' => [['id' => 'p1']], 'dashcards' => [
                ['id' => 11, 'card_id' => 5, 'row' => 0, 'col' => 0, 'size_x' => 4, 'card' => ['name' => 'Orders this week', 'display' => 'scalar']],
                ['id' => 12, 'card_id' => 6, 'row' => 1, 'col' => 0, 'size_x' => 12, 'card' => ['name' => 'Orders by brand', 'display' => 'bar']],
                ['id' => 13, 'card_id' => null, 'row' => 2, 'col' => 0, 'size_x' => 12, 'visualization_settings' => ['text' => 'Numbers from the store.']],
                ['id' => 14, 'card_id' => 8, 'row' => 3, 'col' => 0, 'size_x' => 6, 'card' => ['name' => 'Broken card', 'display' => 'table']],
            ]]),
            self::MB.'/api/dashboard/2/dashcard/11/card/5/query' => Http::response(self::answer([['Orders', 'type/Integer']], [[1834]]), 202),
            self::MB.'/api/dashboard/2/dashcard/12/card/6/query' => Http::response(self::answer([['Brand', 'type/Text'], ['Orders', 'type/Integer']], [['39DollarGlasses', 1500], ['Ocusafe', 334]]), 202),
            self::MB.'/api/dashboard/2/dashcard/14/card/8/query' => Http::response(['status' => 'failed', 'error' => 'relation "nope" does not exist'], 202),
        ]);

        $this->actingAs($this->admin())->get('/metabase/dashboard/2')->assertOk()
            ->assertSee('Weekly KPIs')->assertSee('1,834')->assertSee('class="mb-bars"', false)->assertSee('Ocusafe')
            ->assertSee('Numbers from the store.')->assertSee('relation &quot;nope&quot; does not exist', false)->assertSee('1 filter');
    }

    public function test_sql_queries_run_read_only_and_can_be_saved_to_metabase(): void
    {
        $this->connect();
        $admin = $this->admin();
        Http::fake([
            self::MB.'/api/database' => Http::response(['data' => [['id' => 2, 'name' => 'Store', 'engine' => 'postgres']]]),
            self::MB.'/api/collection/root/items*' => Http::response(['data' => [['id' => 3, 'model' => 'collection', 'name' => 'Marketing']]]),
            self::MB.'/api/dataset' => Http::response(self::answer([['Brand', 'type/Text'], ['Orders', 'type/Integer']], [['Ocusafe', 42]]), 202),
            self::MB.'/api/card' => Http::response(['id' => 55, 'name' => 'Orders by brand'], 200),
        ]);

        $this->actingAs($admin)->get('/metabase/query')->assertOk()->assertSee('Store (postgres)')->assertSee('Marketing');
        $this->actingAs($admin)->post('/metabase/query', ['mode' => 'sql', 'db' => 2, 'sql' => "select brand, count(*) as orders from orders where note = 'delete me' group by 1"])
            ->assertOk()->assertSee('Ocusafe')->assertSee('not saved in OmniVEN');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/dataset') && $r['type'] === 'native' && $r['database'] === 2);

        foreach (["delete from orders", "select 1; drop table orders", "with x as (update orders set a = 1 returning *) select * from x"] as $bad) {
            $this->actingAs($admin)->post('/metabase/query', ['mode' => 'sql', 'db' => 2, 'sql' => $bad])->assertOk()->assertSee('The query didn’t run.');
        }
        // Databases and collections came from the cache; only the good query reached Metabase.
        Http::assertSentCount(3);

        $this->actingAs($admin)->post('/metabase/query/save', ['mode' => 'sql', 'db' => 2, 'sql' => 'select 1', 'name' => '', 'display' => 'auto'])
            ->assertOk()->assertSee('Give the question a name');
        $this->actingAs($admin)->post('/metabase/query/save', ['mode' => 'sql', 'db' => 2, 'sql' => 'select brand from orders', 'name' => 'Orders by brand', 'collection_id' => 3, 'display' => 'bar'])
            ->assertRedirect(route('metabase.question', 55));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/card') && $r->method() === 'POST' && $r['collection_id'] === 3 && $r['display'] === 'bar' && $r['dataset_query']['native']['query'] === 'select brand from orders');
        $this->assertDatabaseHas('activity_logs', ['action' => 'metabase.query', 'description' => 'Ran a SQL query in Metabase']);
        $this->assertDatabaseMissing('activity_logs', ['description' => "select brand, count(*) as orders from orders where note = 'delete me' group by 1"]);
    }

    public function test_the_table_builder_turns_choices_into_a_metabase_query(): void
    {
        $this->connect();
        Http::fake([
            self::MB.'/api/database' => Http::response([['id' => 2, 'name' => 'Store', 'engine' => 'postgres']]),
            self::MB.'/api/collection/root/items*' => Http::response(['data' => []]),
            self::MB.'/api/database/2/metadata' => Http::response(['tables' => [
                ['id' => 40, 'name' => 'orders', 'display_name' => 'Orders', 'schema' => 'public', 'fields' => [
                    ['id' => 401, 'display_name' => 'Total', 'base_type' => 'type/Decimal'],
                    ['id' => 402, 'display_name' => 'Created At', 'base_type' => 'type/DateTime'],
                    ['id' => 403, 'display_name' => 'Email', 'base_type' => 'type/Text', 'visibility_type' => 'sensitive'],
                ]],
                ['id' => 41, 'name' => 'secrets', 'display_name' => 'Secrets', 'visibility_type' => 'hidden', 'fields' => []],
            ]]),
            self::MB.'/api/dataset' => Http::response(self::answer([['Created At', 'type/DateTime'], ['Sum of Total', 'type/Float']], [['2026-09-01T00:00:00Z', 10], ['2026-10-01T00:00:00Z', 12]]), 202),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/metabase/query?mode=simple&db=2&table=40')->assertOk()
            ->assertSee('Total Total')->assertSee('Created At')->assertDontSee('value="403"', false)->assertDontSee('Secrets');
        $this->actingAs($admin)->post('/metabase/query', ['mode' => 'simple', 'db' => 2, 'table' => 40, 'measure' => 'sum:401', 'group' => 402, 'unit' => 'month', 'days' => 90, 'date_field' => 402, 'limit' => 100])
            ->assertOk()->assertSee('class="trend"', false);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/dataset') && $r['type'] === 'query' && $r['query'] === [
            'source-table' => 40, 'aggregation' => [['sum', ['field', 401, null]]], 'limit' => 100,
            'breakout' => [['field', 402, ['temporal-unit' => 'month']]], 'order-by' => [['asc', ['field', 402, ['temporal-unit' => 'month']]]],
            'filter' => ['time-interval', ['field', 402, null], -90, 'day'],
        ]);

        // A field from another table (or a hidden one) can't be slipped in.
        $this->actingAs($admin)->post('/metabase/query', ['mode' => 'simple', 'db' => 2, 'table' => 40, 'measure' => 'sum:999'])->assertOk()->assertSee('Pick the column to add up');
    }

    public function test_failures_are_plain_and_only_admins_see_why(): void
    {
        $this->connect();
        Http::fake([self::MB.'/api/collection/root/items*' => Http::response(['message' => 'Unauthenticated'], 401)]);
        $viewer = $this->member();
        config(['revenue.viewers' => [strtolower($viewer->email)]]);

        $this->actingAs($this->admin())->get('/metabase')->assertOk()->assertSee('didn’t accept the API key');
        Cache::flush();
        $this->actingAs($viewer)->get('/metabase')->assertOk()->assertSee('Can’t reach Metabase right now')->assertDontSee('API key');

        $this->expectException(RuntimeException::class);
        Metabase::assertReadOnly('   ');
    }
}
