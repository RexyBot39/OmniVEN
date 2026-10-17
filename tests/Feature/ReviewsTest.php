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
}
