<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PulseToPost;
use Carbon\Carbon;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PulseToPostTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'p2p-secret-token-123';
    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OmniVenSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', config('app.timezone')));
        config(['pulsetopost.token' => self::TOKEN, 'pulsetopost.base_url' => 'https://pulsetopost.com']);
        PulseToPost::forget();
        $this->me = User::factory()->create(['email' => 'kaylie@39dollarglasses.com', 'is_active' => true, 'hotlist_popup_on' => today()]);
    }

    private function summary(array $over = []): array
    {
        return array_replace_recursive([
            'as_of' => '2026-10-16T14:02:11+00:00',
            'counts' => ['awaiting_review' => 9, 'gated' => 2, 'drafts_awaiting_review' => 4, 'approved_this_week' => 3],
            'spend' => ['today_cents' => 14, 'month_to_date_cents' => 418],
            'links' => ['review_queue' => 'https://pulsetopost.com/opportunities', 'drafts' => 'https://pulsetopost.com/drafts', 'dashboard' => 'https://pulsetopost.com/'],
        ], $over);
    }

    private function health(array $over = []): array
    {
        return array_replace_recursive([
            'as_of' => '2026-10-16T14:02:11+00:00',
            'last_run' => ['status' => 'success', 'started_at' => null, 'finished_at' => null, 'items_seen' => 86, 'items_kept' => 9, 'error' => null],
            'hours_since_last_run' => 8.1,
            'queries' => ['active' => 77, 'auto_disabled' => 2],
            'queue_depth' => 0,
        ], $over);
    }

    /** Http::fake stubs stack (the first match wins), so one stub reads whatever the test set last. */
    private ?array $nextSummary = null;
    private ?array $nextHealth = null;
    private bool $faked = false;

    private function fake(?array $summary, ?array $health = null): void
    {
        $this->nextSummary = $summary;
        $this->nextHealth = $health ?? $this->health();
        if (! $this->faked) {
            $this->faked = true;
            Http::fake(fn (Request $r) => match ($r->url()) {
                'https://pulsetopost.com/api/omniven/summary' => $this->nextSummary === null ? Http::response('down', 503) : Http::response($this->nextSummary),
                'https://pulsetopost.com/api/omniven/health' => Http::response($this->nextHealth),
            });
        }
    }

    private function card(): string
    {
        $html = $this->actingAs($this->me)->get('/')->assertOk()->getContent();
        $start = strpos($html, 'class="p2p wrap"');
        $this->assertNotFalse($start, 'no PulseToPost card');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    public function test_card_shows_counts_and_health_and_never_sends_the_token_to_the_browser(): void
    {
        $this->fake($this->summary());
        $html = $this->actingAs($this->me)->get('/')->getContent();
        $card = $this->card();

        $this->assertStringContainsString('href="https://pulsetopost.com/opportunities"', $card);
        $this->assertMatchesRegularExpression('#opportunities"[^>]*><b>9</b></a>\s*<span>awaiting review</span>#', $card);
        $this->assertStringContainsString('p2p-fig gated"><b>2</b>', $card);
        $this->assertStringContainsString('<b>4</b></a>', $card);
        $this->assertStringContainsString('$4.18', $card);
        $this->assertStringContainsString('Last run 8h ago, 9 kept', $card);
        $this->assertStringContainsString('p2p-run good', $card);
        $this->assertStringContainsString('0 queued', $card);
        $this->assertStringContainsString('2 queries auto-disabled', $card);
        $this->assertStringContainsString('as of Oct 16, 10:02 AM', $card);
        $this->assertStringNotContainsString(self::TOKEN, $html);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://pulsetopost.com/api/omniven/summary' && $r->hasHeader('Authorization', 'Bearer '.self::TOKEN));
        Http::assertSentCount(2);
        // Cached for five minutes: more page loads don't call PulseToPost again.
        $this->card();
        Http::assertSentCount(2);
        $this->travel(6)->minutes();
        $this->card();
        Http::assertSentCount(4);
    }

    public function test_unreachable_shows_status_unavailable_with_last_good_read_and_no_old_numbers(): void
    {
        $this->assertStringContainsString('No good read yet', (function () {
            $this->fake(null);

            return $this->card();
        })());

        PulseToPost::forget();
        $this->fake($this->summary());
        $this->card();

        $this->travel(10)->minutes();
        $this->fake(null);
        $card = $this->card();
        $this->assertStringContainsString('Status unavailable', $card);
        $this->assertStringContainsString('Last good read Oct 16, 10:00 AM', $card);
        $this->assertStringNotContainsString('awaiting review', $card);
        $this->assertStringNotContainsString('$4.18', $card);
        // The rest of the dashboard still renders.
        $this->actingAs($this->me)->get('/')->assertSee('data-tab="tasks"', false);

    }

    public function test_nulls_are_gaps_and_unknown_queue_depth_is_not_empty(): void
    {
        $this->fake(
            $this->summary(['counts' => ['awaiting_review' => null, 'gated' => null], 'spend' => ['month_to_date_cents' => null]]),
            $this->health(['queue_depth' => null, 'hours_since_last_run' => 31.2, 'queries' => ['auto_disabled' => 0]]),
        );
        $card = $this->card();
        $this->assertStringContainsString('<b>—</b>', $card);
        $this->assertStringNotContainsString('<b>0</b>', $card);
        $this->assertStringContainsString('Queue depth unknown', $card);
        $this->assertStringNotContainsString('0 queued', $card);
        $this->assertStringContainsString('No run in 31 hours', $card);
        $this->assertStringContainsString('p2p-run bad', $card);
        $this->assertStringNotContainsString('auto-disabled', $card);
    }

    public function test_strings_are_escaped_and_foreign_links_dropped(): void
    {
        $this->fake(
            $this->summary(['links' => ['review_queue' => 'javascript:alert(1)', 'drafts' => 'https://evil.example/drafts']]),
            $this->health(['last_run' => ['status' => '<img src=x onerror=alert(1)>']]),
        );
        $card = $this->card();
        $this->assertStringNotContainsString('javascript:', $card);
        $this->assertStringNotContainsString('evil.example', $card);
        $this->assertStringNotContainsString('<img src=x', $card);
        $this->assertStringContainsString('&lt;img src=x', $card);
        $this->assertStringContainsString('p2p-run bad', $card);
        $this->assertStringContainsString('<b>9</b>', $card);
    }

    public function test_a_timeout_is_status_unavailable_too(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));
        $card = $this->card();
        $this->assertStringContainsString('Status unavailable', $card);
        $this->assertStringContainsString('No good read yet', $card);
    }

    public function test_no_token_means_no_card_and_no_requests(): void
    {
        config(['pulsetopost.token' => null]);
        Http::fake();
        $html = $this->actingAs($this->me)->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('class="p2p wrap"', $html);
        Http::assertNothingSent();
    }
}
