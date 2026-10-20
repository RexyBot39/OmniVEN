<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A Google search we track for one brand: where its site ranks, how that changed and the page that ranks. */
class SeoKeyword extends Model
{
    protected $fillable = ['brand_id', 'keyword', 'position', 'previous_position', 'best_position', 'url', 'search_volume', 'difficulty',
        'task_id', 'task_posted_at', 'checked_at', 'created_by'];

    protected function casts(): array
    {
        return ['task_posted_at' => 'datetime', 'checked_at' => 'datetime'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(SeoRankCheck::class);
    }

    /** Places gained since the check before (positive is better), or null when either check had no position. */
    public function change(): ?int
    {
        return $this->position && $this->previous_position ? $this->previous_position - $this->position : null;
    }

    public static function normalize(string $keyword): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $keyword)));
    }
}
