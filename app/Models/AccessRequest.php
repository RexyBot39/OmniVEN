<?php

namespace App\Models;

use App\Support\Launchpad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone asking for access to a tool or brand account. The reason is seen only by them and admins. */
class AccessRequest extends Model
{
    public const TYPES = ['tool', 'account'];

    protected $fillable = ['user_id', 'item_type', 'item_id', 'reason', 'status', 'handled_by', 'handled_at', 'decision_note'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** The tool or account asked for (null if it has since been deleted). */
    public function item(): ?Model
    {
        return Launchpad::find($this->item_type, $this->item_id);
    }

    public function itemName(?Model $item = null): string
    {
        $item ??= $this->item();

        return match (true) {
            $item instanceof Tool => $item->name,
            $item instanceof Account => $item->title(),
            default => 'a removed item',
        };
    }

    /** This person's most recent request per item, keyed "type:id", for the Request access buttons. */
    public static function latestFor(User $user): \Illuminate\Support\Collection
    {
        return static::where('user_id', $user->id)->orderByDesc('id')->get()->unique(fn ($r) => $r->key())->keyBy(fn ($r) => $r->key());
    }

    public function key(): string
    {
        return $this->item_type.':'.$this->item_id;
    }
}
