<?php

namespace App\Support\Affiliate;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Keeps affiliate_months in step with CJ and Awin: this month and last month on every refresh
 * (commissions move from pending to confirmed for weeks), plus one more month of history per
 * refresh. Read-only; tokens stay on the server and never appear in errors or logs.
 */
class Affiliates
{
    public const NETWORKS = ['cj' => 'CJ', 'awin' => 'Awin'];

    public static function configured(?string $network = null): bool
    {
        $ok = fn ($n) => filled(config("affiliates.$n.token")) && filled(config("affiliates.$n.advertiser_id"));

        return $network ? $ok($network) : $ok('cj') || $ok('awin');
    }

    public static function networks(): array
    {
        return array_values(array_filter(array_keys(self::NETWORKS), fn ($n) => self::configured($n)));
    }

    /** Re-read one month for one network and replace its rows. */
    public static function syncMonth(string $network, CarbonImmutable $month): void
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth()->min(CarbonImmutable::now(config('app.timezone')));
        $rows = $network === 'cj' ? self::cj($from, $to) : self::awin($from, $to);

        $now = now();
        DB::transaction(function () use ($network, $from, $rows, $now) {
            DB::table('affiliate_months')->where('network', $network)->where('month', $from->format('Y-m-d'))->delete();
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                DB::table('affiliate_months')->insert(array_map(fn ($r) => $r + [
                    'network' => $network, 'month' => $from->format('Y-m-d'), 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
        });
    }

    /** CJ Commission Detail API (GraphQL), summed per publisher. Pending = new or extended. */
    private static function cj(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        $since = null;
        $cid = (string) config('affiliates.cj.advertiser_id');
        for ($page = 0; $page < 200; $page++) {
            $args = 'forAdvertisers: '.json_encode([$cid]).', sincePostingDate: '.json_encode($from->utc()->format('Y-m-d\TH:i:s\Z'))
                .', beforePostingDate: '.json_encode($to->addDay()->startOfDay()->utc()->format('Y-m-d\TH:i:s\Z'))
                .($since ? ', sinceCommissionId: '.json_encode($since) : '');
            $query = '{ advertiserCommissions('.$args.') { count payloadComplete maxCommissionId records { commissionId actionStatus publisherId publisherName saleAmountAdvCurrency advCommissionAmountAdvCurrency } } }';

            $res = Http::withToken((string) config('affiliates.cj.token'))->acceptJson()->timeout(config('affiliates.timeout'))
                ->post(config('affiliates.cj.url'), ['query' => $query]);
            if (! $res->successful()) {
                throw new RuntimeException(self::explain('CJ', $res->status()));
            }
            if ($res->json('errors')) {
                throw new RuntimeException('CJ rejected the report: '.mb_substr((string) $res->json('errors.0.message'), 0, 200));
            }
            $data = $res->json('data.advertiserCommissions') ?? [];
            foreach ($data['records'] ?? [] as $r) {
                $id = (string) ($r['publisherId'] ?? 'unknown');
                $out[$id] ??= ['publisher_id' => $id, 'publisher_name' => mb_substr((string) ($r['publisherName'] ?? ''), 0, 200) ?: null, 'orders' => 0, 'sale_cents' => 0, 'commission_cents' => 0, 'pending_cents' => 0];
                $sale = (int) round((float) ($r['saleAmountAdvCurrency'] ?? 0) * 100);
                $comm = (int) round((float) ($r['advCommissionAmountAdvCurrency'] ?? 0) * 100);
                $out[$id]['orders'] += $sale > 0 ? 1 : ($sale < 0 ? -1 : 0);
                $out[$id]['sale_cents'] += $sale;
                $out[$id]['commission_cents'] += $comm;
                if (in_array(strtolower((string) ($r['actionStatus'] ?? '')), ['new', 'extended'], true)) {
                    $out[$id]['pending_cents'] += $comm;
                }
            }
            if (($data['payloadComplete'] ?? true) || empty($data['maxCommissionId'])) {
                break;
            }
            $since = (string) $data['maxCommissionId'];
        }

        return $out;
    }

    /** Awin advertiser publisher-performance report: one row per publisher for the range. */
    private static function awin(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $id = rawurlencode((string) config('affiliates.awin.advertiser_id'));
        $res = Http::withToken((string) config('affiliates.awin.token'))->acceptJson()->timeout(config('affiliates.timeout'))
            ->get(config('affiliates.awin.url')."/advertisers/{$id}/reports/publisher", [
                'startDate' => $from->format('Y-m-d'), 'endDate' => $to->format('Y-m-d'),
                'region' => config('affiliates.awin.region'), 'timezone' => 'UTC',
            ]);
        if (! $res->successful() || ! is_array($res->json())) {
            throw new RuntimeException(self::explain('Awin', $res->status()));
        }
        $cents = fn ($v) => (int) round((float) ($v ?? 0) * 100);
        $out = [];
        foreach ($res->json() as $r) {
            if (! is_array($r)) {
                continue;
            }
            $pid = (string) ($r['publisherId'] ?? 'unknown');
            $out[$pid] = [
                'publisher_id' => $pid,
                'publisher_name' => mb_substr((string) ($r['publisherName'] ?? ''), 0, 200) ?: null,
                'orders' => (int) ($r['pendingNo'] ?? 0) + (int) ($r['confirmedNo'] ?? 0),
                'sale_cents' => $cents($r['pendingValue'] ?? 0) + $cents($r['confirmedValue'] ?? 0),
                'commission_cents' => $cents($r['pendingComm'] ?? 0) + $cents($r['confirmedComm'] ?? 0),
                'pending_cents' => $cents($r['pendingComm'] ?? 0),
            ];
        }

        return array_filter($out, fn ($r) => $r['orders'] || $r['sale_cents'] || $r['commission_cents']);
    }

    private static function explain(string $network, int $status): string
    {
        $setting = $network === 'CJ' ? 'CJ_API_TOKEN' : 'AWIN_API_TOKEN';
        $id = $network === 'CJ' ? 'CJ_ADVERTISER_ID' : 'AWIN_ADVERTISER_ID';

        return match (true) {
            $status === 401 => "{$network} refused the API token (HTTP 401). Check {$setting}.",
            $status === 403 => "{$network} says the token can’t read this account (HTTP 403). Check {$id} and that the token belongs to a user on it.",
            $status === 404 => "{$network} didn’t find the report (HTTP 404). Check {$id}.",
            $status === 429 => "{$network} is rate-limiting us (HTTP 429). It will try again later.",
            default => "{$network} answered HTTP {$status}.",
        };
    }

    /** One refresh for every configured network: this month, last month, and one more month of history. */
    public static function refresh(bool $fullHistory = false): bool
    {
        $ok = true;
        $this_ = CarbonImmutable::now(config('app.timezone'))->startOfMonth();
        $oldest = $this_->subMonths(config('affiliates.history_months') - 1);
        foreach (self::networks() as $n) {
            try {
                self::syncMonth($n, $this_);
                self::syncMonth($n, $this_->subMonth());
                $reached = Cache::get("affiliates:$n:history");
                $reached = is_string($reached) ? CarbonImmutable::parse($reached, config('app.timezone')) : $this_->subMonth();
                for ($i = 0; $reached->gt($oldest) && ($fullHistory || $i < config('affiliates.history_months_per_refresh')); $i++) {
                    $month = $reached->subMonth();
                    try {
                        self::syncMonth($n, $month);
                    } catch (RuntimeException $e) {
                        // An old month that keeps failing is past what the network will report: history ends there.
                        $fails = (int) Cache::get("affiliates:$n:history-fails") + 1;
                        Cache::forever("affiliates:$n:history-fails", $fails);
                        if ($fails >= 3) {
                            Cache::forever("affiliates:$n:history", $oldest->format('Y-m-d'));
                            Cache::forget("affiliates:$n:history-fails");
                        }
                        Log::info('Affiliate history stopped', ['network' => $n, 'month' => $month->format('Y-m'), 'error' => $e->getMessage()]);
                        break;
                    }
                    Cache::forget("affiliates:$n:history-fails");
                    $reached = $month;
                    Cache::forever("affiliates:$n:history", $reached->format('Y-m-d'));
                }
                Cache::forever("affiliates:$n:last", now()->timestamp);
                Cache::forget("affiliates:$n:error");
            } catch (Throwable $e) {
                $ok = false;
                $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Reading '.self::NETWORKS[$n].' failed ('.class_basename($e).').';
                Cache::forever("affiliates:$n:error", $msg);
                Log::warning('Affiliate sync failed', ['network' => $n, 'error' => $msg]);
            }
        }

        return $ok;
    }

    public static function refreshIfStale(): void
    {
        $stale = collect(self::networks())->contains(function ($n) {
            $last = Cache::get("affiliates:$n:last");

            return ! is_numeric($last) || now()->timestamp - (int) $last >= config('affiliates.refresh_minutes') * 60;
        });
        if (! $stale || ! Cache::add('affiliates:refreshing', 1, 600)) {
            return;
        }
        dispatch(function () {
            try {
                self::refresh();
            } finally {
                Cache::forget('affiliates:refreshing');
            }
        })->afterResponse();
    }

    /** Per network: whether it's set up, when it last updated, and the last error. */
    public static function status(): array
    {
        return collect(self::NETWORKS)->map(fn ($label, $n) => [
            'label' => $label,
            'configured' => self::configured($n),
            'last' => is_numeric($t = Cache::get("affiliates:$n:last")) ? CarbonImmutable::createFromTimestamp((int) $t, config('app.timezone')) : null,
            'error' => Cache::get("affiliates:$n:error"),
        ])->all();
    }

    public static function forget(): void
    {
        foreach (array_keys(self::NETWORKS) as $n) {
            foreach (['last', 'error', 'history', 'history-fails'] as $k) {
                Cache::forget("affiliates:$n:$k");
            }
        }
        Cache::forget('affiliates:refreshing');
    }
}
