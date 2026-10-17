<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One brand ticked for a per-brand routine in one period. */
class RoutineBrandCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['routine_id', 'brand_id', 'period_key', 'user_id', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
