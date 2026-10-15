<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One 36-hour-rule decision: boosted (with spend) or explicitly skipped. */
class BoostLog extends Model
{
    public const BRANDS = ['39dg' => '39DG', 'ocusafe' => 'Ocusafe', 'neurolux' => 'Neurolux'];

    public const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    protected $fillable = ['calendar_week_id', 'day', 'brand_slug', 'format', 'beat_median', 'amount', 'user_id'];

    protected function casts(): array
    {
        return ['beat_median' => 'boolean', 'amount' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spend(): int
    {
        return $this->beat_median ? $this->amount : 0;
    }

    public function brandLabel(): string
    {
        return self::BRANDS[$this->brand_slug] ?? $this->brand_slug;
    }
}
