<?php

namespace App\Support\Metabase;

use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * One query's answer from Metabase: columns, rows and how best to draw it. Lives only for the page that shows
 * it; nothing here is cached or saved.
 */
class Result
{
    public const DISPLAYS = ['auto' => 'Best fit', 'table' => 'Table', 'line' => 'Line', 'bar' => 'Bars', 'scalar' => 'Number'];

    /** @param array<int, array{name: string, kind: string}> $cols */
    public function __construct(public array $cols = [], public array $rows = [], public int $count = 0, public ?string $error = null)
    {
    }

    public static function failed(string $error): self
    {
        return new self(error: $error);
    }

    public static function from(array $body): self
    {
        if (($body['status'] ?? 'completed') === 'failed' || isset($body['error']) && ! isset($body['data']['rows'])) {
            $err = $body['error'] ?? 'The query failed.';

            return self::failed(mb_substr(is_string($err) ? $err : json_encode($err), 0, 600));
        }
        $data = $body['data'] ?? null;
        if (! is_array($data) || ! isset($data['cols'], $data['rows'])) {
            throw new RuntimeException('Metabase sent back a result OmniVEN can’t read.');
        }
        $cols = array_map(fn ($c) => ['name' => (string) ($c['display_name'] ?? $c['name'] ?? ''), 'kind' => Metabase::kind((string) ($c['base_type'] ?? $c['effective_type'] ?? ''))], $data['cols']);

        return new self($cols, array_values($data['rows']), (int) ($body['row_count'] ?? count($data['rows'])));
    }

    public function ok(): bool
    {
        return $this->error === null;
    }

    /** Table, line, bars or one big number, from what was asked for and what came back. */
    public function display(string $wanted = 'auto'): string
    {
        $wanted = match ($wanted) {
            'line', 'area', 'combo' => 'line',
            'bar', 'row', 'pie', 'funnel', 'waterfall' => 'bar',
            'scalar', 'smartscalar', 'progress', 'gauge' => 'scalar',
            'table', 'pivot', 'object' => 'table',
            default => 'auto',
        };
        $numbers = $this->numberColumns();
        $first = $this->cols[0]['kind'] ?? null;
        $fits = [
            'scalar' => $this->count >= 1 && $numbers !== [],
            'line' => count($this->rows) > 1 && count($this->cols) >= 2 && $numbers !== [] && $numbers !== [0],
            'bar' => count($this->rows) >= 1 && count($this->cols) >= 2 && $numbers !== [] && $numbers !== [0],
        ];
        if ($wanted !== 'auto') {
            return $wanted === 'table' || ($fits[$wanted] ?? false) ? $wanted : 'table';
        }

        return match (true) {
            count($this->rows) === 1 && count($this->cols) === 1 && $numbers === [0] => 'scalar',
            $first === 'date' && $fits['line'] => 'line',
            $first === 'text' && $fits['bar'] && count($this->rows) <= 40 => 'bar',
            default => 'table',
        };
    }

    /** Indexes of the numeric columns. */
    public function numberColumns(): array
    {
        return array_keys(array_filter($this->cols, fn ($c) => $c['kind'] === 'number'));
    }

    /** The column a chart plots: the first number that isn't the label column. */
    public function measure(): ?int
    {
        foreach ($this->numberColumns() as $i) {
            if ($i > 0) {
                return $i;
            }
        }

        return null;
    }

    /** Points for the line chart partial (labels from the first column, values from the measure). */
    public function points(): array
    {
        $m = $this->measure();
        // Monthly rows (every date on the 1st) get "Oct ’26" ticks instead of "Oct 1".
        $monthly = ($this->cols[0]['kind'] ?? '') === 'date' && collect($this->rows)->every(fn ($r) => is_string($r[0] ?? null) && preg_match('/^\d{4}-\d{2}-01/', $r[0]));
        $short = fn ($v) => $monthly ? CarbonImmutable::parse($v)->format('M ’y') : $this->format($v, 0, true);

        return array_map(fn ($r) => ['label' => $this->format($r[0] ?? null, 0), 'short' => $short($r[0] ?? null),
            'v' => is_numeric($r[$m] ?? null) ? (float) $r[$m] : null, 'c' => null], array_slice($this->rows, 0, 400));
    }

    /** Whether the measure is whole numbers, so the chart formats it without decimals. */
    public function wholeNumbers(): bool
    {
        $m = $this->measure() ?? 0;

        return collect($this->rows)->every(fn ($r) => ! is_numeric($r[$m] ?? null) || floor((float) $r[$m]) == (float) $r[$m]);
    }

    public function format($v, int $col, bool $short = false): string
    {
        if ($v === null) {
            return '—';
        }
        $kind = $this->cols[$col]['kind'] ?? 'text';
        if ($kind === 'number' && is_numeric($v)) {
            return floor((float) $v) == (float) $v && abs((float) $v) < 1e15 ? number_format((float) $v) : number_format((float) $v, 2);
        }
        if ($kind === 'date' && is_string($v)) {
            try {
                $d = CarbonImmutable::parse($v);
                $timeless = $d->format('H:i:s') === '00:00:00';

                return $short ? $d->format('M j') : $d->format($timeless ? 'M j, Y' : 'M j, Y g:i A');
            } catch (Throwable) {
                return $v;
            }
        }
        if (is_bool($v)) {
            return $v ? 'Yes' : 'No';
        }

        return is_scalar($v) ? (string) $v : json_encode($v);
    }
}
