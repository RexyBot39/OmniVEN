<?php

namespace App\Models;

use App\Models\Concerns\HasBrandScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Routine extends Model
{
    use HasBrandScope;

    protected $fillable = ['title', 'cadence', 'description', 'link', 'all_brands', 'sort'];

    protected function casts(): array
    {
        return ['all_brands' => 'boolean', 'sort' => 'integer'];
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(RoutineCompletion::class);
    }

    public function latestCompletion(): HasOne
    {
        return $this->hasOne(RoutineCompletion::class)->latestOfMany('completed_at');
    }

    /** Identifies the current day, ISO week or month, in the app timezone. */
    public static function periodKey(string $cadence, ?CarbonInterface $at = null): string
    {
        $at = ($at ?? now())->copy()->setTimezone(config('app.timezone'));

        return match ($cadence) {
            'monthly' => $at->format('Y-m'),
            'weekly' => $at->format('o-\WW'),
            default => $at->format('Y-m-d'),
        };
    }

    public function currentPeriodKey(): string
    {
        return static::periodKey($this->cadence);
    }

    public function isDone(): bool
    {
        $c = $this->latestCompletion;

        return $c !== null && $c->period_key === $this->currentPeriodKey();
    }
}
