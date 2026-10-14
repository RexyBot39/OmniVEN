<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\SlackChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSlack(array $history = null): void
    {
        config(['services.slack_app.bot_token' => 'xoxb-test', 'services.slack_app.default_channels' => 'reddit-monitor,marketing']);
        Http::fake([
            'slack.com/api/auth.test*' => Http::response(['ok' => true, 'url' => 'https://39dg.slack.com/', 'team_id' => 'T1']),
            'slack.com/api/conversations.list*' => Http::response(['ok' => true, 'channels' => [
                ['id' => 'C1', 'name' => 'reddit-monitor', 'is_member' => true, 'is_private' => false],
                ['id' => 'C2', 'name' => 'marketing', 'is_member' => false, 'is_private' => false],
                ['id' => 'C3', 'name' => 'leadership', 'is_member' => false, 'is_private' => true],
            ]]),
            'slack.com/api/conversations.join*' => Http::response(['ok' => true]),
            'slack.com/api/users.list*' => Http::response(['ok' => true, 'members' => [
                ['id' => 'U1', 'name' => 'pat', 'profile' => ['real_name' => 'Pat Lee', 'image_48' => 'https://avatars.slack-edge.com/p.png']],
            ]]),
            'slack.com/api/conversations.history*' => Http::response(['ok' => true, 'messages' => $history ?? [
                ['ts' => '1792000000.000200', 'user' => 'U1', 'text' => 'Hey <@U1>, see <https://39dollarglasses.com|the site> *now* <script>alert(1)</script>', 'reply_count' => 3],
                ['ts' => '1791999000.000100', 'user' => 'U1', 'text' => 'First post'],
            ]]),
            'slack.com/api/chat.postMessage*' => Http::response(['ok' => true]),
        ]);
    }

    public function test_tab_says_not_connected_without_a_token(): void
    {
        config(['services.slack_app.bot_token' => null]);
        $this->actingAs(User::factory()->create())->get('/slack/feed')->assertOk()->assertSee("Slack isn't connected yet", false);
    }

    public function test_feed_loads_default_channels_and_renders_messages_safely(): void
    {
        $this->fakeSlack();
        $res = $this->actingAs(User::factory()->create())->get('/slack/feed')->assertOk();

        $this->assertSame(['reddit-monitor', 'marketing'], SlackChannel::orderBy('sort')->pluck('name')->all());
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'conversations.join') && $r['channel'] === 'C2');

        $res->assertSee('Pat Lee')->assertSee('#reddit-monitor')
            ->assertSee('<a href="https://39dollarglasses.com" target="_blank" rel="noopener noreferrer">the site</a>', false)
            ->assertSee('<strong>now</strong>', false)
            ->assertSee('@Pat Lee')
            ->assertSee('3 replies in Slack')
            ->assertSee('https://39dg.slack.com/archives/C1/p1792000000000200', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->assertLessThan(strpos($res->getContent(), 'Hey'), strpos($res->getContent(), 'First post'));
    }

    public function test_posting_sends_as_the_person_and_is_logged(): void
    {
        $this->fakeSlack();
        $ch = SlackChannel::create(['channel_id' => 'C2', 'name' => 'marketing']);
        $pat = User::factory()->create(['name' => 'Pat Lee']);

        $this->actingAs($pat)->postJson("/slack/channels/{$ch->id}/post", ['text' => 'Fall sale goes live at 9'])->assertOk();

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'chat.postMessage')
            && $r['channel'] === 'C2' && $r['text'] === 'Fall sale goes live at 9' && $r['username'] === 'Pat Lee (via OmniVEN)');
        $this->assertSame(1, ActivityLog::where('action', 'slack.posted')->where('user_id', $pat->id)->count());
    }

    public function test_not_in_channel_error_is_explained(): void
    {
        config(['services.slack_app.bot_token' => 'xoxb-test']);
        SlackChannel::create(['channel_id' => 'C3', 'name' => 'leadership']);
        Http::fake([
            'slack.com/api/auth.test*' => Http::response(['ok' => true, 'url' => 'https://39dg.slack.com/']),
            'slack.com/api/users.list*' => Http::response(['ok' => true, 'members' => []]),
            'slack.com/api/conversations.history*' => Http::response(['ok' => false, 'error' => 'not_in_channel']),
        ]);
        $this->actingAs(User::factory()->create())->get('/slack/feed')->assertSee('/invite @OmniVEN');
    }

    public function test_only_admins_manage_channels(): void
    {
        $this->fakeSlack();
        $this->actingAs(User::factory()->create())->post('/slack/channels', ['name' => '#leadership'])->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->post('/slack/channels', ['name' => '#leadership'])
            ->assertRedirect()->assertSessionHas('status');
        $ch = SlackChannel::where('channel_id', 'C3')->first();
        $this->assertNotNull($ch);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'conversations.join') && ($r['channel'] ?? null) === 'C3');

        $this->actingAs(User::factory()->admin()->create())->post('/slack/channels', ['name' => 'nope'])->assertSessionHas('error');
    }
}
