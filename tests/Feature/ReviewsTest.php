<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\ReviewSnapshot;
use App\Models\Task;
use App\Models\User;
use App\Support\Reviews\ReviewSync;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use RefreshDatabase;

    private User $kay;
    private User $leona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        config([
            'reviews.shopperapproved.site_id' => '12345', 'reviews.shopperapproved.token' => 'sa-secret',
            'reviews.trustpilot.api_key' => 'tp-secret', 'reviews.trustpilot.business_unit_id' => 'bu1',
        ]);
        ReviewSync::forget();
        $this->kay = User::factory()->create(['email' => 'kaylie@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
        $this->leona = User::where('email', 'leona.p@39dollarglasses.com')->first()
            ?? User::factory()->create(['name' => 'Leona Park', 'email' => 'leona.p@39dollarglasses.com', 'is_admin' => true, 'is_active' => true]);
        $this->leona->update(['is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function fake(int $tpStatus = 200): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.shopperapproved.com/aggregates/*' => Http::response(['average_rating' => 4.71, 'total_reviews' => 21345]),
            'api.shopperapproved.com/reviews/*' => Http::response([
                '9001' => ['review_id' => 9001, 'overall' => 5, 'comments' => 'Great glasses, fast shipping.', 'date' => '2026-10-15 09:00:00'],
                '9002' => ['review_id' => 9002, 'overall' => 1, 'comments' => "Wrong <b>prescription</b>\nand no reply.", 'date' => '2026-10-15 12:00:00'],
                '9003' => ['review_id' => 9003, 'overall' => 2, 'comments' => 'Slow.', 'date' => '2026-09-20 12:00:00'],
            ]),
            'api.trustpilot.com/v1/business-units/bu1/reviews*' => $tpStatus === 200
                ? Http::response(['reviews' => [['id' => 'tp1', 'stars' => 2, 'title' => 'Meh', 'text' => 'Lenses scratched.', 'createdAt' => '2026-10-14T10:00:00Z']]])
                : Http::response('no', $tpStatus),
            'api.trustpilot.com/v1/business-units/bu1*' => $tpStatus === 200
                ? Http::response(['score' => ['trustScore' => 4.2], 'numberOfReviews' => ['total' => 3120]])
                : Http::response('no', $tpStatus),
        ]);
    }

    public function test_sync_records_ratings_and_turns_new_low_reviews_into_hotlist_tasks(): void
    {
        $this->fake();
        $this->assertTrue(ReviewSync::refresh());

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'aggregates/reviews/12345') && str_contains($r->url(), 'token=sa-secret'));
        $this->assertSame(4.71, ReviewSnapshot::where('platform', 'shopperapproved')->first()->rating);
        $this->assertSame(3120, ReviewSnapshot::where('platform', 'trustpilot')->first()->review_count);
        $this->assertSame(4, Review::count());

        $low = Review::where('external_id', '9002')->first();
        $this->assertSame('Wrong prescription and no reply.', $low->excerpt);
        $task = $low->task;
        $this->assertNotNull($task);
        $this->assertTrue($task->hot);
        $this->assertSame($this->leona->id, $task->assignee_id);
        $this->assertSame('Reply to a 1★ Shopper Approved review', $task->title);
        $this->assertStringContainsString('Wrong prescription', $task->details);
        $this->assertSame('2026-10-17', $task->due_on->format('Y-m-d'));
        $this->assertNotNull(Review::where('external_id', 'tp1')->first()->task_id);
        $this->assertNull(Review::where('external_id', '9001')->first()->task_id, '5★ makes no task');
        $this->assertNull(Review::where('external_id', '9003')->first()->task_id, 'old low reviews make no task');

        // Seeing the same reviews again doesn't make more tasks.
        ReviewSync::refresh();
        $this->assertSame(2, Task::where('title', 'like', 'Reply to a %')->count());
    }

    public function test_page_shows_ratings_waiting_replies_and_recent_reviews(): void
    {
        $this->fake();
        ReviewSync::refresh();
        ReviewSnapshot::create(['platform' => 'shopperapproved', 'brand_id' => null, 'day' => '2026-09-10', 'rating' => 4.6, 'review_count' => 21000]);
        \Illuminate\Support\Facades\Cache::forever('reviews:shopperapproved:last', now()->timestamp);
        \Illuminate\Support\Facades\Cache::forever('reviews:trustpilot:last', now()->timestamp);

        $html = $this->actingAs($this->kay)->get('/reviews')->assertOk()->getContent();
        $this->assertStringContainsString('4.7<small> ★</small>', $html);
        $this->assertStringContainsString('21,345 reviews', $html);
        $this->assertStringContainsString('+345 in 30 days', $html);
        $this->assertStringContainsString('▲ 0.11', $html);
        $waiting = substr($html, strpos($html, 'Waiting on a reply'), 4000);
        $this->assertStringContainsString('Wrong prescription and no reply.', $waiting);
        $this->assertStringContainsString('Task for', $waiting);
        $this->assertStringContainsString('href="https://www.trustpilot.com/reviews/tp1"', $html);

        $low = $this->actingAs($this->kay)->get('/reviews?stars=low')->getContent();
        $recentLow = substr($low, strpos($low, 'Recent reviews'));
        $this->assertStringNotContainsString('Great glasses', $recentLow);

        // Once the task is done it stops waiting.
        Review::where('external_id', '9002')->first()->task->update(['completed_at' => now()]);
        $html = $this->actingAs($this->kay)->get('/reviews')->getContent();
        $this->assertStringNotContainsString('Wrong prescription', substr($html, strpos($html, 'Waiting on a reply'), strpos($html, 'Recent reviews') - strpos($html, 'Waiting on a reply')));
    }

    public function test_anyone_updates_manual_platforms_and_failures_are_explained(): void
    {
        $this->actingAs($this->kay)->post('/reviews/rating', ['platform' => 'google', 'rating' => '4.4', 'review_count' => '812'])->assertRedirect(route('reviews'));
        $snap = ReviewSnapshot::where('platform', 'google')->first();
        $this->assertSame(4.4, $snap->rating);
        $this->assertTrue($snap->manual);
        $this->actingAs($this->kay)->post('/reviews/rating', ['platform' => 'google', 'rating' => '7'])->assertSessionHasErrors('rating');

        $this->fake(401);
        $this->assertFalse(ReviewSync::refresh());
        $admin = User::factory()->create(['email' => 'boss@39dollarglasses.com', 'is_admin' => true, 'is_active' => true, 'hotlist_popup_on' => today()]);
        $html = $this->actingAs($admin)->get('/reviews')->getContent();
        $this->assertStringContainsString('Check TRUSTPILOT_API_KEY', $html);
        $this->assertStringNotContainsString('tp-secret', $html);
        $this->assertSame(4.71, ReviewSnapshot::where('platform', 'shopperapproved')->first()->rating, 'Shopper Approved still updates');
    }

    /** Shopper Approved pages by "from" and "page": the last 30 days, and a history of $history reviews, newest first. */
    private function fakeHistory(int $history, bool $ignoresPage = false): void
    {
        Http::preventStrayRequests();
        $old = collect(range(1, $history))->map(fn ($i) => ['review_id' => 7000 + $i, 'overall' => $i % 5 === 0 ? 1 : 5, 'name' => 'Jane Customer', 'email' => 'jane@example.com',
            'comments' => 'Old review '.$i.'. '.str_repeat('Lovely frames and clear lenses. ', 12), 'date' => now()->subDays(40 + $i)->format('Y-m-d H:i:s')]);
        Http::fake([
            'api.shopperapproved.com/aggregates/*' => Http::response(['average_rating' => 4.71, 'total_reviews' => 21345]),
            'api.shopperapproved.com/reviews/*' => function (Request $r) use ($old, $ignoresPage) {
                if ($r['from'] === now()->subDays(30)->format('Y-m-d')) {
                    return Http::response(['9001' => ['review_id' => 9001, 'overall' => 5, 'comments' => 'Great glasses, fast shipping.', 'date' => '2026-10-15 09:00:00']]);
                }
                $size = (int) $r['limit'];
                $page = $ignoresPage ? 0 : (int) $r['page'];

                return Http::response($old->slice($page * $size, $size)->keyBy('review_id')->all());
            },
        ]);
    }

    public function test_shopper_approved_history_is_read_back_a_few_pages_at_a_time(): void
    {
        config(['reviews.trustpilot.api_key' => null, 'reviews.shopperapproved.page_size' => 10, 'reviews.shopperapproved.history_pages_per_refresh' => 2]);
        $this->fakeHistory(45);

        ReviewSync::refresh();
        $this->assertSame(21, Review::where('platform', 'shopperapproved')->count(), 'the last 30 days plus two pages of history');
        $this->assertFalse(ReviewSync::history()['done']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/reviews/12345') && ($r->data()['page'] ?? null) == 1 && $r['from'] === now()->subDays(1095)->format('Y-m-d'));

        ReviewSync::refresh();
        ReviewSync::refresh();
        $h = ReviewSync::history();
        $this->assertSame(46, $h['stored']);
        $this->assertTrue($h['done']);
        $this->assertSame(now()->subDays(85)->format('Y-m-d'), $h['oldest']->format('Y-m-d'));

        // The whole review is kept for analysis, but never the reviewer; old low reviews make no tasks.
        $r = Review::where('external_id', '7001')->first();
        $this->assertGreaterThan(300, mb_strlen($r->body));
        $this->assertLessThanOrEqual(300, mb_strlen($r->excerpt));
        $this->assertStringNotContainsString('Jane', json_encode(Review::all()->toArray()));
        $this->assertSame(0, Task::where('title', 'like', 'Reply to a %')->count());

        // Once it's all in, a refresh reads only the last 30 days.
        $before = count(Http::recorded());
        ReviewSync::refresh();
        $this->assertSame(2, count(Http::recorded()) - $before);
    }

    public function test_history_stops_if_the_api_keeps_sending_the_same_page(): void
    {
        config(['reviews.trustpilot.api_key' => null, 'reviews.shopperapproved.page_size' => 10]);
        $this->fakeHistory(45, ignoresPage: true);

        ReviewSync::refresh(fullHistory: true);
        $this->assertTrue(ReviewSync::history()['done']);
        $this->assertSame(11, Review::count());
        $this->assertLessThan(6, count(Http::recorded()));
    }

    public function test_ask_omniven_ai_reads_reviews_in_depth_and_the_page_links_to_it(): void
    {
        config(['reviews.trustpilot.api_key' => null, 'reviews.shopperapproved.page_size' => 10, 'assistant.key' => 'sk-test']);
        $this->fakeHistory(30);
        ReviewSync::refresh(fullHistory: true);

        $tools = new \App\Support\Assistant\Tools($this->kay);
        $this->assertContains('review_analysis', array_column($tools->definitions(), 'name'));
        $best = $tools->call('review_analysis', ['stars_min' => 5, 'limit' => 5]);
        $this->assertSame(25, $best['matching'], '30 old reviews, 6 of them 1★, plus one recent 5★');
        $this->assertSame([5 => 25], $best['by_stars']);
        $this->assertCount(5, $best['reviews']);
        $this->assertStringStartsWith('Old review', $best['reviews'][0]['text'], 'longest first');
        $this->assertGreaterThan(300, mb_strlen($best['reviews'][0]['text']));
        $this->assertSame('Shopper Approved', $best['reviews'][0]['platform']);
        $this->assertFalse($best['history']['still_loading']);
        $this->assertSame(31, $best['history']['shopper_approved_stored']);

        $worst = $tools->call('review_analysis', ['stars_max' => 2, 'contains' => 'OLD REVIEW 1']);
        $this->assertSame(['10', '15'], collect($worst['reviews'])->map(fn ($r) => explode('.', substr($r['text'], 11))[0])->sort()->values()->all());
        $this->assertSame(5, $tools->call('review_analysis', ['sort' => 'newest', 'days' => 30])['reviews'][0]['stars']);

        $html = $this->actingAs($this->kay)->get('/reviews')->assertOk()->getContent();
        $this->assertStringContainsString('31 Shopper Approved reviews stored for analysis, back to Aug 2026.', $html);
        $this->assertStringContainsString('Analyze with Ask OmniVEN AI', $html);
        $this->assertStringContainsString('focus=reputation', $html);
        $this->actingAs($this->kay)->get(route('assistant', ['focus' => 'reputation', 'q' => 'Do a deep analysis']))->assertOk()->assertSee('Do a deep analysis');
    }

    public function test_a_review_saved_by_another_sync_at_the_same_moment_is_updated_not_an_error(): void
    {
        config(['reviews.trustpilot.api_key' => null, 'reviews.shopperapproved.page_size' => 10]);
        $this->fakeHistory(3);
        // The page's refresh and the console command both reach review 7002 at once: the other one inserts it first.
        $raced = false;
        Review::creating(function (Review $r) use (&$raced) {
            if (! $raced && $r->external_id === '7002') {
                $raced = true;
                \Illuminate\Support\Facades\DB::table('reviews')->insert(['platform' => 'shopperapproved', 'external_id' => '7002', 'stars' => 5,
                    'reviewed_at' => now(), 'excerpt' => 'old', 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        $this->assertTrue(ReviewSync::refresh(fullHistory: true));
        $this->assertTrue($raced);
        $this->assertNull(ReviewSync::status('shopperapproved')['error']);
        $this->assertSame(1, Review::where('external_id', '7002')->count());
        $this->assertStringStartsWith('Old review 2.', Review::where('external_id', '7002')->value('body'));
        $this->assertSame(4, Review::count());
    }

    public function test_html_entities_become_plain_text_and_database_errors_hide_the_sql(): void
    {
        config(['reviews.trustpilot.api_key' => null]);
        Http::preventStrayRequests();
        Http::fake([
            'api.shopperapproved.com/aggregates/*' => Http::response(['average_rating' => 4.7, 'total_reviews' => 10]),
            'api.shopperapproved.com/reviews/*' => Http::response(['1' => ['review_id' => 1, 'overall' => 4, 'comments' => 'You don&rsquo;t explain if a second pair must be the same Rx &amp; frame.', 'date' => '2026-10-15 09:00:00']]),
        ]);
        ReviewSync::refresh();
        $this->assertSame('You don’t explain if a second pair must be the same Rx & frame.', Review::first()->body);

        Review::saving(fn () => throw new \Illuminate\Database\QueryException('pgsql', 'insert into "reviews" values (secret text)', [], new \Exception('boom')));
        Review::query()->delete();
        $this->assertFalse(ReviewSync::refresh());
        $this->assertSame('Reading Shopper Approved failed (QueryException).', ReviewSync::status('shopperapproved')['error']);
    }

    public function test_the_console_command_shows_its_progress_and_holds_off_the_page_refresh(): void
    {
        config(['reviews.trustpilot.api_key' => null, 'reviews.shopperapproved.page_size' => 10]);
        $this->fakeHistory(25);

        $this->artisan('omniven:reviews-sync --history')
            ->expectsOutputToContain('Page 1: 10 new')
            ->expectsOutputToContain('Page 3: 5 new, back to')
            ->expectsOutputToContain('26 Shopper Approved reviews stored')
            ->assertExitCode(0);
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has('reviews:refreshing'), 'the page can refresh again afterwards');
    }
}
