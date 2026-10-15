<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A calendar item ticked off for one week, and by whom. */
class CalendarCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['calendar_week_id', 'calendar_item_id', 'user_id', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
