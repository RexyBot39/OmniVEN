<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\RedditThread;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Finds Reddit threads worth a reply: ones that name our brands, shoppers asking for an alternative to
 * a competitor, and buying questions in the eyewear subreddits. Read-only, app-only login kept on the
 * server; stores public thread facts (title, subreddit, link, counts), never usernames or post text.
 */
class Reddit
{
    public static function configured(): bool
    {
        return filled(config('reddit.client_id')) && filled(config('reddit.client_secret'));
    }

    public static function status(): array
    {
        $t = Cache::get('reddit:last');

        return ['last' => is_numeric($t) ? CarbonImmutable::createFromTimestamp((int) $t, config('app.timezone')) : null, 'error' => Cache::get('reddit:error')];
    }

    public static function forget(): void
    {
        foreach (['reddit:last', 'reddit:error', 'reddit:refreshing', 'reddit:token'] as $k) {
            Cache::forget($k);
        }
    }

    public static function refreshIfStale(): void
    {
        if (! self::configured()) {
            return;
        }
        $last = Cache::get('reddit:last');
        if (is_numeric($last) && now()->timestamp - (int) $last < config('reddit.refresh_minutes') * 60) {
            return;
        }
        if (! Cache::add('reddit:refreshing', 1, 600)) {
            return;
        }
        dispatch(function () {
            try {
                self::refresh();
            } finally {
                Cache::forget('reddit:refreshing');
            }
        })->afterResponse();
    }

    public static function refresh(): bool
    {
        try {
            $posts = [];
            foreach (config('reddit.brands') as $slug => $terms) {
                foreach ($terms as $q) {
                    foreach (self::search('/search', $q) as $p) {
                        $posts[$p['id']] = $p;
                    }
                }
            }
            $subs = implode('+', config('reddit.subreddits'));
            foreach (config('reddit.topics') as $q) {
                foreach (self::search("/r/{$subs}/search", $q, true) as $p) {
                    $posts[$p['id']] ??= $p;
                }
            }
            $brands = Brand::whereIn('slug', array_keys(config('reddit.brands')))->get()->keyBy('slug');
            foreach ($posts as $p) {
                self::store($p, $brands);
            }
            // Old threads nobody acted on drop off.
            RedditThread::where('status', 'new')->where('posted_at', '<', now()->subDays(config('reddit.max_age_days') + 7))->delete();
            Cache::forever('reddit:last', now()->timestamp);
            Cache::forget('reddit:error');

            return true;
        } catch (Throwable $e) {
            $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Reading Reddit failed ('.class_basename($e).').';
            Cache::forever('reddit:error', $msg);
            Log::warning('Reddit sync failed', ['error' => $msg]);

            return false;
        }
    }

    /**
     * Score a thread 0–100 and say what kind of opportunity it is, or null when it isn't one.
     * Brand mentions come first (complaints most of all), then shoppers asking about a competitor,
     * then plain buying questions. Newer threads and ones with a few comments score higher.
     */
    public static function score(array $p, ?string &$brandSlug = null): ?array
    {
        $text = mb_strtolower($p['title'].' '.$p['body']);
        $matched = [];
        $brandSlug = null;
        foreach (config('reddit.brands') as $slug => $terms) {
            foreach ($terms as $t) {
                $t = trim($t, '"');
                if (str_contains(str_replace([' ', '-', '$'], '', $text), str_replace([' ', '-', '$'], '', mb_strtolower($t)))) {
                    $brandSlug ??= $slug;
                    $matched[] = $t;
                }
            }
        }
        $competitors = array_values(array_filter(config('reddit.competitors'), fn ($c) => preg_match('/\b'.preg_quote($c, '/').'\b/u', $text)));
        $asks = (bool) preg_match('/\?|\b(recommend|recommendation|suggest|alternative|alternatives|where (to|can i|should i) (buy|get|order)|best place|worth it|anyone (tried|used)|experience with|vs\.?|versus|cheap|affordable|budget)\b/u', $text);
        $complaint = array_values(array_filter(config('reddit.complaint_words'), fn ($w) => str_contains($text, $w)));

        if ($brandSlug) {
            $kind = $complaint ? 'complaint' : 'mention';
            $score = $complaint ? 70 : 50;
        } elseif ($competitors && $asks) {
            $kind = 'recommend';
            $score = 40;
        } elseif ($asks && ($p['topic'] ?? false)) {
            $kind = 'question';
            $score = 25;
        } else {
            return null;
        }

        $ageH = max(0, (now()->timestamp - $p['created']) / 3600);
        $score += $ageH < 24 ? 15 : ($ageH < 72 ? 8 : 0);
        $score += match (true) {
            $p['comments'] === 0 => 5,
            $p['comments'] <= 40 => 10,
            $p['comments'] > 150 => -10,
            default => 0,
        };

        return ['kind' => $kind, 'score' => max(0, min(100, $score)), 'matched' => array_values(array_unique([...$matched, ...$competitors, ...$complaint]))];
    }

    private static function store(array $p, $brands): void
    {
        if ($p['created'] < now()->subDays(config('reddit.max_age_days'))->timestamp) {
            return;
        }
        $slug = null;
        $s = self::score($p, $slug);
        $existing = RedditThread::where('reddit_id', $p['id'])->first();
        if (! $s || $s['score'] < config('reddit.min_score')) {
            // Keep threads someone already acted on; drop ones that no longer qualify.
            if ($existing?->status === 'new') {
                $existing->delete();
            }

            return;
        }
        $fields = [
            'subreddit' => mb_substr($p['subreddit'], 0, 64), 'title' => mb_substr($p['title'], 0, 300),
            'url' => 'https://www.reddit.com'.mb_substr($p['permalink'], 0, 470),
            'posted_at' => CarbonImmutable::createFromTimestamp($p['created'], config('app.timezone')),
            'comments' => $p['comments'], 'votes' => $p['votes'],
            'kind' => $s['kind'], 'score' => $s['score'], 'matched' => $s['matched'], 'brand_id' => $slug ? $brands[$slug]?->id : null,
        ];
        $existing ? $existing->update($fields) : RedditThread::create($fields + ['reddit_id' => $p['id'], 'status' => 'new']);
    }

    /** One search (newest first, past week), as plain arrays. */
    private static function search(string $path, string $q, bool $inSubs = false): array
    {
        $res = self::http()->get('https://oauth.reddit.com'.$path, array_filter([
            'q' => $q, 'sort' => 'new', 't' => 'week', 'limit' => 50, 'type' => 'link', 'restrict_sr' => $inSubs ? 'on' : null, 'raw_json' => 1,
        ]));
        if ($res->status() === 401) {
            Cache::forget('reddit:token');
        }
        if (! $res->successful()) {
            throw new RuntimeException(self::explain($res->status()));
        }
        $out = [];
        foreach ($res->json('data.children') ?? [] as $c) {
            $d = $c['data'] ?? [];
            if (empty($d['id']) || ! empty($d['over_18']) || ! empty($d['locked']) || ! empty($d['archived']) || empty($d['permalink'])) {
                continue;
            }
            $out[] = ['id' => (string) $d['id'], 'subreddit' => (string) ($d['subreddit'] ?? ''), 'title' => (string) ($d['title'] ?? ''),
                'body' => Str::limit((string) ($d['selftext'] ?? ''), 4000, ''), 'permalink' => (string) $d['permalink'],
                'created' => (int) ($d['created_utc'] ?? 0), 'comments' => (int) ($d['num_comments'] ?? 0), 'votes' => (int) ($d['score'] ?? 0), 'topic' => $inSubs];
        }

        return $out;
    }

    private static function http(): PendingRequest
    {
        return Http::withToken(self::token())->withHeaders(['User-Agent' => config('reddit.user_agent')])->acceptJson()->timeout(config('reddit.timeout'));
    }

    private static function token(): string
    {
        $cached = Cache::get('reddit:token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $res = Http::asForm()->withBasicAuth((string) config('reddit.client_id'), (string) config('reddit.client_secret'))
            ->withHeaders(['User-Agent' => config('reddit.user_agent')])->timeout(config('reddit.timeout'))
            ->post('https://www.reddit.com/api/v1/access_token', ['grant_type' => 'client_credentials']);
        $token = $res->json('access_token');
        if (! $res->successful() || ! is_string($token)) {
            throw new RuntimeException($res->status() === 401
                ? 'Reddit refused the app login (HTTP 401). Check REDDIT_CLIENT_ID and REDDIT_CLIENT_SECRET.'
                : self::explain($res->status()));
        }
        Cache::put('reddit:token', $token, max(60, (int) $res->json('expires_in', 3600) - 120));

        return $token;
    }

    private static function explain(int $status): string
    {
        return match ($status) {
            401 => 'Reddit refused the app login (HTTP 401). Check REDDIT_CLIENT_ID and REDDIT_CLIENT_SECRET.',
            403 => 'Reddit blocked the request (HTTP 403). Make sure the app is a “script” app and REDDIT_USER_AGENT names it.',
            429 => 'Reddit is rate-limiting us (HTTP 429). It will try again later.',
            0 => 'Couldn’t reach Reddit.',
            default => "Reddit answered HTTP {$status}.",
        };
    }
}
