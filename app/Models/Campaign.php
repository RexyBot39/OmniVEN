<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A campaign: a named push with start and end dates. Its links are the campaign links tagged with
 * the same utm_campaign value; its tasks are attached directly or through a launch-template run.
 */
class Campaign extends Model
{
    protected $fillable = ['name', 'tag', 'brand_id', 'owner_id', 'starts_on', 'ends_on', 'goal', 'notes'];

    protected function startsOn(): Attribute
    {
        return self::plainDate();
    }

    protected function endsOn(): Attribute
    {
        return self::plainDate();
    }

    private static function plainDate(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v === null || $v === '' ? null : CarbonImmutable::parse(substr((string) $v, 0, 10), config('app.timezone')),
            set: fn ($v) => $v === null || $v === '' ? null : CarbonImmutable::parse($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v)->format('Y-m-d'),
        );
    }

    /** The utm_campaign value, cleaned the same way the link builder cleans it. */
    public static function tagFor(string $name): string
    {
        return mb_substr(UtmLink::clean($name), 0, 150);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TemplateRun::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(UtmLink::class, 'campaign', 'tag');
    }

    /** Overlapping [from, to] (inclusive dates). */
    public function scopeDuring(Builder $q, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $q->where('starts_on', '<=', $to->format('Y-m-d'))->where('ends_on', '>=', $from->format('Y-m-d'));
    }

    public function state(): string
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        return match (true) {
            $this->starts_on->gt($today) => 'upcoming',
            $this->ends_on->lt($today) => 'ended',
            default => 'running',
        };
    }

    public function dateLabel(): string
    {
        $a = $this->starts_on;
        $b = $this->ends_on;

        return $a->isSameDay($b) ? $a->format('M j') : $a->format('M j').' – '.($a->isSameMonth($b) ? $b->format('j') : $b->format('M j'));
    }
}
