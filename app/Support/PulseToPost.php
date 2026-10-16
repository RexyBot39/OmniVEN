<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads PulseToPost's read-only OmniVEN API (/summary and /health) for the dashboard card.
 *
 * Server side only: the bearer token is sent to PulseToPost and nowhere else. A good read is cached for
 * five minutes. A failed read is cached briefly and shows as "status unavailable" with the time of the
 * last good read, never with old numbers. Only counts, spend, run figures and links to PulseToPost
 * are kept; no titles, claims or draft text.
 */
class PulseToPost
{
    private const READ = 'pulsetopost:read';
    private const LAST_GOOD = 'pulsetopost:last-good-at';

    public static function configured(): bool
    {
        return filled(config('pulsetopost.token')) && filled(config('pulsetopost.base_url'));
    }

    /** The card's data, or null when PulseToPost isn't set up. Never throws. */
    public static function card(): ?array
    {
        if (! self::configured()) {
            return null;
        }
        try {
            $read = Cache::get(self::READ);
            if (! is_array($read)) {
                $read = self::read();
                Cache::put(self::READ, $read, now()->addSeconds($read['ok'] ? config('pulsetopost.cache_seconds') : config('pulsetopost.retry_seconds')));
            }
            $lastGood = Cache::get(self::LAST_GOOD);
        } catch (Throwable $e) {
            Log::warning('PulseToPost card failed', ['error' => class_basename($e)]);
            $read = ['ok' => false];
            $lastGood = null;
        }

        return self::present($read, is_numeric($lastGood) ? (int) $lastGood : null);
    }

    public static function forget(): void
    {
        Cache::forget(self::READ);
        Cache::forget(self::LAST_GOOD);
    }

    /** One live read. The summary decides whether the card is up; /health failing only blanks the status line. */
    private static function read(): array
    {
        $summary = self::get('summary');
        if (! is_array($summary) || ! is_array($summary['counts'] ?? null)) {
            return ['ok' => false];
        }
        $health = self::get('health');
        $now = now()->timestamp;
        Cache::forever(self::LAST_GOOD, $now);

        $counts = $summary['counts'];
        $run = is_array($health['last_run'] ?? null) ? $health['last_run'] : [];

        return [
            'ok' => true,
            'read_at' => $now,
            'as_of' => self::time($summary['as_of'] ?? null),
            'awaiting_review' => self::count($counts['awaiting_review'] ?? null),
            'gated' => self::count($counts['gated'] ?? null),
            'drafts' => self::count($counts['drafts_awaiting_review'] ?? null),
            'approved_week' => self::count($counts['approved_this_week'] ?? null),
            'spend_mtd_cents' => self::count($summary['spend']['month_to_date_cents'] ?? null),
            'links' => [
                'review_queue' => self::link($summary['links']['review_queue'] ?? null),
                'drafts' => self::link($summary['links']['drafts'] ?? null),
                'dashboard' => self::link($summary['links']['dashboard'] ?? null),
            ],
            'health' => is_array($health) ? [
                'hours' => is_numeric($health['hours_since_last_run'] ?? null) ? max(0.0, (float) $health['hours_since_last_run']) : null,
                'status' => is_string($run['status'] ?? null) ? mb_substr($run['status'], 0, 30) : null,
                'kept' => self::count($run['items_kept'] ?? null),
                'queue_depth' => self::count($health['queue_depth'] ?? null),
                'auto_disabled' => self::count($health['queries']['auto_disabled'] ?? null),
            ] : null,
        ];
    }

    private static function get(string $endpoint): ?array
    {
        try {
            $res = Http::withToken((string) config('pulsetopost.token'))->acceptJson()
                ->connectTimeout(2)->timeout(config('pulsetopost.timeout'))
                ->get(config('pulsetopost.base_url').'/api/omniven/'.$endpoint);
            if ($res->successful() && is_array($res->json())) {
                return $res->json();
            }
            Log::warning('PulseToPost read failed', ['endpoint' => $endpoint, 'status' => $res->status()]);
        } catch (Throwable $e) {
            // Class only: messages can echo response bodies, and the token must never reach the logs.
            Log::warning('PulseToPost read failed', ['endpoint' => $endpoint, 'error' => class_basename($e)]);
        }

        return null;
    }

    /** What the card shows. Nulls stay null so the view renders a gap, not a zero. */
    private static function present(array $read, ?int $lastGood): array
    {
        $tz = config('app.timezone');
        $base = config('pulsetopost.base_url');
        if (! ($read['ok'] ?? false)) {
            return [
                'ok' => false,
                'last_good' => $lastGood ? CarbonImmutable::createFromTimestamp($lastGood, $tz) : null,
                'dashboard' => $base.'/',
            ];
        }

        $h = $read['health'];
        $line = null;
        $tone = '';
        if ($h === null) {
            $line = 'Run status unavailable';
            $tone = 'warn';
        } elseif ($h['hours'] === null) {
            $line = 'Last run unknown';
            $tone = 'warn';
        } elseif ($h['hours'] > config('pulsetopost.stale_hours')) {
            $line = 'No run in '.(int) round($h['hours']).' hours';
            $tone = 'bad';
        } else {
            $ago = $h['hours'] < 1 ? 'under an hour ago' : (int) round($h['hours']).'h ago';
            $failed = $h['status'] !== null && ! in_array(strtolower($h['status']), ['success', 'succeeded', 'ok', 'completed'], true);
            $line = $failed ? 'Last run '.$ago.': '.$h['status'] : 'Last run '.$ago.($h['kept'] !== null ? ', '.$h['kept'].' kept' : '');
            $tone = $failed ? 'bad' : 'good';
        }

        return [
            'ok' => true,
            'as_of' => CarbonImmutable::createFromTimestamp($read['as_of'] ?? $read['read_at'], $tz),
            'awaiting_review' => $read['awaiting_review'],
            'gated' => $read['gated'],
            'drafts' => $read['drafts'],
            'approved_week' => $read['approved_week'],
            'spend' => $read['spend_mtd_cents'] === null ? null : '$'.number_format($read['spend_mtd_cents'] / 100, 2),
            'links' => $read['links'],
            'dashboard' => $read['links']['dashboard'] ?? $base.'/',
            'status' => $line,
            'tone' => $tone,
            // Null is "the queue driver couldn't say", the opposite of an empty queue.
            'queue' => $h === null ? null : ($h['queue_depth'] === null ? 'Queue depth unknown' : $h['queue_depth'].' queued'),
            'auto_disabled' => $h['auto_disabled'] ?? null,
        ];
    }

    private static function count(mixed $v): ?int
    {
        return is_int($v) || (is_float($v) && floor($v) === $v) ? max(0, (int) $v) : null;
    }

    private static function time(mixed $v): ?int
    {
        if (! is_string($v)) {
            return null;
        }
        try {
            return CarbonImmutable::parse($v)->timestamp;
        } catch (Throwable) {
            return null;
        }
    }

    /** Only https links back into PulseToPost itself; anything else from the API is dropped. */
    private static function link(mixed $url): ?string
    {
        $host = parse_url(config('pulsetopost.base_url'), PHP_URL_HOST);

        return is_string($url) && str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL)
            && strcasecmp((string) parse_url($url, PHP_URL_HOST), (string) $host) === 0 ? $url : null;
    }
}
