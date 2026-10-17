<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A page the Site check reads. status: new, ok, warn, fail, or error (couldn't load it). */
class SitePage extends Model
{
    protected $fillable = ['brand_id', 'url', 'kind', 'status', 'issues', 'found', 'error', 'checked_at', 'user_id'];

    protected function casts(): array
    {
        return ['issues' => 'array', 'found' => 'array', 'checked_at' => 'datetime'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function path(): string
    {
        $p = parse_url($this->url);

        return ($p['host'] ?? '').($p['path'] ?? '/');
    }

    public function richResultsUrl(): string
    {
        return 'https://search.google.com/test/rich-results?url='.rawurlencode($this->url);
    }
}
