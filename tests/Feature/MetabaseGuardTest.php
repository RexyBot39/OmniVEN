<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Metabase\MetabaseGuard;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The protections that stop OmniVEN being used to copy the Metabase database. */
class MetabaseGuardTest extends TestCase
{
    use RefreshDatabase;

    private const MB = 'https://metrics.39dg.net';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        Http::preventStrayRequests();
        config(['metabase.api_key' => 'k', 'metabase.url' => self::MB, 'revenue.viewers' => [], 'metabase.max_rows' => 200,
            'metabase.daily_queries' => 150, 'metabase.daily_rows' => 5000, 'metabase.sql' => 'admins', 'metabase.queries' => true]);
    }

    private function viewer(): User
    {
        $u = User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        config(['revenue.viewers' => [strtolower($u->email)]]);

        return $u;
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private static function answer(array $cols, array $rows): array
    {
        return ['status' => 'completed', 'row_count' => count($rows), 'data' => ['rows' => $rows,
            'cols' => array_map(fn ($c) => ['name' => $c[0], 'display_name' => $c[0], 'base_type' => $c[1], 'semantic_type' => $c[2] ?? null], $cols)]];
    }

    private function fakeMetabase(array $extra = []): void
    {
        Http::fake($extra + [
            self::MB.'/api/database' => Http::response(['data' => [['id' => 2, 'name' => 'Store', 'engine' => 'postgres']]]),
            self::MB.'/api/collection/root/items*' => Http::response(['data' => []]),
            self::MB.'/api/database/2/metadata' => Http::response(['tables' => [['id' => 40, 'display_name' => 'Customers', 'fields' => [
                ['id' => 1, 'name' => 'email', 'display_name' => 'Email', 'base_type' => 'type/Text', 'semantic_type' => 'type/Email'],
                ['id' => 2, 'name' => 'first_name', 'display_name' => 'First Name', 'base_type' => 'type/Text'],
                ['id' => 3, 'name' => 'state', 'display_name' => 'State', 'base_type' => 'type/Text', 'semantic_type' => 'type/State'],
                ['id' => 4, 'name' => 'lifetime_value', 'display_name' => 'Lifetime Value', 'base_type' => 'type/Float'],
            ]]]]),
        ]);
    }

    public function test_sql_is_for_admins_only_and_staff_get_the_table_builder(): void
    {
        $this->fakeMetabase();
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/metabase/query')->assertOk()->assertDontSee('id="mb-sql"', false)->assertSee('name="table"', false);
        $this->actingAs($viewer)->post('/metabase/query', ['mode' => 'sql', 'db' => 2, 'sql' => 'select * from customers'])->assertForbidden();
        $this->actingAs($viewer)->post('/metabase/query/save', ['mode' => 'sql', 'db' => 2, 'sql' => 'select * from customers', 'name' => 'x'])->assertForbidden();
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/api/dataset') || str_ends_with($r->url(), '/api/card'));

        $this->actingAs($this->admin())->get('/metabase/query')->assertOk()->assertSee('id="mb-sql"', false);
        config(['metabase.sql' => 'off']);
        $this->actingAs($this->admin())->get('/metabase/query')->assertOk()->assertDontSee('id="mb-sql"', false);
    }

    public function test_every_query_is_capped_and_personal_details_are_masked(): void
    {
        $rows = array_map(fn ($i) => ["person$i@gmail.com", '(631) 555-01'.str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT), 'Hauppauge', 120.5 + $i], range(1, 500));
        $this->fakeMetabase([self::MB.'/api/dataset' => Http::response(self::answer([
            ['Email', 'type/Text', 'type/Email'], ['Phone', 'type/Text'], ['City', 'type/Text', 'type/City'], ['Billing Total', 'type/Float'],
        ], $rows), 202)]);
        $admin = $this->admin();

        $html = $this->actingAs($admin)->post('/metabase/query', ['mode' => 'sql', 'db' => 2, 'sql' => 'select email, phone, city, billing_total from customers'])
            ->assertOk()->assertSee('p•••@gmail.com')->assertSee('•••0101')->assertDontSee('person1@gmail.com')->assertDontSee('555-0101')
            ->assertSee('Hauppauge')->assertSee('121.50')
            ->assertSee('OmniVEN shows at most 200 rows')->assertSee('Personal details are masked: Email, Phone.')->getContent();
        $this->assertSame(200, substr_count($html, '•••@gmail.com'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/dataset') && $r['constraints'] === ['max-results' => 200, 'max-results-bare-rows' => 200]);
        $this->assertSame(['queries' => 1, 'rows' => 200], MetabaseGuard::usage($admin));
    }

    public function test_the_builder_wont_list_or_group_by_personal_details(): void
    {
        $this->fakeMetabase([self::MB.'/api/dataset' => Http::response(self::answer([['State', 'type/Text'], ['Count', 'type/Integer']], [['NY', 10]]), 202)]);
        $viewer = $this->viewer();

        $page = $this->actingAs($viewer)->get('/metabase/query?mode=simple&db=2&table=40')->assertOk();
        $page->assertSee('<option value="3" >State</option>', false)->assertDontSee('<option value="1" >Email</option>', false)
            ->assertDontSee('value="distinct:1"', false)->assertDontSee('<option value="2" >First Name</option>', false);

        foreach ([['group' => 1], ['group' => 2], ['measure' => 'distinct:1']] as $try) {
            $this->actingAs($viewer)->post('/metabase/query', ['mode' => 'simple', 'db' => 2, 'table' => 40] + $try)->assertOk()->assertSee('holds personal details');
        }
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/api/dataset'));

        // Grouping by an ordinary column works, and the limit is held to the cap.
        $this->actingAs($viewer)->post('/metabase/query', ['mode' => 'simple', 'db' => 2, 'table' => 40, 'group' => 3, 'limit' => 2000])->assertOk()->assertSee('NY');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/dataset') && $r['query']['limit'] === 200);
    }

    public function test_daily_limits_block_more_queries_and_tell_admins_once(): void
    {
        config(['metabase.daily_queries' => 2, 'omniven.access_slack_channel' => '']);
        $this->fakeMetabase([
            self::MB.'/api/card/7' => Http::response(['id' => 7, 'name' => 'Customers', 'display' => 'table', 'dataset_query' => ['type' => 'query', 'database' => 2]]),
            self::MB.'/api/card/7/query' => Http::response(self::answer([['State', 'type/Text']], [['NY'], ['NJ']]), 202),
        ]);
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/metabase/question/7')->assertOk()->assertSee('NJ');
        $this->actingAs($viewer)->get('/metabase/question/7')->assertOk()->assertSee('NJ');
        $this->actingAs($viewer)->get('/metabase/question/7')->assertOk()->assertSee('You’ve reached the daily limit of 2 Metabase queries')->assertDontSee('NJ');
        $this->actingAs($viewer)->get('/metabase/question/7')->assertOk()->assertSee('daily limit');
        Http::assertSentCount(6);
        $this->assertDatabaseCount('activity_logs', 1 + \App\Models\ActivityLog::where('action', '!=', 'metabase.limit')->count());
        $this->assertSame(1, \App\Models\ActivityLog::where('action', 'metabase.limit')->where('user_id', $viewer->id)->count());
        $this->assertDatabaseHas('metabase_usage', ['user_id' => $viewer->id, 'queries' => 2, 'row_count' => 4, 'blocked' => 2]);

        // Rows count too, and it all resets the next day.
        $this->travel(1)->days();
        $this->flushSession();
        config(['metabase.daily_queries' => 150, 'metabase.daily_rows' => 2]);
        $this->actingAs($viewer)->get('/metabase/question/7')->assertOk()->assertSee('NJ');
        $this->actingAs($viewer)->get('/metabase/question/7')->assertOk()->assertSee('daily limit of 2 rows');
    }

    public function test_a_dashboard_counts_each_card_and_is_refused_whole_when_it_would_go_over(): void
    {
        config(['metabase.daily_queries' => 3]);
        $this->fakeMetabase([
            self::MB.'/api/dashboard/2' => Http::response(['id' => 2, 'name' => 'KPIs', 'dashcards' => [
                ['id' => 11, 'card_id' => 5, 'card' => ['name' => 'A']], ['id' => 12, 'card_id' => 6, 'card' => ['name' => 'B']],
            ]]),
            self::MB.'/api/dashboard/2/dashcard/*' => Http::response(self::answer([['N', 'type/Integer']], [[1]]), 202),
        ]);
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/metabase/dashboard/2')->assertOk()->assertSee('KPIs');
        $this->assertSame(['queries' => 2, 'rows' => 2], MetabaseGuard::usage($viewer));
        $this->actingAs($viewer)->get('/metabase/dashboard/2')->assertOk()->assertSee('daily limit of 3 Metabase queries');
        Http::assertSentCount(4);
    }

    public function test_a_renamed_email_column_is_still_masked(): void
    {
        $this->fakeMetabase([self::MB.'/api/dataset' => Http::response(self::answer([['x', 'type/Text'], ['n', 'type/Integer']], [['ann@example.com', 3], ['bob@example.org', 1]]), 202)]);

        $this->actingAs($this->admin())->post('/metabase/query', ['mode' => 'sql', 'db' => 2, 'sql' => 'select email as x, count(*) as n from orders group by 1'])
            ->assertOk()->assertSee('a•••@example.com')->assertDontSee('ann@example.com')->assertDontSee('bob@example.org');
    }

    public function test_new_queries_can_be_switched_off(): void
    {
        config(['metabase.queries' => false]);
        $this->fakeMetabase();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/metabase/query')->assertOk()->assertSee('New queries are switched off')->assertDontSee('id="mb-sql"', false);
        $this->actingAs($admin)->post('/metabase/query', ['mode' => 'simple', 'db' => 2, 'table' => 40])->assertForbidden();
    }

    public function test_what_counts_as_a_personal_detail(): void
    {
        foreach ([['customer_email', null, 'text'], ['Shipping Address', null, 'text'], ['phone', null, 'number'], ['notes', 'type/Email', 'text'], ['Last Name', null, 'text'], ['card_number', null, 'number']] as [$n, $t, $k]) {
            $this->assertTrue(MetabaseGuard::sensitive($n, $t, $k), $n);
        }
        foreach ([['order_total', null, 'number'], ['billing_total', null, 'number'], ['Created At', null, 'date'], ['brand', null, 'text'], ['translation', null, 'text'], ['state', 'type/State', 'text']] as [$n, $t, $k]) {
            $this->assertFalse(MetabaseGuard::sensitive($n, $t, $k), $n);
        }
        $this->assertTrue(MetabaseGuard::looksPersonal(['a@b.com', 'c@d.org', null, 'none']));
        $this->assertTrue(MetabaseGuard::looksPersonal(['(631) 555-0101', '631.555.0102']));
        $this->assertFalse(MetabaseGuard::looksPersonal(['Ocusafe', '39DollarGlasses', '2026-10-01', 'SKU 12345']));
        $this->assertSame('j•••@39dollarglasses.com', MetabaseGuard::mask('jane@39dollarglasses.com'));
        $this->assertSame('•••4567', MetabaseGuard::mask('+1 631-555-4567'));
        $this->assertSame('S•••', MetabaseGuard::mask('Smith'));
    }
}
