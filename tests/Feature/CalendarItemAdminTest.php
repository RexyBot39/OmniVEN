<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CalendarCheck;
use App\Models\CalendarItem;
use App\Models\CalendarWeek;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarItemAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-15 10:00', config('app.timezone')));
        $this->admin = User::factory()->admin()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
        $this->member = User::factory()->create(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function week(int $n): CalendarWeek
    {
        return CalendarWeek::where('number', $n)->firstOrFail();
    }

    private function data(array $extra = []): array
    {
        return array_merge(['title' => 'Post the Trailview reel on Instagram', 'day' => 'Thu', 'scope' => 'week', 'brand_slug' => 'trailview', 'owner' => 'Social', 'keyword' => '', 'done_when' => 'Live on the feed'], $extra);
    }

    public function test_admin_adds_a_one_week_item(): void
    {
        $w5 = $this->week(5);
        $this->actingAs($this->admin)->get('/calendar/2026-10-12')->assertOk()->assertSee('+ Add item')->assertSee('/calendar/2026-10-12/items/create', false);
        $this->actingAs($this->admin)->get('/calendar/2026-10-12/items/create')->assertOk()->assertSee('Week 5 only');

        $this->actingAs($this->admin)->post('/calendar/2026-10-12/items', $this->data())->assertRedirect();
        $item = CalendarItem::where('title', 'Post the Trailview reel on Instagram')->firstOrFail();
        $this->assertSame($w5->id, $item->calendar_week_id);
        $this->assertSame('trailview', $item->brand_slug);
        $this->assertStringStartsWith('custom-', $item->key);
        $this->assertSame(18, $w5->items()->count());
        $this->assertSame(15, $this->week(6)->items()->count());
        $this->assertTrue(ActivityLog::where('action', 'calendar.item.created')->exists());

        $this->actingAs($this->member)->get('/calendar/2026-10-12')->assertSee('Post the Trailview reel')->assertSee('#bi-instagram', false);
    }

    public function test_admin_adds_an_every_week_item(): void
    {
        $this->actingAs($this->admin)->post('/calendar/2026-10-12/items', $this->data(['scope' => 'every', 'title' => 'Reply to Shopper Approved reviews', 'day' => 'Tue', 'brand_slug' => '']))->assertRedirect();
        $item = CalendarItem::where('title', 'Reply to Shopper Approved reviews')->firstOrFail();
        $this->assertNull($item->calendar_week_id);
        $this->assertNull($item->brand_slug);
        $this->assertSame(16, $this->week(6)->items()->count());
        $this->assertSame(25, $this->week(1)->items()->count());
    }

    public function test_editing_a_recurring_item_changes_every_week(): void
    {
        $item = CalendarItem::where('key', 'mon-feedly')->first();
        $this->actingAs($this->admin)->get("/calendar/2026-10-12/items/{$item->id}/edit")->assertOk()->assertSee('Changes apply to all 16 weeks');
        $this->actingAs($this->admin)->put("/calendar/2026-10-12/items/{$item->id}", $this->data(['scope' => 'every', 'title' => 'Feedly triage, four anchors', 'day' => 'Mon', 'brand_slug' => '']))->assertRedirect();
        $this->actingAs($this->member)->get('/calendar/2026-11-02')->assertSee('Feedly triage, four anchors');
    }

    public function test_narrowing_to_one_week_drops_other_weeks_checks(): void
    {
        $item = CalendarItem::where('key', 'tue-nx')->first();
        CalendarCheck::create(['calendar_week_id' => $this->week(6)->id, 'calendar_item_id' => $item->id, 'checked_at' => now()]);
        $this->assertSame(2, CalendarCheck::where('calendar_item_id', $item->id)->count()); // week 5 (carried over) + week 6

        $this->actingAs($this->admin)->put("/calendar/2026-10-12/items/{$item->id}", $this->data(['scope' => 'week', 'title' => $item->title, 'day' => 'Tue', 'brand_slug' => 'neurolux', 'owner' => 'Marc']))->assertRedirect();
        $this->assertSame($this->week(5)->id, $item->fresh()->calendar_week_id);
        $this->assertSame(1, CalendarCheck::where('calendar_item_id', $item->id)->count());
        $this->assertFalse($this->week(6)->items()->contains('id', $item->id));
    }

    public function test_admin_deletes_an_item_and_its_checks(): void
    {
        $item = CalendarItem::where('key', 'tue-nx')->first();
        $this->actingAs($this->admin)->get("/calendar/2026-10-12/items/{$item->id}/edit")->assertSee('Deleting removes it from all 16 weeks, along with 1 checkmark');
        $this->actingAs($this->admin)->delete("/calendar/2026-10-12/items/{$item->id}")->assertRedirect(route('calendar.week', $this->week(5)).'#week');
        $this->assertNull(CalendarItem::find($item->id));
        $this->assertSame(0, CalendarCheck::where('calendar_item_id', $item->id)->count());
        $this->assertSame(16, $this->week(5)->items()->count());
    }

    public function test_one_off_items_can_only_be_edited_from_their_week(): void
    {
        $w1only = CalendarItem::where('key', 'w1-connect')->first();
        $this->actingAs($this->admin)->get("/calendar/2026-10-12/items/{$w1only->id}/edit")->assertNotFound();
        $this->actingAs($this->admin)->delete("/calendar/2026-10-12/items/{$w1only->id}")->assertNotFound();
        $this->assertNotNull(CalendarItem::find($w1only->id));
    }

    public function test_validation(): void
    {
        $this->actingAs($this->admin)->post('/calendar/2026-10-12/items', ['title' => '', 'day' => 'Funday', 'scope' => 'sometimes', 'brand_slug' => 'acme', 'owner' => ''])
            ->assertSessionHasErrors(['title', 'day', 'scope', 'brand_slug', 'owner']);
    }

    public function test_staff_cannot_change_items(): void
    {
        $item = CalendarItem::where('key', 'mon-feedly')->first();
        $this->actingAs($this->member)->get('/calendar/2026-10-12')->assertDontSee('+ Add item')->assertDontSee('/items/'.$item->id.'/edit', false);
        $this->actingAs($this->member)->get('/calendar/2026-10-12/items/create')->assertForbidden();
        $this->actingAs($this->member)->post('/calendar/2026-10-12/items', $this->data())->assertForbidden();
        $this->actingAs($this->member)->put("/calendar/2026-10-12/items/{$item->id}", $this->data())->assertForbidden();
        $this->actingAs($this->member)->delete("/calendar/2026-10-12/items/{$item->id}")->assertForbidden();
        $this->assertSame(15 + 21, CalendarItem::count());
    }
}
