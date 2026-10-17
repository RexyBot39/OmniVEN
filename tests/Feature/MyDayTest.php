<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Support\MyDay;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MyDayTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 08:30', config('app.timezone'))); // a Friday
        config(['services.slack_app.bot_token' => 'xoxb-test']);
        $this->kay = User::factory()->create(['name' => 'Kaylie Ross', 'email' => 'kaylie@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $ocusafe = Brand::where('slug', 'ocusafe')->first();
        Task::create(['title' => 'Approve <b>Q4</b> ad creative', 'assignee_id' => $this->kay->id, 'hot' => true, 'due_on' => '2026-10-16', 'brand_id' => $ocusafe->id]);
        Task::create(['title' => 'Shoot jobsite reel', 'assignee_id' => $this->kay->id, 'hot' => false, 'due_on' => '2026-10-14']);
        Task::create(['title' => 'Reply to reviews', 'assignee_id' => $this->kay->id, 'hot' => false, 'due_on' => '2026-10-16']);
        Task::create(['title' => 'Plan November posts', 'assignee_id' => $this->kay->id, 'hot' => false, 'due_on' => '2026-10-28']);
        Routine::where('cadence', 'daily')->first()->update(['assignee_id' => $this->kay->id]);
    }

    private function fakeSlack(string $lookupError = ''): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $r) use ($lookupError) {
            if (str_contains($r->url(), 'users.lookupByEmail')) {
                return $lookupError ? Http::response(['ok' => false, 'error' => $lookupError]) : Http::response(['ok' => true, 'user' => ['id' => 'U123']]);
            }
            if (str_contains($r->url(), 'chat.postMessage')) {
                return Http::response(['ok' => true, 'ts' => '1.2']);
            }
        });
    }

    public function test_message_groups_the_day_and_escapes_titles(): void
    {
        $text = MyDay::text($this->kay, MyDay::for($this->kay));
        $this->assertStringContainsString('*Good morning, Kaylie.* Here’s your day for Friday, Oct 16.', $text);
        $this->assertStringContainsString("*🔥 Hotlist* (1)\n• Approve &lt;b&gt;Q4&lt;/b&gt; ad creative _(Ocusafe · due today)_", $text);
        $this->assertStringContainsString("*⚠️ Overdue* (1)\n• Shoot jobsite reel _(due Oct 14)_", $text);
        $this->assertStringContainsString("*📅 Due today* (1)\n• Reply to reviews", $text);
        $this->assertStringContainsString('*↻ Routines due* (1)', $text);
        $this->assertStringContainsString('1 more open task later on.', $text);
        $this->assertStringContainsString('|Open your board in OmniVEN>', $text);
    }

    public function test_send_looks_up_slack_by_email_dms_once_a_day_and_skips_weekends(): void
    {
        $this->fakeSlack();
        $counts = MyDay::sendAll();
        $this->assertSame(1, $counts['sent'] ?? 0);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'users.lookupByEmail') && $r['email'] === 'kaylie@39dollarglasses.com');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'chat.postMessage') && $r['channel'] === 'U123' && str_contains($r['text'], 'Shoot jobsite reel'));
        $this->assertSame('U123', $this->kay->fresh()->slack_user_id);

        // Not twice the same day; people with nothing due get nothing.
        MyDay::sendAll();
        Http::assertSentCount(2);

        $this->travelTo(Carbon::parse('2026-10-17 08:30', config('app.timezone'))); // Saturday
        $this->assertSame(['weekend' => 1], MyDay::sendAll());
    }

    public function test_people_turn_it_off_preview_it_and_send_it_to_themselves(): void
    {
        $this->fakeSlack();
        $html = $this->actingAs($this->kay)->get('/my-day')->assertOk()->getContent();
        $this->assertStringContainsString('<b>Good morning, Kaylie.</b>', $html);
        $this->assertStringContainsString('Approve &lt;b&gt;Q4&lt;/b&gt; ad creative', $html);
        $this->assertStringContainsString('Open your board in OmniVEN', $html);
        $this->assertStringContainsString('Morning Slack summary: on', $this->actingAs($this->kay)->get('/')->getContent());

        $this->actingAs($this->kay)->post('/my-day/send')->assertSessionHas('status', 'Sent. Check your Slack messages from OmniVEN.');

        $this->actingAs($this->kay)->post('/my-day/toggle');
        $this->assertFalse($this->kay->fresh()->myday_slack);
        $this->travelTo(Carbon::parse('2026-10-19 08:30', config('app.timezone')));
        $this->assertArrayNotHasKey('sent', MyDay::sendAll());
    }

    public function test_missing_slack_permission_is_explained(): void
    {
        $this->fakeSlack('missing_scope');
        $this->actingAs($this->kay)->post('/my-day/send')->assertSessionHasErrors(['slack' => 'The OmniVEN Slack app needs the users:read.email permission. An admin should reinstall it from slack-app-manifest.yml.']);
        $this->assertArrayHasKey('error: missing_scope', MyDay::sendAll());
    }
}
