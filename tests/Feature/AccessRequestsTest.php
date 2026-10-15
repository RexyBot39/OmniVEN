<?php

namespace Tests\Feature;

use App\Models\AccessRequest;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Routine;
use App\Models\Tool;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccessRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        Http::preventStrayRequests();
    }

    private function member(): User
    {
        return User::factory()->create(['is_active' => true, 'name' => 'Pat Member']);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['is_active' => true]);
    }

    private function ask(User $u, string $type, int $id, ?string $reason = null)
    {
        return $this->actingAs($u)->post('/access-requests', ['type' => $type, 'id' => $id, 'reason' => $reason]);
    }

    public function test_member_requests_access_and_sees_it_pending(): void
    {
        $me = $this->member();
        $tool = Tool::first();

        $this->ask($me, 'tool', $tool->id, 'Running the fall campaign')->assertRedirect(route('dashboard').'#access');
        $req = AccessRequest::firstOrFail();
        $this->assertSame(['open', $me->id, 'tool', $tool->id], [$req->status, $req->user_id, $req->item_type, $req->item_id]);

        $this->actingAs($me)->get('/')->assertOk()->assertSee('Requested '.now()->format('M j'));
        // The reason never goes into the Activity report.
        $this->assertFalse(ActivityLog::where('description', 'like', '%fall campaign%')->exists());
        $this->assertTrue(ActivityLog::where('action', 'access.requested')->exists());
    }

    public function test_no_duplicate_open_requests_and_withdraw(): void
    {
        $me = $this->member();
        $tool = Tool::first();
        $this->ask($me, 'tool', $tool->id);
        $this->ask($me, 'tool', $tool->id)->assertSessionHas('status', fn ($s) => str_contains($s, 'already asked'));
        $this->assertSame(1, AccessRequest::count());

        $other = $this->member();
        $req = AccessRequest::first();
        $this->actingAs($other)->delete("/access-requests/{$req->id}")->assertForbidden();
        $this->actingAs($me)->delete("/access-requests/{$req->id}")->assertRedirect();
        $this->assertSame(0, AccessRequest::count());
    }

    public function test_rejects_bad_items(): void
    {
        $me = $this->member();
        $this->ask($me, 'brand', Brand::first()->id)->assertSessionHasErrors('type');
        $this->ask($me, 'tool', 999999)->assertNotFound();
        $this->actingAs($me)->post('/access-requests', ['type' => 'tool', 'id' => Tool::first()->id, 'reason' => str_repeat('x', 501)])
            ->assertSessionHasErrors('reason');
    }

    public function test_only_admins_see_and_handle_requests(): void
    {
        $me = $this->member();
        $acct = Account::create(['brand_id' => Brand::first()->id, 'platform' => 'instagram', 'handle' => '@ocusafe']);
        $this->ask($me, 'account', $acct->id, 'Posting stories');
        $req = AccessRequest::first();

        $this->actingAs($me)->get('/')->assertDontSee('Access requests');
        $this->actingAs($me)->post("/access-requests/{$req->id}/grant")->assertForbidden();
        $this->actingAs($me)->post("/access-requests/{$req->id}/decline")->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Access requests')->assertSee('Posting stories')->assertSee('1 request');

        $this->actingAs($admin)->post("/access-requests/{$req->id}/grant")->assertRedirect();
        $req->refresh();
        $this->assertSame('granted', $req->status);
        $this->assertSame($admin->id, $req->handled_by);
        $this->actingAs($me)->get('/')->assertSee('Granted '.now()->format('M j'));

        // Already handled: a second decision changes nothing.
        $this->actingAs($admin)->post("/access-requests/{$req->id}/decline")->assertSessionHas('status', fn ($s) => str_contains($s, 'already handled'));
        $this->assertSame('granted', $req->fresh()->status);
    }

    public function test_decline_with_note_and_ask_again(): void
    {
        $me = $this->member();
        $tool = Tool::first();
        $this->ask($me, 'tool', $tool->id);
        $req = AccessRequest::first();
        $this->actingAs($this->admin())->post("/access-requests/{$req->id}/decline", ['note' => 'Use the shared login'])->assertRedirect();
        $this->assertSame(['declined', 'Use the shared login'], [$req->fresh()->status, $req->fresh()->decision_note]);

        $this->actingAs($me)->get('/')->assertSee('Declined')->assertSee('Ask again');
        $this->ask($me, 'tool', $tool->id)->assertRedirect();
        $this->assertSame(1, AccessRequest::open()->count());
    }

    public function test_request_from_a_brand_hub_returns_there(): void
    {
        $me = $this->member();
        $brand = Brand::first();
        $acct = Account::create(['brand_id' => $brand->id, 'platform' => 'facebook']);
        $this->actingAs($me)->get("/brands/{$brand->slug}")->assertOk()->assertSee('Request access');
        $this->actingAs($me)->post('/access-requests', ['type' => 'account', 'id' => $acct->id, 'return' => 'hub:'.$brand->slug])
            ->assertRedirect(route('brands.show', $brand));
    }

    public function test_slack_gets_name_and_item_but_never_the_reason(): void
    {
        config(['omniven.access_slack_channel' => 'marketing', 'services.slack_app.bot_token' => 'xoxb-test']);
        Http::fake([
            'slack.com/api/conversations.list*' => Http::response(['ok' => true, 'channels' => [['id' => 'C123', 'name' => 'marketing']]]),
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '1.1']),
        ]);
        $me = $this->member();
        $tool = Tool::first();
        $this->ask($me, 'tool', $tool->id, 'secret project details')->assertRedirect();

        Http::assertSent(function (HttpRequest $r) use ($tool) {
            if (! str_contains($r->url(), 'chat.postMessage')) {
                return false;
            }

            return $r['channel'] === 'C123' && str_contains($r['text'], 'Pat Member') && str_contains($r['text'], $tool->name)
                && ! str_contains($r['text'], 'secret project');
        });
    }

    public function test_request_is_saved_even_when_slack_fails(): void
    {
        config(['omniven.access_slack_channel' => 'marketing', 'services.slack_app.bot_token' => 'xoxb-test']);
        Http::fake(['slack.com/*' => Http::response(['ok' => false, 'error' => 'invalid_auth'])]);
        $this->ask($this->member(), 'tool', Tool::first()->id)->assertRedirect();
        $this->assertSame(1, AccessRequest::count());
    }

    public function test_no_slack_post_when_no_channel_is_set(): void
    {
        config(['omniven.access_slack_channel' => '', 'services.slack_app.bot_token' => 'xoxb-test']);
        Http::fake();
        $this->ask($this->member(), 'tool', Tool::first()->id);
        Http::assertNothingSent();
    }

    public function test_deleting_a_tool_removes_its_requests(): void
    {
        $tool = Tool::first();
        $this->ask($this->member(), 'tool', $tool->id);
        $tool->delete();
        $this->assertSame(0, AccessRequest::count());
    }

    public function test_access_review_is_admin_only_and_verifies_items(): void
    {
        $this->actingAs($this->member())->get('/access/review')->assertForbidden();

        $admin = $this->admin();
        $tool = Tool::first();
        $acct = Account::create(['brand_id' => Brand::first()->id, 'platform' => 'tiktok', 'handle' => '@lumeo']);
        $total = Tool::count() + 1;

        $this->actingAs($admin)->get('/access/review')->assertOk()->assertSee("0 of {$total}")->assertSee('@lumeo');
        $this->actingAs($admin)->post("/tools/{$tool->id}/verify", ['return' => 'review'])->assertRedirect(route('access.review'));
        $this->actingAs($admin)->post("/accounts/{$acct->id}/verify", ['return' => 'review'])->assertRedirect(route('access.review'));
        $this->assertTrue($tool->fresh()->verified_on->isToday());
        $this->assertFalse($tool->fresh()->needsVerify());
        $this->actingAs($admin)->get('/access/review')->assertSee("2 of {$total}");

        $this->actingAs($this->member())->post("/tools/{$tool->id}/verify")->assertForbidden();
    }

    public function test_quarterly_routine_exists_and_resets_each_quarter(): void
    {
        $r = Routine::where('title', 'Quarterly access review')->firstOrFail();
        $this->assertSame('quarterly', $r->cadence);
        $this->assertStringEndsWith('/access/review', $r->link);

        $tz = config('app.timezone');
        $this->assertSame('2026-Q4', Routine::periodKey('quarterly', Carbon::parse('2026-10-01 00:30', $tz)));
        $this->assertSame('2026-Q4', Routine::periodKey('quarterly', Carbon::parse('2026-12-31 23:30', $tz)));
        $this->assertSame('2027-Q1', Routine::periodKey('quarterly', Carbon::parse('2027-01-01 00:30', $tz)));

        $admin = $this->admin();
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Quarterly');
        $this->actingAs($admin)->post("/routines/{$r->id}/toggle")->assertRedirect();
        $this->assertTrue($r->fresh()->isDone());
        $this->travelTo(now()->addMonths(3)->firstOfQuarter()->addDay());
        $this->assertFalse(Routine::with('latestCompletion')->find($r->id)->isDone());
    }
}
