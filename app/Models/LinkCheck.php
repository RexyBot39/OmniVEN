<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Latest check of one link. It only counts as broken after failing two nights in a row. */
class LinkCheck extends Model
{
    public const BROKEN_AFTER = 2;

    public const TYPES = ['tool' => Tool::class, 'account' => Account::class, 'brand' => Brand::class, 'routine' => Routine::class];

    protected $fillable = ['item_type', 'item_id', 'url', 'status', 'http_code', 'error', 'fail_count', 'first_failed_at', 'checked_at', 'notified_at'];

    protected function casts(): array
    {
        return ['first_failed_at' => 'datetime', 'checked_at' => 'datetime', 'notified_at' => 'datetime'];
    }

    public function scopeBroken(Builder $q): Builder
    {
        return $q->where('status', 'failing')->where('fail_count', '>=', self::BROKEN_AFTER);
    }

    public function isBroken(): bool
    {
        return $this->status === 'failing' && $this->fail_count >= self::BROKEN_AFTER;
    }

    public function key(): string
    {
        return $this->item_type.':'.$this->item_id;
    }

    public function item(): ?Model
    {
        $class = self::TYPES[$this->item_type] ?? null;

        return $class ? $class::find($this->item_id) : null;
    }

    public static function itemName(?Model $item): string
    {
        return match (true) {
            $item instanceof Account => $item->title(),
            $item instanceof Routine => $item->title,
            $item instanceof Tool, $item instanceof Brand => $item->name,
            default => 'A removed item',
        };
    }

    /** Where an admin fixes this link. */
    public static function fixUrl(?Model $item): ?string
    {
        return match (true) {
            $item instanceof Tool => route('tools.edit', [$item, 'return' => 'access']),
            $item instanceof Account => route('accounts.edit', [$item, 'return' => 'access']),
            $item instanceof Brand => route('brands.edit', $item),
            $item instanceof Routine => route('routines.edit', $item),
            default => null,
        };
    }

    /** "type:id" keys with a broken link, for the red marks (admins only). */
    public static function brokenKeys(): \Illuminate\Support\Collection
    {
        return static::broken()->get()->map->key()->unique()->flip();
    }

    /** For the admin panel: broken rows with their items, how many couldn't be checked, and when it last ran. */
    public static function report(): array
    {
        $broken = static::broken()->orderBy('first_failed_at')->get()
            ->map(fn ($r) => ['row' => $r, 'item' => $r->item()]);
        $last = \Illuminate\Support\Facades\Cache::get('omniven:link-check:last');

        return [
            'broken' => $broken,
            'blocked' => static::where('status', 'blocked')->count(),
            'total' => static::count(),
            'last' => $last ? \Illuminate\Support\Carbon::parse($last) : static::max('checked_at'),
        ];
    }

    public function shortError(): string
    {
        if ($this->http_code) {
            return match ($this->http_code) {
                404 => 'Page not found (404)',
                410 => 'Page removed (410)',
                default => 'Error '.$this->http_code,
            };
        }

        return $this->error ?: 'Could not connect';
    }
}
