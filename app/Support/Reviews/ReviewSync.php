<?php

namespace App\Support\Reviews;

use App\Models\Brand;
use App\Models\Review;
use App\Models\ReviewSnapshot;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Reads ratings and recent reviews from Shopper Approved and Trustpilot (read-only keys, server
 * side), records a daily rating snapshot, and turns each new low review into a Hotlist task.
 */
class ReviewSync
{
    public static function configured(string $platform): bool
    {
        return match ($platform) {
            'shopperapproved' => filled(config('reviews.shopperapproved.site_id')) && filled(config('reviews.shopperapproved.token')),
            'trustpilot' => filled(config('reviews.trustpilot.api_key')) && filled(config('reviews.trustpilot.business_unit_id')),
            default => false,
        };
    }

    public static function anyConfigured(): bool
    {
        return self::configured('shopperapproved') || self::configured('trustpilot');
    }

    public static function refresh(): bool
    {
        $ok = true;
        foreach (['shopperapproved', 'trustpilot'] as $p) {
            if (! self::configured($p)) {
                continue;
            }
            try {
                [$rating, $count, $reviews] = $p === 'shopperapproved' ? self::shopperApproved() : self::trustpilot();
                $brand = Brand::where('slug', config("reviews.platforms.$p.brand"))->first();
                ReviewSnapshot::updateOrCreate(
                    ['platform' => $p, 'brand_id' => $brand?->id, 'day' => CarbonImmutable::now(config('app.timezone'))->format('Y-m-d')],
                    ['rating' => $rating, 'review_count' => $count, 'manual' => false],
                );
                foreach ($reviews as $r) {
                    self::store($p, $brand, $r);
                }
                Cache::forever("reviews:$p:last", now()->timestamp);
                Cache::forget("reviews:$p:error");
            } catch (Throwable $e) {
                $ok = false;
                $label = config("reviews.platforms.$p.label");
                $msg = $e instanceof RuntimeException ? $e->getMessage() : "Reading $label failed (".class_basename($e).').';
                Cache::forever("reviews:$p:error", $msg);
                Log::warning('Review sync failed', ['platform' => $p, 'error' => $msg]);
            }
        }

        return $ok;
    }

    public static function refreshIfStale(): void
    {
        $stale = collect(['shopperapproved', 'trustpilot'])->filter(fn ($p) => self::configured($p))->contains(function ($p) {
            $last = Cache::get("reviews:$p:last");

            return ! is_numeric($last) || now()->timestamp - (int) $last >= config('reviews.refresh_minutes') * 60;
        });
        if (! $stale || ! Cache::add('reviews:refreshing', 1, 300)) {
            return;
        }
        dispatch(function () {
            try {
                self::refresh();
            } finally {
                Cache::forget('reviews:refreshing');
            }
        })->afterResponse();
    }

    public static function status(string $p): array
    {
        $t = Cache::get("reviews:$p:last");

        return ['last' => is_numeric($t) ? CarbonImmutable::createFromTimestamp((int) $t, config('app.timezone')) : null, 'error' => Cache::get("reviews:$p:error")];
    }

    public static function forget(): void
    {
        foreach (['shopperapproved', 'trustpilot'] as $p) {
            Cache::forget("reviews:$p:last");
            Cache::forget("reviews:$p:error");
        }
        Cache::forget('reviews:refreshing');
    }

    /** @return array{0: ?float, 1: ?int, 2: list<array>} */
    private static function shopperApproved(): array
    {
        $c = config('reviews.shopperapproved');
        $site = rawurlencode((string) $c['site_id']);
        $agg = Http::acceptJson()->timeout(config('reviews.timeout'))->get("{$c['url']}/aggregates/reviews/{$site}", ['token' => $c['token'], 'xml' => 'false']);
        if (! $agg->successful() || ! is_array($agg->json())) {
            throw new RuntimeException(self::explain('Shopper Approved', $agg->status(), 'SHOPPERAPPROVED_API_TOKEN', 'SHOPPERAPPROVED_SITE_ID'));
        }
        $a = $agg->json();
        $rating = self::num($a['average_rating'] ?? $a['average'] ?? $a['rating'] ?? null);
        $count = self::int($a['total_reviews'] ?? $a['reviews'] ?? $a['count'] ?? null);

        $res = Http::acceptJson()->timeout(config('reviews.timeout'))->get("{$c['url']}/reviews/{$site}", [
            'token' => $c['token'], 'xml' => 'false', 'limit' => 100, 'sort' => 'newest',
            'from' => CarbonImmutable::now()->subDays(30)->format('Y-m-d'),
        ]);
        if (! $res->successful() || ! is_array($res->json())) {
            throw new RuntimeException(self::explain('Shopper Approved', $res->status(), 'SHOPPERAPPROVED_API_TOKEN', 'SHOPPERAPPROVED_SITE_ID'));
        }
        $reviews = [];
        foreach ($res->json() as $key => $r) {
            if (! is_array($r)) {
                continue;
            }
            $stars = self::int($r['overall'] ?? $r['rating'] ?? $r['stars'] ?? null);
            $id = (string) ($r['review_id'] ?? $r['id'] ?? $key);
            if ($stars === null || $id === '') {
                continue;
            }
            $reviews[] = ['id' => $id, 'stars' => $stars, 'at' => $r['date'] ?? $r['review_date'] ?? null,
                'text' => $r['comments'] ?? $r['review'] ?? $r['text'] ?? null, 'url' => config('reviews.platforms.shopperapproved.url')];
        }

        return [$rating, $count, $reviews];
    }

    /** @return array{0: ?float, 1: ?int, 2: list<array>} */
    private static function trustpilot(): array
    {
        $c = config('reviews.trustpilot');
        $bu = rawurlencode((string) $c['business_unit_id']);
        $unit = Http::acceptJson()->timeout(config('reviews.timeout'))->get("{$c['url']}/business-units/{$bu}", ['apikey' => $c['api_key']]);
        if (! $unit->successful()) {
            throw new RuntimeException(self::explain('Trustpilot', $unit->status(), 'TRUSTPILOT_API_KEY', 'TRUSTPILOT_BUSINESS_UNIT_ID'));
        }
        $rating = self::num($unit->json('score.trustScore') ?? $unit->json('score.stars'));
        $count = self::int($unit->json('numberOfReviews.total'));

        $res = Http::acceptJson()->timeout(config('reviews.timeout'))->get("{$c['url']}/business-units/{$bu}/reviews", [
            'apikey' => $c['api_key'], 'perPage' => 50, 'orderBy' => 'createdat.desc',
        ]);
        if (! $res->successful()) {
            throw new RuntimeException(self::explain('Trustpilot', $res->status(), 'TRUSTPILOT_API_KEY', 'TRUSTPILOT_BUSINESS_UNIT_ID'));
        }
        $reviews = [];
        foreach ($res->json('reviews') ?? [] as $r) {
            $stars = self::int($r['stars'] ?? null);
            if ($stars === null || empty($r['id'])) {
                continue;
            }
            $reviews[] = ['id' => (string) $r['id'], 'stars' => $stars, 'at' => $r['createdAt'] ?? null,
                'text' => trim(($r['title'] ?? '').'. '.($r['text'] ?? ''), '. '), 'url' => 'https://www.trustpilot.com/reviews/'.rawurlencode((string) $r['id'])];
        }

        return [$rating, $count, $reviews];
    }

    private static function store(string $platform, ?Brand $brand, array $r): void
    {
        try {
            // Store in the app timezone, like every other timestamp in OmniVEN.
            $at = ($r['at'] ? CarbonImmutable::parse($r['at']) : CarbonImmutable::now())->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            $at = CarbonImmutable::now();
        }
        $review = Review::firstOrNew(['platform' => $platform, 'external_id' => mb_substr($r['id'], 0, 100)]);
        $isNew = ! $review->exists;
        $review->fill([
            'brand_id' => $brand?->id,
            'stars' => max(1, min(5, $r['stars'])),
            'reviewed_at' => $at,
            'excerpt' => $r['text'] ? Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $r['text']))), 290) : null,
            'url' => $r['url'],
        ])->save();

        if ($isNew && $review->stars <= config('reviews.task_max_stars') && $at->gte(CarbonImmutable::now()->subDays(config('reviews.task_max_age_days')))) {
            self::taskFor($review);
        }
    }

    /** A Hotlist task, due tomorrow, for the review owner (or the first admin). */
    private static function taskFor(Review $review): void
    {
        $owner = User::where('is_active', true)->whereRaw('lower(email) = ?', [strtolower((string) config('reviews.owner_email'))])->first()
            ?? User::where('is_active', true)->where('is_admin', true)->orderBy('id')->first();
        if (! $owner) {
            return;
        }
        $brandOk = $review->brand_id && Task::brandChoices()->pluck('id')->contains($review->brand_id);
        $task = Task::create([
            'title' => 'Reply to a '.$review->stars.'★ '.$review->platformLabel().' review',
            'details' => trim(($review->excerpt ? '“'.$review->excerpt.'”' : '')."\n\n".($review->url ? $review->url."\n\n" : '').'Reply publicly and offer to make it right. Escalate any chargeback, BBB, legal or prescription-error mention the same day.'),
            'assignee_id' => $owner->id,
            'brand_id' => $brandOk ? $review->brand_id : null,
            'hot' => true,
            'due_on' => CarbonImmutable::now(config('app.timezone'))->addDay()->format('Y-m-d'),
            'assigned_at' => now(),
        ]);
        $review->forceFill(['task_id' => $task->id])->save();
    }

    private static function explain(string $platform, int $status, string $key, string $id): string
    {
        return match (true) {
            $status === 401, $status === 403 => "{$platform} refused the key (HTTP {$status}). Check {$key}.",
            $status === 404 => "{$platform} didn’t find the account (HTTP 404). Check {$id}.",
            $status === 429 => "{$platform} is rate-limiting us (HTTP 429). It will try again later.",
            $status === 0 => "Couldn’t reach {$platform}.",
            default => "{$platform} answered HTTP {$status}.",
        };
    }

    private static function num(mixed $v): ?float
    {
        return is_numeric($v) ? round((float) $v, 2) : null;
    }

    private static function int(mixed $v): ?int
    {
        return is_numeric($v) ? (int) round((float) $v) : null;
    }
}
