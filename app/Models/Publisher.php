<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A publisher we're pitching (blog, review site, affiliate, creator, press) and where the pitch stands. */
class Publisher extends Model
{
    protected $fillable = ['name', 'site', 'type', 'brand_id', 'owner_id', 'stage', 'contact', 'network', 'network_id', 'last_contacted_on', 'follow_up_on', 'result_url', 'notes', 'live_at'];

    protected function casts(): array
    {
        return ['live_at' => 'datetime'];
    }

    /** Dates are stored as plain Y-m-d so "due by today" compares the same on every database. */
    protected function followUpOn(): Attribute
    {
        return self::plainDate();
    }

    protected function lastContactedOn(): Attribute
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

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OutreachEvent::class)->latest('id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->stage, config('outreach.closed'), true);
    }

    public function followUpDue(): bool
    {
        return $this->isOpen() && $this->follow_up_on && $this->follow_up_on->lte(CarbonImmutable::now(config('app.timezone'))->startOfDay());
    }

    public function stageLabel(): string
    {
        return config('outreach.stages.'.$this->stage, $this->stage);
    }

    public function host(): ?string
    {
        return $this->site ? preg_replace('/^www\./', '', (string) parse_url($this->site, PHP_URL_HOST)) : null;
    }
}
