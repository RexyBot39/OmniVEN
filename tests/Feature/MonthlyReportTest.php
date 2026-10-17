<?php

namespace Tests\Feature;

use App\Models\AiCheck;
use App\Models\AiQuestion;
use App\Models\Brand;
use App\Models\Review;
use App\Models\SalesNote;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Brand $dg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        config(['revenue.ga4.credentials' => json_encode(['client_email' => 'x@y', 'private_key' => 'k']), 'revenue.ga4.properties' => ['39dg' => '1']]);
        Cache::forever('revenue:last-sync', now()->timestamp);
        $this->admin = User::factory()->create(['name' => 'Dana Boss', 'email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->dg = Brand::where('slug', '39dg')->first();
    }

    private function revenue(string $day, int $cents, string $channel = 'organic_search'): void
    {
        DB::table('revenue_days')->insert(['brand_id' => $this->dg->id, 'day' => $day, 'channel' => $channel, 'source' => 'ga4',
            'sessions' => 1000, 'orders' => 40, 'revenue_cents' => $cents, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_only_revenue_viewers_see_the_report(): void
    {
        $staff = User::factory()->create(['email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($staff)->get('/reports/monthly')->assertForbidden();
        config(['revenue.viewers' => ['kay@39dollarglasses.com']]);
        $this->actingAs($staff)->get('/reports/monthly')->assertOk()->assertSee('Marketing report: October 2026');
    }

    public function test_a_past_month_gathers_revenue_goals_and_every_section(): void
    {
        $this->revenue('2026-09-10', 5_000_000);
        $this->revenue('2026-09-20', 1_000_000, 'email');
        $this->revenue('2025-09-10', 4_000_000);
        $this->revenue('2026-10-02', 9_999_900);
        DB::table('revenue_goals')->insert(['brand_id' => $this->dg->id, 'month' => '2026-09-01', 'goal_cents' => 5_000_000, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('affiliate_months')->insert(['network' => 'cj', 'month' => '2026-09-01', 'publisher_id' => '7', 'publisher_name' => 'Glasses Guide',
            'orders' => 12, 'sale_cents' => 200_000, 'commission_cents' => 20_000, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('review_snapshots')->insert(['platform' => 'trustpilot', 'brand_id' => $this->dg->id, 'day' => '2026-09-29', 'rating' => 4.6, 'review_count' => 1234, 'created_at' => now(), 'updated_at' => now()]);
        Review::create(['platform' => 'trustpilot', 'external_id' => 'a', 'stars' => 5, 'reviewed_at' => '2026-09-05 12:00:00']);
        Review::create(['platform' => 'trustpilot', 'external_id' => 'b', 'stars' => 1, 'reviewed_at' => '2026-09-30 23:30:00']);
        Review::create(['platform' => 'trustpilot', 'external_id' => 'c', 'stars' => 5, 'reviewed_at' => '2026-10-01 00:30:00']);
        $q = AiQuestion::create(['question' => 'Where can I buy cheap glasses online?']);
        AiCheck::create(['ai_question_id' => $q->id, 'engine' => 'chatgpt', 'month' => '2026-09-01', 'result' => 'recommended']);
        AiCheck::create(['ai_question_id' => $q->id, 'engine' => 'google_ai', 'month' => '2026-09-01', 'result' => 'absent']);
        Task::create(['title' => 'Write the blue light guide', 'assignee_id' => $this->admin->id, 'brand_id' => $this->dg->id, 'completed_at' => '2026-09-30 22:30:00', 'completed_by' => $this->admin->id]);
        Task::create(['title' => 'Too late', 'assignee_id' => $this->admin->id, 'completed_at' => '2026-10-01 08:00:00', 'completed_by' => $this->admin->id]);
        SalesNote::create(['week' => '2026-09-14', 'body' => 'Email drove the jump.', 'user_id' => $this->admin->id]);

        $html = $this->actingAs($this->admin)->get('/reports/monthly?month=2026-09')->assertOk()->getContent();
        $this->assertStringContainsString('Marketing report: September 2026', $html);
        $this->assertStringContainsString('Full month', $html);
        $this->assertStringContainsString('$60,000', $html, 'September revenue only');
        $this->assertStringNotContainsString('$159,999', $html);
        $this->assertStringContainsString('120% of $50,000 goal', $html);
        $this->assertStringContainsString('▲ 50.0%', $html, 'vs September last year');
        $this->assertStringContainsString('Glasses Guide', $html);
        $this->assertStringContainsString('10.0% cost of sale', $html);
        $this->assertMatchesRegularExpression('/<b>2<\/b> new reviews averaging <b>3\.0★<\/b>; 1 at 2★/', $html);
        $this->assertStringContainsString('4.6★', $html);
        $this->assertStringContainsString('mentioned us in <b>50%</b> of 2 answers', $html);
        $this->assertMatchesRegularExpression('/<b>1<\/b> tasks finished/', $html);
        $this->assertStringContainsString('Dana Boss: 1 task', $html);
        $this->assertStringContainsString('Email drove the jump.', $html);
        $this->assertStringContainsString('October →', $html);
        $this->assertStringContainsString('data-print', $html);
    }

    public function test_the_current_month_is_partial_and_future_months_clamp(): void
    {
        $html = $this->actingAs($this->admin)->get('/reports/monthly?month=2027-03')->assertOk()->getContent();
        $this->assertStringContainsString('Marketing report: October 2026', $html);
        $this->assertStringContainsString('Month to date, through Oct 16', $html);
        $this->assertStringNotContainsString('November →', $html);
        $this->assertStringNotContainsString('By brand', $html, 'no revenue, no brand table');
        $this->actingAs($this->admin)->get('/reports/monthly?month=nonsense')->assertOk()->assertSee('October 2026');
    }
}
