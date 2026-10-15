<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Brand;
use App\Models\LinkCheck;
use App\Models\Routine;
use App\Models\Tool;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Visits every link OmniVEN shows and records whether it still works.
 * Sites that refuse automated visits (Instagram, LinkedIn…) are "couldn't check", not broken.
 */
class LinkChecker
{
    /** Status codes that mean "the site wouldn't let a robot in", not "the page is gone". */
    private const BLOCKED = [401, 403, 405, 406, 429, 451, 999];

    /** @return array{checked:int, ok:int, failing:int, blocked:int, newly_broken:int}|null  null when a check is already running */
    public static function run(): ?array
    {
        $lock = Cache::lock('omniven:link-check', 900);
        if (! $lock->get()) {
            return null;
        }
        try {
            return self::check();
        } finally {
            $lock->release();
        }
    }

    /** Every [type, id, url] OmniVEN links to, skipping its own pages. */
    public static function targets(): Collection
    {
        $own = parse_url((string) config('app.url'), PHP_URL_HOST);
        $t = collect();
        foreach (Tool::whereNotNull('url')->get() as $x) {
            $t->push(['tool', $x->id, $x->url]);
        }
        foreach (Account::whereNotNull('url')->get() as $x) {
            $t->push(['account', $x->id, $x->url]);
        }
        foreach (Brand::all() as $b) {
            if ($b->site) {
                $t->push(['brand', $b->id, $b->site]);
            }
            foreach ($b->socials ?? [] as $s) {
                if (! empty($s['url'])) {
                    $t->push(['brand', $b->id, $s['url']]);
                }
            }
        }
        foreach (Routine::whereNotNull('link')->get() as $x) {
            $t->push(['routine', $x->id, $x->link]);
        }

        return $t->filter(fn ($r) => preg_match('#^https?://#i', $r[2]) && parse_url($r[2], PHP_URL_HOST) !== $own)
            ->unique(fn ($r) => $r[0].':'.$r[1].' '.$r[2])->values();
    }

    private static function check(): array
    {
        $targets = self::targets();
        $results = self::fetch($targets->pluck(2)->unique()->values()->all());
        $now = now();
        $newlyBroken = collect();
        $keep = [];

        foreach ($targets as [$type, $id, $url]) {
            [$status, $code, $error] = $results[$url];
            $row = LinkCheck::firstOrNew(['item_type' => $type, 'item_id' => $id, 'url' => $url]);
            $wasBroken = $row->exists && $row->isBroken();

            if ($status === 'failing') {
                $row->fail_count = $row->fail_count + 1;
                $row->first_failed_at ??= $now;
            } else {
                $row->fail_count = 0;
                $row->first_failed_at = null;
                $row->notified_at = null;
            }
            $row->fill(['status' => $status, 'http_code' => $code, 'error' => $error, 'checked_at' => $now])->save();
            $keep[] = $row->id;

            if (! $wasBroken && $row->isBroken() && ! $row->notified_at) {
                $newlyBroken->push($row);
            }
        }

        // Links that were removed or changed since the last run.
        LinkCheck::whereNotIn('id', $keep)->delete();

        if ($newlyBroken->isNotEmpty()) {
            self::notify($newlyBroken);
            LinkCheck::whereIn('id', $newlyBroken->pluck('id'))->update(['notified_at' => $now]);
        }
        Cache::forever('omniven:link-check:last', $now->toIso8601String());

        $all = LinkCheck::all();

        return [
            'checked' => $all->count(),
            'ok' => $all->where('status', 'ok')->count(),
            'failing' => $all->filter->isBroken()->count(),
            'blocked' => $all->where('status', 'blocked')->count(),
            'newly_broken' => $newlyBroken->count(),
        ];
    }

    /** @return array<string, array{0:string,1:?int,2:?string}> url => [status, http code, error] */
    private static function fetch(array $urls): array
    {
        $out = [];
        foreach (array_chunk($urls, 10) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($u) => $pool->as($u)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; OmniVEN link check; +'.config('app.url').')', 'Accept' => 'text/html,*/*'])
                    ->connectTimeout(5)->timeout(10)
                    ->withOptions(['allow_redirects' => ['max' => 6]])
                    ->get($u),
                $chunk,
            ));
            foreach ($chunk as $u) {
                $out[$u] = self::classify($responses[$u] ?? null);
            }
        }

        return $out;
    }

    private static function classify(mixed $r): array
    {
        if ($r instanceof Response) {
            $code = $r->status();
            if ($code < 400) {
                return ['ok', $code, null];
            }

            return [in_array($code, self::BLOCKED, true) ? 'blocked' : 'failing', $code, null];
        }
        $msg = $r instanceof Throwable ? $r->getMessage() : 'No response';

        return ['failing', null, self::explain($msg)];
    }

    private static function explain(string $msg): string
    {
        return match (true) {
            Str::contains($msg, ['Could not resolve', 'getaddrinfo', 'Name or service']) => 'Website not found',
            Str::contains($msg, ['timed out', 'Timeout']) => 'Timed out',
            Str::contains($msg, ['SSL', 'certificate']) => 'Security certificate problem',
            Str::contains($msg, ['refused']) => 'Connection refused',
            default => Str::limit($msg, 120),
        };
    }

    /** One short Slack note on nights when links newly break. */
    private static function notify(Collection $rows): void
    {
        $names = $rows->map(fn ($r) => AccessNotifier::clean(LinkCheck::itemName($r->item())))->unique()->values();
        $text = ':warning: '.$rows->count().' '.Str::plural('link', $rows->count()).' stopped working: '
            .$names->take(8)->implode(', ').($names->count() > 8 ? ' and more' : '')
            .'. See <'.route('dashboard').'#access|Broken links on the OmniVEN Access tab>.';
        if (! AccessNotifier::postToChannel($text)) {
            Log::info('Broken-link Slack note not sent (no channel set or Slack unavailable)', ['count' => $rows->count()]);
        }
    }
}
