<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\UtmLink;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Database\Seeders\TaskTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignsTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;
    private User $admin;
    private Brand $dg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-15 10:00', config('app.timezone')));
        $this->kay = User::factory()->create(['name' => 'Kay Lee', 'email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->dg = Brand::where('slug', '39dg')->first();
    }

    private function campaign(array $over = []): Campaign
    {
        $this->actingAs($this->kay)->post('/campaigns', $over + ['name' => 'Fall Frames Sale', 'brand_id' => $this->dg->id, 'owner_id' => $this->kay->id,
            'starts_on' => '2026-10-14', 'ends_on' => '2026-10-20', 'goal' => '600 orders'])->assertSessionHasNoErrors();

        return Campaign::latest('id')->first();
    }

    private function link(string $tag, string $source = 'instagram'): void
    {
        UtmLink::create(['brand_id' => $this->dg->id, 'preset' => 'social', 'base_url' => 'https://www.39dollarglasses.com/', 'source' => $source, 'medium' => 'social',
            'campaign' => $tag, 'url' => "https://www.39dollarglasses.com/?utm_source={$source}&utm_medium=social&utm_campaign={$tag}", 'user_id' => $this->kay->id]);
    }

    public function test_a_campaign_gathers_its_links_tasks_and_results(): void
    {
        $c = $this->campaign();
        $this->assertSame('fall-frames-sale', $c->tag);
        $this->link('fall-frames-sale');
        $this->link('fall-frames-sale', 'klaviyo');
        $this->link('something-else');

        // Adding a task from the campaign page attaches it and comes back.
        $this->actingAs($this->kay)->get("/work/new?type=task&campaign={$c->id}")->assertOk()->assertSee('Part of the campaign');
        $this->actingAs($this->kay)->post('/work', ['kind' => 'task', 'title' => 'Schedule the sale email', 'assignee_id' => $this->kay->id, 'due_on' => '2026-10-18', 'campaign_id' => $c->id])
            ->assertRedirect(route('campaigns.show', $c));
        $task = Task::latest('id')->first();
        $this->assertSame($c->id, $task->campaign_id);
        Task::create(['title' => 'Post the reel', 'assignee_id' => $this->kay->id, 'campaign_id' => $c->id, 'completed_at' => now(), 'completed_by' => $this->kay->id, 'outcome_url' => 'https://www.instagram.com/p/xyz/']);

        // Editing a task without the campaign field keeps its campaign.
        $this->actingAs($this->admin)->put("/tasks/{$task->id}", ['title' => 'Schedule the sale email', 'assignee_id' => $this->kay->id]);
        $this->assertSame($c->id, $task->refresh()->campaign_id);

        $html = $this->actingAs($this->kay)->get("/campaigns/{$c->id}")->assertOk()->getContent();
        $this->assertStringContainsString('utm_source=klaviyo', $html);
        $this->assertStringNotContainsString('something-else', $html);
        $this->assertStringContainsString('Schedule the sale email', $html);
        $this->assertStringContainsString('instagram.com/p/xyz/', $html);
        $this->assertStringContainsString('5 days left', $html);
        $this->assertStringContainsString('campaign=fall-frames-sale', $html, 'link builder prefill');
        $this->assertStringContainsString('value="fall-frames-sale"', $this->actingAs($this->kay)->get('/links?campaign=fall-frames-sale')->getContent());
    }

    public function test_it_shows_on_the_action_calendar_with_tasks_due_that_week(): void
    {
        $c = $this->campaign();
        Task::create(['title' => 'Send the last-chance email', 'assignee_id' => $this->kay->id, 'campaign_id' => $c->id, 'due_on' => '2026-10-18']);
        Task::create(['title' => 'Next week thing', 'assignee_id' => $this->kay->id, 'campaign_id' => $c->id, 'due_on' => '2026-10-21']);
        $this->campaign(['name' => 'Holiday', 'starts_on' => '2026-11-20', 'ends_on' => '2026-12-01']);

        $html = $this->actingAs($this->kay)->get('/calendar')->assertOk()->getContent();
        $panel = substr($html, strpos($html, 'id="campaigns"'), 4000);
        $this->assertStringContainsString('Fall Frames Sale', $panel);
        $this->assertStringContainsString('starts Wed', $panel);
        $this->assertStringContainsString('Send the last-chance email', $panel, 'Sunday task counts in the week');
        $this->assertStringNotContainsString('Next week thing', $panel);
        $this->assertStringNotContainsString('Holiday', $panel);
        $this->assertSame(5, substr_count(substr($panel, strpos($panel, 'cal-cmp-days')), '<i class="on"'), 'Wed–Sun');
    }

    public function test_a_launch_template_run_can_belong_to_a_campaign(): void
    {
        $this->seed(TaskTemplatesSeeder::class);
        $c = $this->campaign();
        $tpl = TaskTemplate::first();
        $this->actingAs($this->kay)->get("/templates/{$tpl->id}/run?campaign={$c->id}")->assertOk()->assertSee('Fall Frames Sale');
        $this->actingAs($this->kay)->post("/templates/{$tpl->id}/run", ['name' => 'Fall frames', 'starts_on' => '2026-10-20', 'assignee_id' => $this->kay->id, 'campaign_id' => $c->id]);
        $this->assertSame($tpl->items()->count(), Task::where('campaign_id', $c->id)->count());
        $this->assertStringContainsString('From launch templates', $this->actingAs($this->kay)->get("/campaigns/{$c->id}")->getContent());
    }

    public function test_untracked_tags_are_listed_tags_are_unique_and_only_admins_delete(): void
    {
        $this->link('spring-glasses');
        $html = $this->actingAs($this->kay)->get('/campaigns')->assertOk()->getContent();
        $this->assertStringContainsString('<code>spring-glasses</code>', $html);
        $this->assertStringContainsString('tag=spring-glasses', $html);

        $c = $this->campaign();
        $this->actingAs($this->kay)->post('/campaigns', ['name' => 'Fall frames sale!', 'starts_on' => '2026-10-14', 'ends_on' => '2026-10-20'])->assertSessionHasErrors('tag');
        $this->actingAs($this->kay)->post('/campaigns', ['name' => 'Backwards', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-14'])->assertSessionHasErrors('ends_on');

        $this->actingAs($this->kay)->delete("/campaigns/{$c->id}")->assertForbidden();
        $t = Task::create(['title' => 'x', 'assignee_id' => $this->kay->id, 'campaign_id' => $c->id]);
        $this->actingAs($this->admin)->delete("/campaigns/{$c->id}")->assertRedirect('/campaigns');
        $this->assertNull($t->refresh()->campaign_id, 'tasks stay');
    }

    public function test_the_monthly_report_lists_campaigns(): void
    {
        $this->campaign();
        $this->assertStringContainsString('Fall Frames Sale', $this->actingAs($this->admin)->get('/reports/monthly')->getContent());
    }
}
