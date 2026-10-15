<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A calendar action: part of the every-week runbook (no week) or a one-off for one week. */
class CalendarItem extends Model
{
    protected $fillable = ['calendar_week_id', 'key', 'day', 'brand_slug', 'title', 'keyword', 'owner', 'done_when', 'sort'];

    public function week(): BelongsTo
    {
        return $this->belongsTo(CalendarWeek::class, 'calendar_week_id');
    }

    public function dayHeading(): string
    {
        return match ($this->day) {
            'Setup' => 'One-off this week',
            'Review' => 'Review milestone',
            default => ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'][$this->day] ?? $this->day,
        };
    }
}
