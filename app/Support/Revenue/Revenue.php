<?php

namespace App\Support\Revenue;

use App\Models\Brand;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Keeps revenue_days in step with GA4: the last few days on every refresh (GA4 keeps adjusting
 * them for a day or two), plus one more month of history per refresh until a year and a bit is in.
 */
class Revenue
{
    private const LAST_SYNC = 'revenue:last-sync';
    private const ERROR = 'revenue:error';
    private const LOCK = 'revenue:refreshing';
    private const BACKFILLED = 'revenue:backfilled-to';

    public static function configured(): bool
    {
        return Ga4::configured();
    }

    public static function canSee(?User $user): bool
    {
        return $user !== null && ($user->is_admin || in_array(strtolower($user->email), config('revenue.viewers'), true));
    }

    /** Brands with a GA4 property, keyed by slug, in brand order. */
    public static function brands()
    {
        return Brand::whereIn('slug', array_keys(config('revenue.ga4.properties')))->orderBy('sort')->orderBy('name')->get()->keyBy('slug');
    }

    /** Our channel for a GA4 session: AI assistants and affiliate networks first, then GA4's own group. */
    public static function channel(string $group, string $source): string
    {
        $src = strtolower($source);
        foreach (config('revenue.ai_sources') as $s) {
            if (str_contains($src, $s)) {
                return 'ai';
            }
        }
        foreach (config('revenue.affiliate_sources') as $s) {
            if (str_contains($src, $s)) {
                return 'affiliate';
            }
        }

        return match (strtolower($group)) {
            'organic search' => 'organic_search',
            'paid search' => 'paid_search',
            'organic shopping', 'paid shopping' => 'shopping',
            'paid social' => 'paid_social',
            'organic social', 'organic video' => 'organic_social',
            'email', 'sms', 'mobile push notifications' => 'email',
            'affiliates' => 'affiliate',
            'referral' => 'referral',
            'direct' => 'direct',
            'display', 'paid video', 'paid other', 'cross-network', 'audio' => 'paid_other',
            default => 'other',
        };
    }

    /** Re-read GA4 for every configured brand between two dates (inclusive), a month at a time. */
    public static function sync(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $brands = self::brands();
        foreach (config('revenue.ga4.properties') as $slug => $propertyId) {
            $brand = $brands[$slug] ?? null;
            if (! $brand) {
                continue;
            }
            for ($start = $from; $start->lte($to); $start = $start->addDays(31)) {
                $end = $start->addDays(30)->min($to);
                $rows = Ga4::report($propertyId, $start->format('Y-m-d'), $end->format('Y-m-d'),
                    ['date', 'sessionDefaultChannelGroup', 'sessionSource'], ['sessions', 'transactions', 'purchaseRevenue']);

                $days = [];
                foreach ($rows as $r) {
                    [$date, $group, $source] = $r['d'] + ['', '', ''];
                    if (! preg_match('/^(\d{4})(\d{2})(\d{2})$/', $date, $m)) {
                        continue;
                    }
                    $key = "{$m[1]}-{$m[2]}-{$m[3]}|".self::channel($group, $source);
                    $days[$key] ??= ['sessions' => 0, 'orders' => 0, 'cents' => 0];
                    $days[$key]['sessions'] += (int) $r['m'][0];
                    $days[$key]['orders'] += (int) round((float) ($r['m'][1] ?? 0));
                    $days[$key]['cents'] += (int) round((float) ($r['m'][2] ?? 0) * 100);
                }

                $now = now();
                $insert = [];
                foreach ($days as $key => $v) {
                    [$day, $channel] = explode('|', $key);
                    $insert[] = ['brand_id' => $brand->id, 'day' => $day, 'channel' => $channel, 'source' => 'ga4',
                        'sessions' => $v['sessions'], 'orders' => $v['orders'], 'revenue_cents' => max(0, $v['cents']),
                        'created_at' => $now, 'updated_at' => $now];
                }
                DB::transaction(function () use ($brand, $start, $end, $insert) {
                    DB::table('revenue_days')->where('brand_id', $brand->id)->where('source', 'ga4')
                        ->whereBetween('day', [$start->format('Y-m-d'), $end->format('Y-m-d')])->delete();
                    foreach (array_chunk($insert, 500) as $chunk) {
                        DB::table('revenue_days')->insert($chunk);
                    }
                });
            }
        }
    }

    /** One refresh: the last four days, plus the next month of history if it isn't all in yet. */
    public static function refresh(bool $fullHistory = false): bool
    {
        try {
            $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
            self::sync($today->subDays(3), $today);

            $floor = $today->subDays(config('revenue.backfill_days'));
            $reached = Cache::get(self::BACKFILLED);
            $reached = is_string($reached) ? CarbonImmutable::parse($reached, config('app.timezone')) : $today->subDays(3);
            while ($reached->gt($floor)) {
                $start = $reached->subDays(31)->max($floor);
                self::sync($start, $reached->subDay());
                Cache::forever(self::BACKFILLED, $start->format('Y-m-d'));
                $reached = $start;
                if (! $fullHistory) {
                    break;
                }
            }

            Cache::forever(self::LAST_SYNC, now()->timestamp);
            Cache::forget(self::ERROR);

            return true;
        } catch (Throwable $e) {
            $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Reading GA4 failed ('.class_basename($e).').';
            Cache::forever(self::ERROR, $msg);
            Log::warning('Revenue sync failed', ['error' => $msg]);

            return false;
        }
    }

    /** Called on page views: if the numbers are stale, refresh them after the page has been sent. */
    public static function refreshIfStale(): void
    {
        if (! self::configured()) {
            return;
        }
        $last = Cache::get(self::LAST_SYNC);
        if (is_numeric($last) && now()->timestamp - (int) $last < config('revenue.refresh_minutes') * 60) {
            return;
        }
        if (! Cache::add(self::LOCK, 1, 300)) {
            return;
        }
        dispatch(function () {
            try {
                self::refresh();
            } finally {
                Cache::forget(self::LOCK);
            }
        })->afterResponse();
    }

    /** @return array{last: ?CarbonImmutable, error: ?string, history_from: ?string} */
    public static function status(): array
    {
        $last = Cache::get(self::LAST_SYNC);

        return [
            'last' => is_numeric($last) ? CarbonImmutable::createFromTimestamp((int) $last, config('app.timezone')) : null,
            'error' => Cache::get(self::ERROR),
            'history_from' => Cache::get(self::BACKFILLED),
        ];
    }

    public static function forget(): void
    {
        foreach ([self::LAST_SYNC, self::ERROR, self::LOCK, self::BACKFILLED, 'revenue:ga4:token'] as $k) {
            Cache::forget($k);
        }
    }
}
