<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Support\Metabase\Metabase;
use App\Support\Metabase\Result;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Metabase in OmniVEN: browse collections, open saved questions and dashboards drawn as OmniVEN charts, and
 * write new queries (SQL, or a simple table/measure/group-by builder) that can be saved back to Metabase.
 * Admins and revenue viewers only. Results are shown and never stored.
 */
class MetabaseController extends Controller
{
    public const UNITS = ['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'quarter' => 'Quarter', 'year' => 'Year'];

    public function index(Request $request): View
    {
        $q = mb_substr(trim((string) $request->query('q')), 0, 80);
        $cid = $request->integer('c') ?: null;

        return $this->safely(fn () => [
            'q' => $q,
            'collection' => $cid ? Metabase::collection($cid) : null,
            'items' => $q !== '' ? Metabase::search($q) : Metabase::items($cid),
        ], 'metabase.index', ['q' => $q, 'collection' => null, 'items' => []]);
    }

    public function question(Request $request, int $card): View
    {
        $as = array_key_exists((string) $request->query('as'), Result::DISPLAYS) ? (string) $request->query('as') : null;

        return $this->safely(function () use ($card, $as) {
            $c = Metabase::card($card);

            return ['card' => $c, 'result' => Metabase::runCard($card), 'as' => $as ?? $c['display']];
        }, 'metabase.question', ['card' => null, 'result' => null, 'as' => 'auto']);
    }

    public function dashboard(int $dashboard): View
    {
        return $this->safely(fn () => ['dash' => Metabase::dashboard($dashboard)], 'metabase.dashboard', ['dash' => null]);
    }

    public function query(Request $request): View
    {
        $sql = null;
        $db = $request->integer('db') ?: null;
        if ($from = $request->integer('from')) {
            try {
                $c = Metabase::card($from);
                $sql = $c['sql'];
                $db = $db ?? $c['database'];
            } catch (RuntimeException) {
                // Opened from a question that's gone: start blank.
            }
        }

        return $this->queryPage($request, ['sql' => $sql, 'db' => $db, 'table' => $request->integer('table') ?: null,
            'mode' => $request->query('mode') === 'simple' ? 'simple' : 'sql']);
    }

    /** Run the query (POST so SQL stays out of addresses and logs) and show the answer below it. */
    public function run(Request $request): View
    {
        $input = $this->validated($request);
        $result = null;
        try {
            $result = $input['mode'] === 'sql' ? Metabase::runSql($input['db'], $input['sql']) : Metabase::runQuery($this->datasetQuery($input));
            Activity::log('metabase.query', 'Ran a '.($input['mode'] === 'sql' ? 'SQL' : 'table').' query in Metabase');
        } catch (RuntimeException $e) {
            $result = Result::failed($e->getMessage());
        }

        return $this->queryPage($request, $input + ['result' => $result]);
    }

    public function save(Request $request): View|RedirectResponse
    {
        $input = $this->validated($request);
        $name = mb_substr(trim((string) $request->input('name')), 0, 200);
        $collection = $request->integer('collection_id') ?: null;
        try {
            if ($name === '') {
                throw new RuntimeException('Give the question a name to save it.');
            }
            $query = $input['mode'] === 'sql'
                ? ['database' => $input['db'], 'type' => 'native', 'native' => ['query' => $input['sql'], 'template-tags' => (object) []]]
                : $this->datasetQuery($input);
            $display = $input['display'] === 'auto' ? 'table' : $input['display'];
            $id = Metabase::save($name, $query, $display, $collection);
        } catch (RuntimeException $e) {
            // Keep the query on screen so nothing typed is lost.
            return $this->queryPage($request, $input + ['result' => Result::failed($e->getMessage())]);
        }
        Activity::log('metabase.saved', "Saved a Metabase question: “{$name}”");

        return redirect()->route('metabase.question', $id)->with('status', 'Saved to Metabase as “'.$name.'”.');
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'mode' => ['required', Rule::in(['sql', 'simple'])],
            'db' => ['required', 'integer'],
            'sql' => ['required_if:mode,sql', 'nullable', 'string', 'max:20000'],
            'table' => ['required_if:mode,simple', 'nullable', 'integer'],
            'measure' => ['nullable', 'string', 'max:40'],
            'group' => ['nullable', 'integer'],
            'unit' => ['nullable', Rule::in(array_keys(self::UNITS))],
            'date_field' => ['nullable', 'integer'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'display' => ['nullable', Rule::in(array_keys(Result::DISPLAYS))],
        ]);

        return ['mode' => $v['mode'], 'db' => (int) $v['db'], 'sql' => $v['sql'] ?? null, 'table' => $v['table'] ?? null, 'measure' => $v['measure'] ?? 'count',
            'group' => $v['group'] ?? null, 'unit' => $v['unit'] ?? 'month', 'date_field' => $v['date_field'] ?? null, 'days' => $v['days'] ?? null,
            'limit' => $v['limit'] ?? 100, 'display' => $v['display'] ?? 'auto'];
    }

    /** The simple builder's choices as a Metabase (MBQL) query. Fields must belong to the chosen table. */
    private function datasetQuery(array $in): array
    {
        $table = collect(Metabase::tables($in['db']))->firstWhere('id', $in['table']) ?? throw new RuntimeException('Pick a table.');
        $fields = collect($table['fields'])->keyBy('id');
        $field = fn (?int $id) => $id && $fields->has($id) ? ['field', $id, null] : null;

        [$agg, $fid] = array_pad(explode(':', (string) $in['measure'], 2), 2, null);
        $aggregation = match ($agg) {
            'sum', 'avg', 'distinct', 'max', 'min' => [[$agg, $field((int) $fid) ?? throw new RuntimeException('Pick the column to '.($agg === 'distinct' ? 'count' : 'add up').'.')]],
            default => [['count']],
        };
        $q = ['source-table' => $table['id'], 'aggregation' => $aggregation, 'limit' => $in['limit']];
        if ($in['group'] && $fields->has($in['group'])) {
            $isDate = $fields[$in['group']]['kind'] === 'date';
            $q['breakout'] = [['field', $in['group'], $isDate ? ['temporal-unit' => $in['unit']] : null]];
            $q['order-by'] = $isDate ? [['asc', $q['breakout'][0]]] : [['desc', ['aggregation', 0]]];
        }
        if ($in['days'] && ($d = $field($in['date_field']))) {
            $q['filter'] = ['time-interval', $d, -$in['days'], 'day'];
        }

        return ['database' => $in['db'], 'type' => 'query', 'query' => $q];
    }

    private function queryPage(Request $request, array $state): View
    {
        $base = ['databases' => [], 'tables' => [], 'error' => null, 'result' => null, 'collections' => []];
        try {
            $dbs = Metabase::databases();
            $db = $state['db'] ?? ($dbs[0]['id'] ?? null);
            $state['db'] = $db;
            $base['databases'] = $dbs;
            if (($state['mode'] ?? 'sql') === 'simple' && $db) {
                $base['tables'] = Metabase::tables($db);
            }
            $base['collections'] = array_values(array_filter(Metabase::items(null), fn ($i) => $i['model'] === 'collection'));
        } catch (RuntimeException $e) {
            $base['error'] = $request->user()->can('admin') ? $e->getMessage() : 'Can’t reach Metabase right now. Try again in a minute.';
        }

        return view('metabase.query', $state + $base + ['units' => self::UNITS]);
    }

    /** Render a page; if Metabase fails, the page says so (admins see why). */
    private function safely(callable $load, string $view, array $empty): View
    {
        if (! Metabase::configured()) {
            return view($view, $empty + ['error' => null]);
        }
        try {
            return view($view, $load() + ['error' => null]);
        } catch (RuntimeException $e) {
            return view($view, $empty + ['error' => request()->user()->can('admin') ? $e->getMessage() : 'Can’t reach Metabase right now. Try again in a minute.']);
        }
    }
}
