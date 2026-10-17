<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A decision on a content gap: "tasked" (with its task) or "dismissed". */
class ContentGap extends Model
{
    protected $fillable = ['key', 'kind', 'brand_id', 'query', 'status', 'task_id', 'user_id'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
