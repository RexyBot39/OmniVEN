<?php

namespace App\Support\Metabase;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OmniVEN's link to Metabase (metrics.39dg.net). Every call is made from the server with the API key, which
 * never reaches a page or a log. Lists (collections, search, tables) are cached briefly; query results are
 * only ever shown, never stored, because they can hold customer-level rows.
 */
class Metabase
{
    public static function configured(): bool
    {
        return filled(config('metabase.api_key')) && filled(config('metabase.url'));
    }

    public static function link(string $path): string
    {
        return config('metabase.url').'/'.ltrim($path, '/');
    }

    // ---- Browsing (cached) --------------------------------------------------------------------------------

    /** A collection's questions, dashboards and sub-collections, folders first. $id null is the top level. */
    public static function items(?int $id): array
    {
        return self::cached('items:'.($id ?? 'root'), function () use ($id) {
            $r = self::get('/api/collection/'.($id ?? 'root').'/items', ['models' => ['collection', 'card', 'dataset', 'metric', 'dashboard']]);
            $order = ['collection' => 0, 'dashboard' => 1, 'card' => 2, 'dataset' => 2, 'metric' => 2];

            return collect($r['data'] ?? $r)->filter(fn ($i) => is_array($i) && isset($i['id'], $i['model']) && isset($order[$i['model']]))
                ->map(fn ($i) => self::item($i))
                ->sortBy(fn ($i) => [$order[$i['model']], mb_strtolower($i['name'])])->values()->all();
        });
    }

    public static function collection(int $id): array
    {
        return self::cached("collection:$id", function () use ($id) {
            $c = self::get("/api/collection/$id");

            return ['id' => $id, 'name' => (string) ($c['name'] ?? 'Collection'),
                'path' => array_values(array_map(fn ($a) => ['id' => (int) $a['id'], 'name' => (string) $a['name']],
                    array_filter($c['effective_ancestors'] ?? [], fn ($a) => is_numeric($a['id'] ?? null))))];
        });
    }

    public static function search(string $q): array
    {
        return self::cached('search:'.md5(mb_strtolower($q)), function () use ($q) {
            $r = self::get('/api/search', ['q' => $q, 'models' => ['card', 'dataset', 'metric', 'dashboard', 'collection']]);

            return array_values(array_map(fn ($i) => self::item($i), array_slice(array_filter($r['data'] ?? [], fn ($i) => isset($i['id'], $i['model'])), 0, 60)));
        });
    }

    private static function item(array $i): array
    {
        return [
            'id' => (int) $i['id'],
            'model' => $i['model'] === 'dataset' || $i['model'] === 'metric' ? 'card' : (string) $i['model'],
            'name' => (string) ($i['name'] ?? 'Untitled'),
            'description' => mb_substr((string) ($i['description'] ?? ''), 0, 240),
            'display' => (string) ($i['display'] ?? ''),
            'where' => (string) ($i['collection']['name'] ?? ''),
        ];
    }

    /** Databases the key can query. */
    public static function databases(): array
    {
        return self::cached('databases', function () {
            $r = self::get('/api/database');

            return array_values(array_map(fn ($d) => ['id' => (int) $d['id'], 'name' => (string) $d['name'], 'engine' => (string) ($d['engine'] ?? '')],
                array_filter($r['data'] ?? $r, fn ($d) => is_array($d) && isset($d['id'], $d['name']))));
        });
    }

    /** One database's tables and their fields, for the simple query builder. */
    public static function tables(int $databaseId): array
    {
        return self::cached("tables:$databaseId", function () use ($databaseId) {
            $r = self::get("/api/database/$databaseId/metadata");
            $tables = [];
            foreach ($r['tables'] ?? [] as $t) {
                if (! isset($t['id']) || ($t['visibility_type'] ?? null) !== null) {
                    continue;
                }
                $tables[] = [
                    'id' => (int) $t['id'],
                    'name' => trim(($t['schema'] ?? '') !== '' && ($t['schema'] ?? '') !== 'public' ? $t['schema'].'.' : '').($t['display_name'] ?? $t['name']),
                    'fields' => array_values(array_map(fn ($f) => [
                        'id' => (int) $f['id'], 'name' => (string) ($f['display_name'] ?? $f['name']), 'kind' => self::kind((string) ($f['base_type'] ?? '')),
                    ], array_filter($t['fields'] ?? [], fn ($f) => isset($f['id']) && ($f['visibility_type'] ?? 'normal') !== 'sensitive' && ($f['visibility_type'] ?? 'normal') !== 'retired'))),
                ];
            }
            usort($tables, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            return $tables;
        });
    }

    // ---- Questions and dashboards ------------------------------------------------------------------------

    public static function card(int $id): array
    {
        $c = self::get("/api/card/$id");

        return [
            'id' => $id,
            'name' => (string) ($c['name'] ?? 'Question'),
            'description' => (string) ($c['description'] ?? ''),
            'display' => (string) ($c['display'] ?? 'table'),
            'database' => isset($c['dataset_query']['database']) ? (int) $c['dataset_query']['database'] : null,
            'sql' => ($c['dataset_query']['type'] ?? null) === 'native' ? (string) ($c['dataset_query']['native']['query'] ?? '') : null,
            'collection' => isset($c['collection']['id']) ? ['id' => (int) $c['collection']['id'], 'name' => (string) $c['collection']['name']] : null,
        ];
    }

    /** Run a saved question. Returns a Result (shown, never stored). */
    public static function runCard(int $id): Result
    {
        return Result::from(self::post("/api/card/$id/query", ['ignore_cache' => false]));
    }

    /** A dashboard and its cards, each already run (in parallel), in Metabase's layout order. */
    public static function dashboard(int $id): array
    {
        $d = self::get("/api/dashboard/$id");
        $cards = collect($d['dashcards'] ?? $d['ordered_cards'] ?? [])
            ->sortBy(fn ($c) => [(int) ($c['row'] ?? 0), (int) ($c['col'] ?? 0)])->values()
            ->take((int) config('metabase.dashboard_cards'));

        $runnable = $cards->filter(fn ($c) => isset($c['card_id'], $c['id']))->values();
        $responses = $runnable->isEmpty() ? [] : Http::pool(fn (Pool $pool) => $runnable->map(fn ($c) => self::request($pool->as((string) $c['id']))
            ->post(config('metabase.url')."/api/dashboard/$id/dashcard/{$c['id']}/card/{$c['card_id']}/query", ['parameters' => []]))->all());

        return [
            'id' => $id,
            'name' => (string) ($d['name'] ?? 'Dashboard'),
            'description' => (string) ($d['description'] ?? ''),
            'filters' => count($d['parameters'] ?? []),
            'cards' => $cards->map(function ($c) use ($responses) {
                $wide = (int) ($c['size_x'] ?? 4) >= 12 ? 'wide' : ((int) ($c['size_x'] ?? 4) >= 8 ? 'half' : 'third');
                if (! isset($c['card_id'])) {
                    $text = trim((string) ($c['visualization_settings']['text'] ?? ''));

                    return $text === '' ? null : ['type' => 'text', 'text' => $text, 'size' => $wide];
                }
                $res = $responses[(string) $c['id']] ?? null;
                try {
                    $result = $res instanceof Response ? Result::from(self::check($res)) : Result::failed('Couldn’t reach Metabase for this card.');
                } catch (RuntimeException $e) {
                    $result = Result::failed($e->getMessage());
                }

                return ['type' => 'card', 'id' => (int) $c['card_id'], 'name' => (string) ($c['card']['name'] ?? 'Question'),
                    'display' => (string) ($c['card']['display'] ?? 'table'), 'result' => $result, 'size' => $wide];
            })->filter()->values()->all(),
        ];
    }

    // ---- New queries ------------------------------------------------------------------------------------

    /** Run SQL (read-only statements only). Returns a Result. */
    public static function runSql(int $databaseId, string $sql): Result
    {
        self::assertReadOnly($sql);

        return Result::from(self::post('/api/dataset', ['database' => $databaseId, 'type' => 'native', 'native' => ['query' => $sql, 'template-tags' => (object) []]]));
    }

    public static function runQuery(array $datasetQuery): Result
    {
        return Result::from(self::post('/api/dataset', $datasetQuery));
    }

    /** Save a query as a new Metabase question; returns its id. */
    public static function save(string $name, array $datasetQuery, string $display, ?int $collectionId): int
    {
        if (($datasetQuery['type'] ?? '') === 'native') {
            self::assertReadOnly((string) ($datasetQuery['native']['query'] ?? ''));
        }
        $r = self::post('/api/card', ['name' => $name, 'type' => 'question', 'display' => $display, 'visualization_settings' => (object) [],
            'dataset_query' => $datasetQuery, 'collection_id' => $collectionId]);
        if (! isset($r['id'])) {
            throw new RuntimeException('Metabase didn’t save the question.');
        }
        foreach (array_keys(Cache::get('mb:keys', [])) as $k) {
            Cache::forget($k);
        }

        return (int) $r['id'];
    }

    /** Only SELECT / WITH statements, one at a time. Metabase's database login should be read-only too. */
    public static function assertReadOnly(string $sql): void
    {
        $bare = trim(preg_replace(['#/\*.*?\*/#s', '/--[^\n]*/', "/'(?:[^']|'')*'/", '/"(?:[^"]|"")*"/'], ' ', $sql));
        $bare = rtrim($bare, "; \t\n\r");
        if ($bare === '' || ! preg_match('/^\s*(select|with)\b/i', $bare)) {
            throw new RuntimeException('Only queries that read data (starting with SELECT or WITH) can run here.');
        }
        if (str_contains($bare, ';')) {
            throw new RuntimeException('Run one statement at a time (remove the extra “;”).');
        }
        if (preg_match('/\b(insert|update|delete|merge|drop|alter|create|truncate|grant|revoke|copy|call|exec|execute|vacuum|refresh)\b/i', $bare)) {
            throw new RuntimeException('That query would change data, which isn’t allowed from OmniVEN.');
        }
    }

    // ---- HTTP --------------------------------------------------------------------------------------------

    private static function request($http = null)
    {
        return ($http ?? Http::getFacadeRoot())->withHeaders(['x-api-key' => (string) config('metabase.api_key')])
            ->acceptJson()->timeout((int) config('metabase.timeout'));
    }

    private static function get(string $path, array $query = []): array
    {
        return self::send(fn () => self::request()->get(config('metabase.url').$path, self::query($query)));
    }

    private static function post(string $path, array $body): array
    {
        return self::send(fn () => self::request()->post(config('metabase.url').$path, $body));
    }

    /** Repeated keys (models=a&models=b), the way Metabase reads lists. */
    private static function query(array $q): string
    {
        $parts = [];
        foreach ($q as $k => $v) {
            foreach ((array) $v as $one) {
                $parts[] = rawurlencode($k).'='.rawurlencode((string) $one);
            }
        }

        return implode('&', $parts);
    }

    private static function send(callable $call): array
    {
        if (! self::configured()) {
            throw new RuntimeException('Metabase isn’t set up: add METABASE_API_KEY.');
        }
        try {
            return self::check($call());
        } catch (ConnectionException) {
            throw new RuntimeException('Couldn’t reach Metabase at '.parse_url(config('metabase.url'), PHP_URL_HOST).'. It may be down or slow; try again.');
        }
    }

    private static function check(Response $res): array
    {
        $body = $res->json();
        if ($res->status() === 401) {
            throw new RuntimeException('Metabase didn’t accept the API key. An admin can check METABASE_API_KEY.');
        }
        if ($res->status() === 403) {
            throw new RuntimeException('OmniVEN’s Metabase key isn’t allowed to see that. Give its group access in Metabase’s permissions.');
        }
        if ($res->status() === 404) {
            throw new RuntimeException('Metabase couldn’t find that. It may have been moved or deleted.');
        }
        if (! $res->successful()) {
            $msg = is_array($body) ? ($body['message'] ?? $body['error'] ?? null) : null;
            throw new RuntimeException('Metabase couldn’t do that'.(is_string($msg) && $msg !== '' ? ': '.mb_substr($msg, 0, 300) : ' (HTTP '.$res->status().')').'.');
        }
        if (! is_array($body)) {
            throw new RuntimeException('Metabase sent back something unreadable.');
        }

        return $body;
    }

    private static function cached(string $key, callable $fn)
    {
        $full = "mb:$key";
        $keys = Cache::get('mb:keys', []);
        if (! isset($keys[$full])) {
            Cache::forever('mb:keys', $keys + [$full => 1]);
        }

        return Cache::remember($full, now()->addMinutes((int) config('metabase.list_minutes')), $fn);
    }

    public static function kind(string $baseType): string
    {
        return match (true) {
            (bool) preg_match('#type/(Integer|BigInteger|Float|Decimal|Number)#', $baseType) => 'number',
            (bool) preg_match('#type/(Date|DateTime|Time)#', $baseType) => 'date',
            $baseType === 'type/Boolean' => 'bool',
            default => 'text',
        };
    }
}
