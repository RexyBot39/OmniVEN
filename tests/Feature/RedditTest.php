<?php

namespace Tests\Feature;

use App\Models\RedditThread;
use App\Models\Task;
use App\Models\User;
use App\Support\Reddit;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RedditTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;
    private User $leona;
    private int $status = 200;
    private int $tokenCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        config(['reddit.client_id' => 'cid', 'reddit.client_secret' => 'secret']);
        Reddit::forget();
        $this->kay = User::factory()->create(['name' => 'Kay Lee', 'email' => 'kay@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->leona = User::factory()->create(['name' => 'Leona Park', 'email' => 'leona.test@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function thread(string $id, string $title, string $body = '', int $hoursAgo = 5, int $comments = 3, string $sub = 'glasses'): array
    {
        return ['kind' => 't3', 'data' => ['id' => $id, 'subreddit' => $sub, 'title' => $title, 'selftext' => $body, 'author' => 'someone_private',
            'permalink' => "/r/{$sub}/comments/{$id}/x/", 'created_utc' => now()->subHours($hoursAgo)->timestamp, 'num_comments' => $comments, 'score' => 4]];
    }

    private function fake(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $r) {
            if (str_contains($r->url(), 'api/v1/access_token')) {
                $this->tokenCalls++;
                $this->assertSame('Basic '.base64_encode('cid:secret'), $r->header('Authorization')[0]);

                return Http::response(['access_token' => 'tok', 'expires_in' => 3600]);
            }
            if ($this->status !== 200) {
                return Http::response([], $this->status);
            }
            $this->assertSame('Bearer tok', $r->header('Authorization')[0]);
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            $children = match (true) {
                $q['q'] === '39dollarglasses' => [
                    $this->thread('a1', 'Ordered from 39DollarGlasses, never arrived and no refund yet', '', 2, 4),
                    $this->thread('a2', 'My new 39dollarglasses frames are great', '', 30, 10),
                    $this->thread('old', '39dollarglasses review', '', 24 * 9),
                    ['kind' => 't3', 'data' => ['id' => 'nsfw', 'over_18' => true, 'title' => '39dollarglasses', 'permalink' => '/r/x/', 'created_utc' => now()->timestamp]],
                ],
                ($q['restrict_sr'] ?? null) === 'on' && $q['q'] === 'zenni alternative' => [
                    $this->thread('b1', 'Is there a good Zenni alternative for progressives?', '', 10, 12, 'eyeglasses'),
                    $this->thread('b2', 'Photo of my cat wearing glasses', '', 10, 0, 'eyeglasses'),
                ],
                ($q['restrict_sr'] ?? null) === 'on' && $q['q'] === 'cheap glasses online' => [
                    $this->thread('c1', 'Where can I buy cheap prescription glasses online?', '', 60, 220, 'Frugal'),
                ],
                default => [],
            };

            return Http::response(['data' => ['children' => $children]]);
        });
    }

    public function test_finds_and_ranks_threads_worth_a_reply(): void
    {
        $this->fake();
        $this->assertTrue(Reddit::refresh());

        $t = RedditThread::orderByDesc('score')->get()->keyBy('reddit_id');
        $this->assertSame(['a1', 'a2', 'b1'], $t->keys()->all(), 'old, adult, off-topic and low-scoring threads left out');
        $this->assertSame('complaint', $t['a1']->kind);
        $this->assertSame(95, $t['a1']->score, '70 + new + a few comments');
        $this->assertSame('mention', $t['a2']->kind);
        $this->assertSame('recommend', $t['b1']->kind);
        $this->assertContains('zenni', $t['b1']->matched);
        $this->assertSame('39dg', $t['a1']->brand->slug);
        $this->assertSame('https://www.reddit.com/r/glasses/comments/a1/x/', $t['a1']->url);
        $this->assertNull(RedditThread::where('reddit_id', 'c1')->first(), 'busy 2-day-old question scores 25+8-10 = 23');
        $this->assertSame(1, $this->tokenCalls, 'token reused');
        $this->assertStringNotContainsString('someone_private', json_encode(RedditThread::all()->toArray()), 'no usernames kept');

        $html = $this->actingAs($this->kay)->get('/reddit')->assertOk()->getContent();
        $this->assertStringContainsString('Unhappy customer', $html);
        $this->assertStringContainsString('never arrived and no refund yet', $html);
        $this->assertTrue(strpos($html, 'never arrived') < strpos($html, 'Zenni alternative'), 'best first');
    }

    public function test_a_thread_becomes_a_task_with_reply_guidance(): void
    {
        $this->fake();
        Reddit::refresh();
        $a1 = RedditThread::where('reddit_id', 'a1')->first();
        $this->actingAs($this->kay)->post("/reddit/{$a1->id}/task", ['assignee_id' => $this->leona->id])->assertSessionHas('status', 'Assigned to Leona, due today.');

        $task = Task::latest('id')->first();
        $this->assertSame('Reply on r/glasses: “Ordered from 39DollarGlasses, never arrived and no refund yet”', $task->title);
        $this->assertTrue($task->hot, 'complaints go on the Hotlist');
        $this->assertSame('2026-10-16', $task->due_on->format('Y-m-d'));
        $this->assertStringContainsString('Never ask for order or personal details in public', $task->details);
        $this->assertStringStartsWith('https://www.reddit.com/r/glasses/comments/a1/x/', $task->details);
        $this->assertSame('tasked', $a1->refresh()->status);
        $this->actingAs($this->kay)->post("/reddit/{$a1->id}/task", ['assignee_id' => $this->kay->id])->assertSessionHas('status', 'Someone already made that a task.');

        $b1 = RedditThread::where('reddit_id', 'b1')->first();
        $this->actingAs($this->kay)->post("/reddit/{$b1->id}/task", ['assignee_id' => $this->kay->id]);
        $this->assertStringContainsString('asking about Zenni', Task::latest('id')->first()->details);
        $this->assertStringContainsString('say you work there', Task::latest('id')->first()->details);

        // Skip, then a refresh keeps it skipped; restore brings it back.
        $a2 = RedditThread::where('reddit_id', 'a2')->first();
        $this->actingAs($this->kay)->post("/reddit/{$a2->id}/skip");
        Cache::forget('reddit:last');
        Reddit::refresh();
        $this->assertSame('skipped', $a2->refresh()->status);
        $html = $this->actingAs($this->kay)->get('/reddit')->getContent();
        $this->assertStringContainsString('Skipped by Kay', $html);
        $this->assertStringContainsString('Task · Leona', $html);
        $this->actingAs($this->kay)->post("/reddit/{$a2->id}/restore");
        $this->assertSame('new', $a2->refresh()->status);
    }

    public function test_failures_are_explained_to_admins_only(): void
    {
        $admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->status = 403;
        $this->fake();
        $this->assertFalse(Reddit::refresh());
        $this->assertStringContainsString('“script” app', $this->actingAs($admin)->get('/reddit')->getContent());
        $staff = $this->actingAs($this->kay)->get('/reddit')->getContent();
        $this->assertStringContainsString('The last update from Reddit failed.', $staff);
        $this->assertStringNotContainsString('“script” app', $staff);
    }

    public function test_setup_steps_until_configured(): void
    {
        config(['reddit.client_id' => null]);
        $admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->actingAs($admin)->get('/reddit')->assertOk()->assertSee('REDDIT_CLIENT_SECRET');
        $this->actingAs($this->kay)->get('/reddit')->assertOk()->assertSee('Ask an admin to connect it.');
    }
}
