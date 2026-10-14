<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Note;
use App\Models\Routine;
use App\Models\Tool;
use App\Models\User;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
    }

    public function test_dashboard_renders_seeded_data_with_brand_names(): void
    {
        $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()
            ->assertSee('Google Merchant Center')
            ->assertSee('Google Search Console')
            ->assertSee('TikTok Business Center')
            ->assertSee('Meta Business Suite')
            ->assertSee('Ocusafe')
            ->assertSee('Ocusleep')
            ->assertDontSee('OcuSafe')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_staff_cannot_reach_admin_pages(): void
    {
        $staff = User::factory()->create();
        $tool = Tool::first();
        $this->actingAs($staff)->get('/tools/create')->assertForbidden();
        $this->actingAs($staff)->put("/tools/{$tool->id}", ['name' => 'x', 'status' => 'active'])->assertForbidden();
        $this->actingAs($staff)->delete("/brands/".Brand::first()->id)->assertForbidden();
        $this->actingAs($staff)->get('/people')->assertForbidden();
        $this->actingAs($staff)->get('/')->assertDontSee('+ Add tool');
    }

    public function test_admin_can_add_tool_for_specific_brands(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/tools', [
            'name' => 'Pinterest Business', 'url' => 'https://business.pinterest.com', 'category' => 'Social & Community',
            'status' => 'verify', 'brands' => ['ocusafe', 'neurolux'], 'featured' => '1',
        ])->assertRedirect('/');
        $tool = Tool::where('name', 'Pinterest Business')->first();
        $this->assertTrue($tool->featured);
        $this->assertEqualsCanonicalizing(['ocusafe', 'neurolux'], $tool->brands->pluck('slug')->all());
    }

    public function test_tool_urls_must_be_http(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/tools', ['name' => 'Bad', 'url' => 'javascript:alert(1)', 'status' => 'active'])
            ->assertSessionHasErrors('url');
    }

    public function test_anyone_can_tick_a_routine_and_untick_it(): void
    {
        $staff = User::factory()->create();
        $r = Routine::where('cadence', 'weekly')->first();
        $this->actingAs($staff)->post("/routines/{$r->id}/toggle")->assertRedirect();
        $this->assertTrue($r->fresh()->isDone());
        $this->assertSame($staff->id, $r->fresh()->latestCompletion->user_id);
        $this->actingAs($staff)->post("/routines/{$r->id}/toggle");
        $this->assertFalse($r->fresh()->isDone());
    }

    public function test_daily_routine_resets_next_day(): void
    {
        $r = Routine::where('cadence', 'daily')->first();
        $this->actingAs(User::factory()->create())->post("/routines/{$r->id}/toggle");
        $this->assertTrue($r->fresh()->isDone());
        $this->travel(1)->days();
        $this->assertFalse($r->fresh()->isDone());
    }

    public function test_staff_can_post_but_not_pin_or_edit_others_notes(): void
    {
        $staff = User::factory()->create();
        $this->actingAs($staff)->post('/notes', ['title' => 'Spring sale live', 'pinned' => '1'])->assertRedirect('/');
        $mine = Note::where('title', 'Spring sale live')->first();
        $this->assertFalse($mine->pinned);
        $this->actingAs($staff)->get("/notes/{$mine->id}/edit")->assertOk();

        $other = Note::where('title', 'Welcome to OmniVEN')->first();
        $this->actingAs($staff)->delete("/notes/{$other->id}")->assertForbidden();
    }

    public function test_admin_can_manage_people_but_not_demote_configured_admin(): void
    {
        $marc = User::factory()->admin()->create(['email' => 'drmarcweinstein@39dollarglasses.com']);
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();

        $this->actingAs($admin)->patch("/people/{$staff->id}", ['is_admin' => '1'])->assertRedirect();
        $this->assertTrue($staff->fresh()->is_admin);
        $this->actingAs($admin)->patch("/people/{$staff->id}", ['is_active' => '0']);
        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($admin)->patch("/people/{$marc->id}", ['is_admin' => '0'])->assertSessionHas('error');
        $this->assertTrue($marc->fresh()->is_admin);
    }

    public function test_seeder_is_idempotent(): void
    {
        $count = Tool::count();
        $this->seed(OmniVenSeeder::class);
        $this->assertSame($count, Tool::count());
    }
}
