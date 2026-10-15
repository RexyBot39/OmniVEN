<?php

namespace Tests\Feature;

use App\Models\BoostLog;
use App\Models\CalendarCheck;
use App\Models\CalendarItem;
use App\Models\CalendarWeek;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-15 10:00', config('app.timezone')));
        $this->member = User::factory()->create(['is_active' => true, 'name' => 'Leona Park', 'hotlist_popup_on' => today()]);
        $this->admin = User::factory()->admin()->create(['is_active' => true, 'name' => 'Marc Weinstein', 'hotlist_popup_on' => today()]);
    }

    private function week(string $monday): CalendarWeek
    {
        return CalendarWeek::whereDate('starts_on', $monday)->firstOrFail();
    }

    public function test_plan_is_loaded_with_corrected_brand_spelling_and_carried_checks(): void
    {
        $this->assertSame(16, CalendarWeek::count());
        $this->assertSame(15, CalendarItem::whereNull('calendar_week_id')->count());
        $w1 = CalendarWeek::where('number', 1)->first();
        $this->assertSame('Phase 0 · Connect and configure', $w1->phase_label);
        $this->assertSame(24, $w1->items()->count()); // 9 one-offs + 15 every week
        $titles = CalendarItem::pluck('title')->implode(' ');
        $this->assertStringNotContainsString('OcuSafe', $titles); // the brand is spelled Ocusafe
        $this->assertStringContainsString('Ocusafe', $titles);
        $this->assertSame('neurolux', CalendarItem::where('key', 'tue-nx')->value('brand_slug'));
        $this->assertSame('Marc', CalendarItem::where('key', 'tue-nx')->value('owner'));
        // The four checkmarks from the artifact version came across.
        $this->assertSame(4, CalendarCheck::count());
        $this->assertTrue(CalendarCheck::where('calendar_week_id', $this->week('2026-10-12')->id)
            ->where('calendar_item_id', CalendarItem::where('key', 'tue-nx')->value('id'))->exists());
    }

    public function test_running_the_plan_again_adds_no_duplicates(): void
    {
        CalendarItem::where('key', 'tue-nx')->update(['title' => 'Edited by an admin']);
        (new \Database\Seeders\ActionCalendarSeeder)->run();
        $this->assertSame(16, CalendarWeek::count());
        $this->assertSame(15 + 21, CalendarItem::count());
        $this->assertSame('Edited by an admin', CalendarItem::where('key', 'tue-nx')->value('title'));
    }

    public function test_opens_on_this_week_for_everyone(): void
    {
        $this->assertSame('2026-10-12', CalendarWeek::current()->starts_on->toDateString());
        $this->actingAs($this->member)->get('/calendar')->assertOk()
            ->assertSee('Week 5 — this week')->assertSee('Oct 12 – 18')->assertSee('Home Eye Safety Month')
            ->assertSee('Phase 4 · Remaining funnels')->assertSee('#bi-pinterest', false)->assertSee('Marc signs off')
            ->assertSee('1 of 17'); // tue-nx carried over; 2 one-offs + 15 every-week items
    }

    public function test_check_and_uncheck_is_shared_and_records_who(): void
    {
        $week = $this->week('2026-10-19');
        $item = CalendarItem::where('key', 'wed-boost')->first();
        $this->actingAs($this->member)->post("/calendar/2026-10-19/items/{$item->id}/toggle")->assertRedirect(route('calendar.week', $week).'#item-'.$item->id);
        $this->assertSame($this->member->id, CalendarCheck::where(['calendar_week_id' => $week->id, 'calendar_item_id' => $item->id])->value('user_id'));

        $this->actingAs($this->admin)->get('/calendar/2026-10-19')->assertOk()->assertSee('✓ Leona')->assertSee('Oct 15, 2026 10:00am');
        $this->actingAs($this->member)->get('/calendar/2026-10-19')->assertOk()->assertSee('✓ Leona')->assertDontSee('10:00am');

        $this->actingAs($this->admin)->postJson("/calendar/2026-10-19/items/{$item->id}/toggle")->assertJson(['checked' => false]);
        $this->assertFalse(CalendarCheck::where(['calendar_week_id' => $week->id, 'calendar_item_id' => $item->id])->exists());
    }

    public function test_one_off_items_only_toggle_in_their_own_week(): void
    {
        $w1only = CalendarItem::where('key', 'w1-connect')->first();
        $this->actingAs($this->member)->post("/calendar/2026-10-12/items/{$w1only->id}/toggle")->assertNotFound();
        $this->actingAs($this->member)->get('/calendar/2026-13-99')->assertNotFound();
        $this->actingAs($this->member)->get('/calendar/not-a-week')->assertNotFound();
    }

    public function test_boost_log_counts_spend_only_when_boosted(): void
    {
        $this->actingAs($this->member)->post('/calendar/2026-10-12/boosts', ['day' => 'Wed', 'brand_slug' => '39dg', 'format' => 'FrameOfTheWeek', 'beat' => 'yes', 'amount' => 150])->assertRedirect();
        $this->actingAs($this->member)->post('/calendar/2026-10-12/boosts', ['day' => 'Fri', 'brand_slug' => 'ocusafe', 'beat' => 'no', 'amount' => 125])->assertRedirect();
        $this->assertSame(2, BoostLog::count());
        $this->assertSame(0, BoostLog::where('beat_median', false)->value('amount'));

        $this->actingAs($this->member)->get('/calendar')->assertSee('1 boosted · $150 this week')->assertSee('no — skipped')->assertSee('$150');
        $this->actingAs($this->member)->post('/calendar/2026-10-12/boosts', ['day' => 'Funday', 'brand_slug' => 'acme', 'beat' => 'maybe'])->assertSessionHasErrors(['day', 'brand_slug', 'beat']);

        // Only the person who logged it, or an admin, can remove it.
        $boost = BoostLog::first();
        $other = User::factory()->create(['is_active' => true]);
        $this->actingAs($other)->delete("/calendar/boosts/{$boost->id}")->assertForbidden();
        $this->actingAs($this->admin)->delete("/calendar/boosts/{$boost->id}")->assertRedirect();
        $this->assertSame(1, BoostLog::count());
    }

    public function test_sunday_note_saves_per_week(): void
    {
        $this->actingAs($this->member)->put('/calendar/2026-10-12/notes', ['notes' => 'Moving Neurolux to Thursdays.'])->assertRedirect();
        $w = $this->week('2026-10-12');
        $this->assertSame('Moving Neurolux to Thursdays.', $w->fresh()->notes);
        $this->assertSame($this->member->id, $w->fresh()->notes_by);
        $this->assertNull($this->week('2026-10-19')->notes);
        $this->actingAs($this->admin)->get('/calendar/2026-10-12')->assertSee('Moving Neurolux to Thursdays.');
    }

    public function test_csv_exports(): void
    {
        $this->actingAs($this->member)->post('/calendar/2026-10-12/boosts', ['day' => 'Wed', 'brand_slug' => '39dg', 'format' => '=HYPERLINK("x")', 'beat' => 'yes', 'amount' => 150]);
        $csv = $this->actingAs($this->member)->get('/calendar/export/boosts.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Week,Dates,Day,Brand,Format,"Beat median",Spend,"Logged by"', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv); // formulas neutralised

        $tasks = $this->actingAs($this->member)->get('/calendar/export/tasks.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Neurolux', $tasks);
        $this->assertSame(16 * 15 + 21 + 1, substr_count($tasks, "\n")); // runbook × 16 weeks + 21 one-offs + header
    }

    public function test_links_from_dashboard_and_guests_blocked(): void
    {
        $this->actingAs($this->member)->get('/')->assertOk()->assertSee('Action calendar · Week 5')->assertSee(route('calendar'), false);
        $this->assertNotNull(collect($this->actingAs($this->member)->getJson('/launcher.json')->json())->firstWhere('t', 'Action calendar'));
        auth()->logout();
        $this->get('/calendar')->assertRedirect('/login');
    }
}
