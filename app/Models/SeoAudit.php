<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One DataForSEO crawl of a brand's site: the score, issue counts and the pages that need the most work. */
class SeoAudit extends Model
{
    protected $fillable = ['brand_id', 'task_id', 'status', 'pages_crawled', 'max_pages', 'score', 'summary', 'pages', 'error', 'started_by', 'finished_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'pages' => 'array', 'finished_at' => 'datetime', 'score' => 'float'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
