<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Routine;
use App\Models\RoutineBrandCheck;
use App\Models\User;
use App\Support\MyDay;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutinePerBrandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $kay;
    private Routine $routine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-15 10:00', config('app.timezone')));
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->kay = User::factory()->create(['name' => 'Kay Lee', 'email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($this->admin)->post('/routines', ['title' => 'Reply to every DM', 'cadence' => 'daily', 'brands' => ['all'], 'per_brand' => 1, 'assignee_id' => $this->kay->id]);
        $this->routine = Routine::where('title', 'Reply to every DM')->first();
    }

    private function brand(string $slug): Brand
    {
        return Brand::where('slug', $slug)->first();
    }

    public function test_each_brand_is_ticked_and_the_routine_is_done_when_all_are(): void
    {
        $this->assertTrue($this->routine->per_brand);
        $this->assertSame(['39DollarGlasses', 'Ocusafe', 'Ocusleep'], $this->routine->splitBrands()->pluck('name')->all());

        $html = $this->actingAs($this->kay)->get('/')->getContent();
        $this->assertStringContainsString('routines/'.$this->routine->id.'/brands/'.$this->brand('ocusafe')->id, $html);
        $this->assertStringContainsString('0 of 3', $html);

        $this->actingAs($this->kay)->post("/routines/{$this->routine->id}/brands/{$this->brand('39dg')->id}")->assertSessionHas('status', 'Ticked 39DollarGlasses for “Reply to every DM”.');
        $this->actingAs($this->kay)->post("/routines/{$this->routine->id}/brands/{$this->brand('ocusafe')->id}");
        $this->assertFalse($this->routine->fresh()->isDone());
        $this->assertStringContainsString('still to do: Ocusleep', MyDay::text($this->kay, MyDay::for($this->kay)));

        $this->actingAs($this->kay)->post("/routines/{$this->routine->id}/brands/{$this->brand('ocusleep')->id}", ['return' => 'tasks'])
            ->assertRedirect(route('dashboard').'#tasks')->assertSessionHas('status', 'All brands done. “Reply to every DM” is done.');
        $this->assertTrue($this->routine->fresh()->isDone());
        $this->assertSame($this->kay->id, $this->routine->fresh()->latestCompletion->user_id);

        // Unticking one brand reopens it.
        $this->actingAs($this->kay)->post("/routines/{$this->routine->id}/brands/{$this->brand('ocusafe')->id}");
        $this->assertFalse($this->routine->fresh()->isDone());

        // Tomorrow starts fresh.
        $this->travelTo(Carbon::parse('2026-10-16 09:00', config('app.timezone')));
        $this->assertSame(0, $this->routine->fresh()->currentBrandChecks()->count());
    }

    public function test_the_main_tick_does_or_undoes_every_brand(): void
    {
        $this->actingAs($this->kay)->post("/routines/{$this->routine->id}/toggle");
        $this->assertTrue($this->routine->fresh()->isDone());
        $this->assertSame(3, RoutineBrandCheck::count());
        $this->actingAs($this->kay)->post("/routines/{$this->routine->id}/toggle");
        $this->assertFalse($this->routine->fresh()->isDone());
        $this->assertSame(0, RoutineBrandCheck::count());
    }

    public function test_only_split_routines_and_their_own_brands(): void
    {
        $this->actingAs($this->admin)->post('/routines', ['title' => 'Plain', 'cadence' => 'weekly', 'brands' => ['39dg', 'ocusafe']]);
        $plain = Routine::where('title', 'Plain')->first();
        $this->assertFalse($plain->per_brand);
        $this->actingAs($this->kay)->post("/routines/{$plain->id}/brands/{$this->brand('39dg')->id}")->assertNotFound();
        $this->assertStringNotContainsString('routines/'.$plain->id.'/brands/', $this->actingAs($this->kay)->get('/')->getContent());

        $this->actingAs($this->admin)->post('/routines', ['title' => 'Two brands', 'cadence' => 'weekly', 'brands' => ['39dg', 'neurolux'], 'per_brand' => 1]);
        $two = Routine::where('title', 'Two brands')->first();
        $this->actingAs($this->kay)->post("/routines/{$two->id}/brands/{$this->brand('ocusafe')->id}")->assertNotFound();
        $this->actingAs($this->kay)->post("/routines/{$two->id}/brands/{$this->brand('neurolux')->id}")->assertRedirect();

        // Editing without the box switches it off.
        $this->actingAs($this->admin)->put("/routines/{$two->id}", ['title' => 'Two brands', 'cadence' => 'weekly', 'brands' => ['39dg', 'neurolux']]);
        $this->assertFalse($two->fresh()->per_brand);
        $this->assertStringContainsString('Tick off each brand separately', $this->actingAs($this->admin)->get("/routines/{$two->id}/edit")->getContent());
    }
}
