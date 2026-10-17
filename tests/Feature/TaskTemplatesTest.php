<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TemplateRun;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;
    private User $chanley;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        $this->kay = User::factory()->create(['name' => 'Kaylie Ross', 'email' => 'kaylie@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->chanley = User::factory()->create(['name' => 'Chanley Diaz', 'email' => 'chanley@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    public function test_two_starter_templates_are_seeded_once(): void
    {
        $this->assertSame(['Product launch', 'Seasonal sale'], TaskTemplate::orderBy('sort')->pluck('name')->all());
        $this->assertGreaterThanOrEqual(12, TaskTemplate::where('name', 'Seasonal sale')->first()->items()->count());
        (new \Database\Seeders\TaskTemplatesSeeder)->run();
        $this->assertSame(2, TaskTemplate::count());
        $this->actingAs($this->kay)->get('/templates')->assertOk()->assertSee('Use this template')->assertDontSee('New template');
        $this->assertStringContainsString('From a template', $this->actingAs($this->kay)->get('/')->getContent());
    }

    public function test_running_a_template_creates_dated_tasks_for_the_right_people(): void
    {
        $t = TaskTemplate::where('name', 'Seasonal sale')->first();
        $items = $t->items()->get();
        $email = $items->first(fn ($i) => str_contains($i->title, 'Klaviyo'));
        $skip = $items->first(fn ($i) => str_contains($i->title, 'ad set'));
        $ocusafe = Brand::where('slug', 'ocusafe')->first();

        $this->actingAs($this->kay)->get("/templates/{$t->id}/run")->assertOk()->assertSee('Seasonal sale '.now()->year, false)->assertSee('14 days before');

        $this->actingAs($this->kay)->post("/templates/{$t->id}/run", [
            'name' => 'Black Friday 2026', 'brand_id' => $ocusafe->id, 'starts_on' => '2026-11-27', 'assignee_id' => $this->kay->id,
            'assignees' => [$email->id => $this->chanley->id], 'skip' => [$skip->id => '1'],
        ])->assertRedirect();

        $run = TemplateRun::firstOrFail();
        $tasks = Task::where('template_run_id', $run->id)->get();
        $this->assertCount($items->count() - 1, $tasks);
        $mail = $tasks->first(fn ($x) => str_contains($x->title, 'Klaviyo'));
        $this->assertSame('Build the Black Friday 2026 announcement and reminder emails in Klaviyo', $mail->title);
        $this->assertSame($this->chanley->id, $mail->assignee_id);
        $this->assertSame('2026-11-20', $mail->due_on->format('Y-m-d'));
        $this->assertSame($ocusafe->id, $mail->brand_id);
        $start = $tasks->first(fn ($x) => str_starts_with($x->title, 'Black Friday 2026 starts'));
        $this->assertTrue($start->hot);
        $this->assertSame('2026-11-27', $start->due_on->format('Y-m-d'));
        $this->assertSame($this->kay->id, $start->assignee_id);
        $this->assertFalse($tasks->contains(fn ($x) => str_contains($x->title, 'ad set')));

        $start->update(['completed_at' => now(), 'completed_by' => $this->kay->id]);
        $page = $this->actingAs($this->chanley)->get(route('templates.show-run', $run))->assertOk()->getContent();
        $this->assertStringContainsString('1 of '.$tasks->count().' done', $page);
        $this->assertStringContainsString('1 of '.$tasks->count().' done', $this->actingAs($this->chanley)->get('/templates')->getContent());
    }

    public function test_admins_edit_templates_and_others_cannot(): void
    {
        $this->actingAs($this->kay)->post('/templates', ['name' => 'Trade show'])->assertForbidden();
        $this->actingAs($this->admin)->post('/templates', ['name' => 'Ocusafe trade show'])->assertRedirect();
        $t = TaskTemplate::where('name', 'Ocusafe trade show')->firstOrFail();
        $this->actingAs($this->kay)->get("/templates/{$t->id}/edit")->assertForbidden();

        $this->actingAs($this->admin)->put("/templates/{$t->id}", ['name' => 'Ocusafe trade show', 'items' => [
            'new1' => ['title' => 'Ship the {name} booth kit', 'offset_days' => '-10', 'assignee_id' => $this->chanley->id],
            'new2' => ['title' => 'Post {name} photos', 'offset_days' => '1', 'hot' => '1'],
            'new3' => ['title' => '', 'offset_days' => '0'],
        ]])->assertRedirect();
        $this->assertSame(['Ship the {name} booth kit', 'Post {name} photos'], $t->items()->pluck('title')->all());
        $booth = $t->items()->first();

        $this->actingAs($this->admin)->put("/templates/{$t->id}", ['name' => 'Ocusafe trade show', 'items' => [
            $booth->id => ['title' => 'Ship the {name} booth kit', 'offset_days' => '-12', 'remove' => '1'],
        ]]);
        $this->assertSame(['Post {name} photos'], $t->items()->pluck('title')->all());

        // A step's usual person is used when the runner doesn't pick someone.
        $t->items()->first()->update(['assignee_id' => $this->chanley->id]);
        $this->actingAs($this->kay)->post("/templates/{$t->id}/run", ['name' => 'Safety Expo', 'starts_on' => '2026-11-02', 'assignee_id' => $this->kay->id]);
        $this->assertSame($this->chanley->id, Task::where('title', 'Post Safety Expo photos')->value('assignee_id'));

        $this->actingAs($this->admin)->delete("/templates/{$t->id}")->assertRedirect();
        $this->assertNull(TaskTemplate::find($t->id));
        $this->assertNotNull(Task::where('title', 'Post Safety Expo photos')->first(), 'tasks already made stay');
    }
}
