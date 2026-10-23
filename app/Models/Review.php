<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A public review from Shopper Approved or Trustpilot: stars, date, its text (a short excerpt and the whole review) and a link. */
class Review extends Model
{
    protected $fillable = ['platform', 'external_id', 'brand_id', 'stars', 'reviewed_at', 'excerpt', 'body', 'url', 'task_id'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'stars' => 'integer'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function platformLabel(): string
    {
        return config('reviews.platforms.'.$this->platform.'.label', $this->platform);
    }
}
