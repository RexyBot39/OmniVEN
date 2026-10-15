<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** One Monday-to-Sunday week of the action calendar. */
class CalendarWeek extends Model
{
    public const DAY_ORDER = ['Setup', 'Review', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    protected $fillable = ['starts_on', 'number', 'range_label', 'phase_label', 'phase_text', 'season_text', 'notes', 'notes_by', 'notes_at'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'notes_at' => 'datetime', 'number' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'starts_on';
    }

    /** /calendar/2026-10-12 finds the week by its Monday. */
    public function resolveRouteBinding($value, $field = null)
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? static::whereDate('starts_on', $value)->firstOrFail() : abort(404);
    }

    public function getRouteKey(): string
    {
        return $this->starts_on->toDateString();
    }

    public function oneOffItems(): HasMany
    {
        return $this->hasMany(CalendarItem::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(CalendarCheck::class);
    }

    public function boosts(): HasMany
    {
        return $this->hasMany(BoostLog::class)->orderBy('id');
    }

    public function notesAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notes_by');
    }

    /** This week's one-off items plus the every-week runbook, in day order. */
    public function items(): Collection
    {
        return CalendarItem::where(fn (Builder $q) => $q->whereNull('calendar_week_id')->orWhere('calendar_week_id', $this->id))
            ->get()
            ->sortBy(fn ($i) => [array_search($i->day, self::DAY_ORDER, true), $i->calendar_week_id ? 0 : 1, $i->sort])
            ->values();
    }

    /** The week containing today (Eastern), or the nearest one before or after the plan. */
    public static function current(): ?self
    {
        $today = now()->setTimezone(config('app.timezone'))->toDateString();

        return static::whereDate('starts_on', '<=', $today)->orderByDesc('starts_on')->first()
            ?? static::orderBy('starts_on')->first();
    }

    public function isCurrent(): bool
    {
        return static::current()?->is($this) ?? false;
    }
}
