<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\RevenueGoal;
use App\Models\Routine;
use App\Models\SalesNote;
use App\Models\User;
use App\Support\Revenue\Report;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevenueGoalsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Brand $dg;
    private Brand $ocusafe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        // Oct 16 of 31 days: about 52% of the month has gone.
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        config(['revenue.ga4.credentials' => json_encode(['client_email' => 'x@y', 'private_key' => 'k']), 'revenue.ga4.properties' => ['39dg' => '1', 'ocusafe' => '2']]);
        Cache::forever('revenue:last-sync', now()->timestamp);
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->dg = Brand::where('slug', '39dg')->first();
        $this->ocusafe = Brand::where('slug', 'ocusafe')->first();
        foreach ([[$this->dg, 6_000_000], [$this->ocusafe, 400_000]] as [$b, $cents]) {
            DB::table('revenue_days')->insert(['brand_id' => $b->id, 'day' => '2026-10-10', 'channel' => 'organic_search', 'source' => 'ga4', 'sessions' => 1000, 'orders' => 50, 'revenue_cents' => $cents, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_admins_set_goals_and_pace_shows_against_the_month(): void
    {
        $this->actingAs($this->admin)->post('/sales/goals', ['goal' => [$this->dg->id => '100000', $this->ocusafe->id => '20000']])->assertRedirect(route('sales'));
        $this->assertSame(10_000_000, (int) RevenueGoal::where('brand_id', $this->dg->id)->value('goal_cents'));

        // 39DG: $60,000 of $100,000 = 60%, ahead of 52% → on pace. Ocusafe: $4,000 of $20,000 = 20% → behind.
        $p = Report::pace(6_000_000, $this->dg->id);
        $this->assertTrue($p['on_pace']);
        $this->assertFalse(Report::pace(400_000, $this->ocusafe->id)['on_pace']);

        $html = $this->actingAs($this->admin)->get('/sales')->getContent();
        $this->assertStringContainsString('53% of $120,000 goal · on pace', $html);
        $this->assertStringContainsString('60% of $100,000 goal · on pace', $html);
        $this->assertStringContainsString('20% of $20,000 goal · behind pace', $html);
        $this->assertStringContainsString('% of $120,000 goal', $this->actingAs($this->admin)->get('/')->getContent(), 'dashboard strip shows pace too');
        $this->assertStringNotContainsString('goal ·', $this->actingAs($this->admin)->get('/sales?period=last30')->getContent(), 'pace only for this month');

        // Blank clears a goal.
        $this->actingAs($this->admin)->post('/sales/goals', ['goal' => [$this->dg->id => '', $this->ocusafe->id => '20000']]);
        $this->assertNull(RevenueGoal::where('brand_id', $this->dg->id)->first());
    }

    public function test_weekly_note_one_per_week_and_only_admins_set_goals(): void
    {
        $viewer = User::factory()->create(['email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        config(['revenue.viewers' => ['kay@39dollarglasses.com']]);
        $this->actingAs($viewer)->post('/sales/goals', ['goal' => [$this->dg->id => '1']])->assertForbidden();

        $this->actingAs($viewer)->post('/sales/notes', ['body' => 'Organic up after the <b>guide</b>.'])->assertRedirect();
        $this->actingAs($this->admin)->post('/sales/notes', ['body' => 'Organic up after the guide; Ocusafe ads paused Tuesday.']);
        $this->assertSame(1, SalesNote::count());
        $this->assertSame('2026-10-12', substr((string) SalesNote::first()->week, 0, 10));

        SalesNote::create(['week' => '2026-10-05', 'body' => 'Halloween email drove 118 buyers.', 'user_id' => $viewer->id]);
        $html = $this->actingAs($viewer)->get('/sales')->getContent();
        $this->assertStringContainsString('Week of Oct 12', $html);
        $this->assertStringContainsString('Ocusafe ads paused Tuesday.</textarea>', $html);
        $this->assertStringContainsString('Halloween email drove 118 buyers.', $html);
        $this->assertStringNotContainsString('Set this month', $html, 'goal form is admin-only');
        $this->actingAs($viewer)->post('/sales/notes', ['body' => ''])->assertSessionHasErrors('body');
    }

    public function test_weekly_review_routine_is_seeded_once(): void
    {
        $r = Routine::where('title', 'Weekly revenue review')->firstOrFail();
        $this->assertSame('weekly', $r->cadence);
        $this->assertStringEndsWith('/sales', $r->link);
        (new \Database\Seeders\WeeklyRevenueReviewSeeder)->run();
        $this->assertSame(1, Routine::where('title', 'Weekly revenue review')->count());
    }
}
