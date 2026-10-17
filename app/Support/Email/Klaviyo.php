<?php

namespace App\Support\Email;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Email & SMS health from Klaviyo's reporting API (read-only, server side): the last 30 days of
 * campaigns and flows, orders credited to them, whether order events carry a value at all, and the
 * main list's size. Only names and numbers are kept; never profiles or email addresses.
 */
class Klaviyo
{
    private const REPORT = 'klaviyo:report';
    private const LAST = 'klaviyo:last';
    private const ERROR = 'klaviyo:error';

    public static function configured(): bool
    {
        return filled(config('klaviyo.key'));
    }

    /** The last good report, or null. */
    public static function report(): ?array
    {
        $r = Cache::get(self::REPORT);

        return is_array($r) ? $r : null;
    }

    public static function status(): array
    {
        $t = Cache::get(self::LAST);

        return ['last' => is_numeric($t) ? CarbonImmutable::createFromTimestamp((int) $t, config('app.timezone')) : null, 'error' => Cache::get(self::ERROR)];
    }

    public static function forget(): void
    {
        foreach ([self::REPORT, self::LAST, self::ERROR, 'klaviyo:refreshing'] as $k) {
            Cache::forget($k);
        }
    }

    public static function refreshIfStale(): void
    {
        if (! self::configured()) {
            return;
        }
        $last = Cache::get(self::LAST);
        if (is_numeric($last) && now()->timestamp - (int) $last < config('klaviyo.refresh_minutes') * 60) {
            return;
        }
        if (! Cache::add('klaviyo:refreshing', 1, 600)) {
            return;
        }
        dispatch(function () {
            try {
                self::refresh();
            } finally {
                Cache::forget('klaviyo:refreshing');
            }
        })->afterResponse();
    }

    public static function refresh(): bool
    {
        try {
            [$campaignRows, $valuesOk] = self::valuesReport('campaign');
            [$flowRows] = self::valuesReport('flow', $valuesOk);
            $campaignNames = self::campaignNames();
            $flowNames = self::flowNames();

            $campaigns = [];
            foreach ($campaignRows as $r) {
                $id = $r['groupings']['campaign_id'] ?? null;
                $s = $r['statistics'] ?? [];
                if (! $id || ($s['recipients'] ?? 0) < config('klaviyo.min_recipients')) {
                    continue;
                }
                $campaigns[] = ['id' => $id, 'name' => $campaignNames[$id]['name'] ?? 'Campaign '.$id, 'sent' => $campaignNames[$id]['sent'] ?? null,
                    'channel' => $r['groupings']['send_channel'] ?? 'email'] + self::stats($s, $valuesOk);
            }
            usort($campaigns, fn ($a, $b) => strcmp((string) $b['sent'], (string) $a['sent']));

            $flows = [];
            foreach ($flowRows as $r) {
                $id = $r['groupings']['flow_id'] ?? null;
                if (! $id) {
                    continue;
                }
                $s = self::stats($r['statistics'] ?? [], $valuesOk);
                $flows[$id] ??= ['id' => $id, 'name' => $flowNames[$id] ?? 'Flow '.$id] + array_map(fn ($v) => is_int($v) ? 0 : $v, $s);
                foreach ($s as $k => $v) {
                    if (is_int($v)) {
                        $flows[$id][$k] += $v;
                    }
                }
            }
            $flows = array_values($flows);
            usort($flows, fn ($a, $b) => [$b['orders'], $b['recipients']] <=> [$a['orders'], $a['recipients']]);

            [$orderEvents, $orderValue] = self::orderEvents();
            $profiles = self::listSize();

            Cache::forever(self::REPORT, [
                'at' => now()->timestamp,
                'values_ok' => $valuesOk && $orderValue > 0,
                'order_events' => $orderEvents,
                'order_value_cents' => (int) round($orderValue * 100),
                'campaigns' => $campaigns,
                'flows' => array_slice($flows, 0, 15),
                'flow_totals' => self::sum($flows),
                'profiles' => $profiles,
            ]);
            Cache::forever(self::LAST, now()->timestamp);
            Cache::forget(self::ERROR);

            return true;
        } catch (Throwable $e) {
            $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Reading Klaviyo failed ('.class_basename($e).').';
            Cache::forever(self::ERROR, $msg);
            Log::warning('Klaviyo sync failed', ['error' => $msg]);

            return false;
        }
    }

    private static function http(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Klaviyo-API-Key '.config('klaviyo.key'),
            'revision' => config('klaviyo.revision'),
            'Accept' => 'application/vnd.api+json',
        ])->asJson()->timeout(config('klaviyo.timeout'));
    }

    private static function check(Response $res): Response
    {
        if ($res->successful()) {
            return $res;
        }

        throw new RuntimeException(match (true) {
            $res->status() === 401 => 'Klaviyo refused the key (HTTP 401). Check KLAVIYO_PRIVATE_KEY.',
            $res->status() === 403 => 'The Klaviyo key can’t read this (HTTP 403). Give it read access to campaigns, flows, lists and metrics.',
            $res->status() === 429 => 'Klaviyo is rate-limiting us (HTTP 429). It will try again later.',
            default => 'Klaviyo answered HTTP '.$res->status().'.'.($res->json('errors.0.detail') ? ' '.mb_substr((string) $res->json('errors.0.detail'), 0, 160) : ''),
        });
    }

    /** A campaign or flow values report for the last 30 days. Retries without order values if the metric has none. */
    private static function valuesReport(string $type, bool $withValues = true): array
    {
        $stats = ['recipients', 'delivered', 'opens_unique', 'clicks_unique', 'unsubscribe_uniques', 'spam_complaints', 'conversions', 'conversion_uniques'];
        $send = fn (bool $values) => self::http()->post(config('klaviyo.url')."/{$type}-values-reports/", ['data' => ['type' => "{$type}-values-report", 'attributes' => [
            'statistics' => $values ? [...$stats, 'conversion_value'] : $stats,
            'timeframe' => ['key' => 'last_30_days'],
            'conversion_metric_id' => config('klaviyo.order_metric_id'),
        ]]]);
        $res = $send($withValues);
        if ($withValues && $res->status() === 400) {
            $withValues = false;
            $res = $send(false);
        }

        return [self::check($res)->json('data.attributes.results') ?? [], $withValues];
    }

    private static function stats(array $s, bool $values): array
    {
        return [
            'recipients' => (int) ($s['recipients'] ?? 0),
            'delivered' => (int) ($s['delivered'] ?? 0),
            'opens' => (int) ($s['opens_unique'] ?? 0),
            'clicks' => (int) ($s['clicks_unique'] ?? 0),
            'unsubs' => (int) ($s['unsubscribe_uniques'] ?? 0),
            'spam' => (int) ($s['spam_complaints'] ?? 0),
            // People who ordered. Our order event fires once per item, so "conversions" counts items.
            'orders' => (int) ($s['conversion_uniques'] ?? $s['conversions'] ?? 0),
            'value_cents' => $values && isset($s['conversion_value']) ? (int) round((float) $s['conversion_value'] * 100) : null,
        ];
    }

    private static function sum(array $rows): array
    {
        $t = ['recipients' => 0, 'delivered' => 0, 'opens' => 0, 'clicks' => 0, 'unsubs' => 0, 'spam' => 0, 'orders' => 0, 'value_cents' => null];
        foreach ($rows as $r) {
            foreach ($t as $k => $v) {
                if ($k === 'value_cents') {
                    $t[$k] = $r[$k] === null ? $t[$k] : ($t[$k] ?? 0) + $r[$k];
                } else {
                    $t[$k] += $r[$k];
                }
            }
        }

        return $t;
    }

    /** id => [name, sent] for email and SMS campaigns created in the last 60 days. */
    private static function campaignNames(): array
    {
        $out = [];
        $since = CarbonImmutable::now()->subDays(60)->utc()->format('Y-m-d\TH:i:s\Z');
        foreach (['email', 'sms'] as $channel) {
            $url = config('klaviyo.url').'/campaigns/';
            $query = ['filter' => "and(equals(messages.channel,'{$channel}'),greater-or-equal(created_at,{$since}))", 'fields[campaign]' => 'name,send_time,scheduled_at'];
            for ($i = 0; $url && $i < 20; $i++) {
                $res = self::check(self::http()->get($url, $query));
                foreach ($res->json('data') ?? [] as $c) {
                    $out[$c['id']] = ['name' => mb_substr((string) ($c['attributes']['name'] ?? ''), 0, 200), 'sent' => $c['attributes']['send_time'] ?? $c['attributes']['scheduled_at'] ?? null];
                }
                $url = $res->json('links.next');
                $query = [];
            }
        }

        return $out;
    }

    private static function flowNames(): array
    {
        $out = [];
        $url = config('klaviyo.url').'/flows/';
        $query = ['fields[flow]' => 'name'];
        for ($i = 0; $url && $i < 20; $i++) {
            $res = self::check(self::http()->get($url, $query));
            foreach ($res->json('data') ?? [] as $f) {
                $out[$f['id']] = mb_substr((string) ($f['attributes']['name'] ?? ''), 0, 200);
            }
            $url = $res->json('links.next');
            $query = [];
        }

        return $out;
    }

    /** How many order events Klaviyo received in the last 30 days, and their total value. */
    private static function orderEvents(): array
    {
        $tz = config('app.timezone');
        $res = self::check(self::http()->post(config('klaviyo.url').'/metric-aggregates/', ['data' => ['type' => 'metric-aggregate', 'attributes' => [
            'metric_id' => config('klaviyo.order_metric_id'),
            'measurements' => ['count', 'sum_value'],
            'interval' => 'day',
            'timezone' => $tz,
            'filter' => [
                'greater-or-equal(datetime,'.CarbonImmutable::now($tz)->subDays(30)->format('Y-m-d\TH:i:s').')',
                'less-than(datetime,'.CarbonImmutable::now($tz)->addDay()->format('Y-m-d\TH:i:s').')',
            ],
        ]]]));
        $m = $res->json('data.attributes.data.0.measurements') ?? [];

        return [(int) array_sum($m['count'] ?? []), (float) array_sum($m['sum_value'] ?? [])];
    }

    /** The main list's size now; also recorded once a day for growth. */
    private static function listSize(): ?int
    {
        $id = (string) config('klaviyo.main_list_id');
        if ($id === '') {
            return null;
        }
        $res = self::check(self::http()->get(config('klaviyo.url').'/lists/'.rawurlencode($id).'/', ['additional-fields[list]' => 'profile_count']));
        $n = $res->json('data.attributes.profile_count');
        if (! is_numeric($n)) {
            return null;
        }
        DB::table('email_list_days')->updateOrInsert(
            ['list_id' => $id, 'day' => CarbonImmutable::now(config('app.timezone'))->format('Y-m-d')],
            ['profiles' => (int) $n, 'updated_at' => now(), 'created_at' => now()],
        );

        return (int) $n;
    }
}
