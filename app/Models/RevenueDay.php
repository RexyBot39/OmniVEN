<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One brand's sessions, orders and revenue from one channel on one day (from GA4). */
class RevenueDay extends Model
{
    protected $fillable = ['brand_id', 'day', 'channel', 'source', 'sessions', 'orders', 'revenue_cents'];

    protected function casts(): array
    {
        return ['day' => 'date', 'sessions' => 'integer', 'orders' => 'integer', 'revenue_cents' => 'integer'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
