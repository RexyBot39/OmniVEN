<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Reddit thread worth a reply. status: new, tasked or skipped. */
class RedditThread extends Model
{
    public const KINDS = [
        'complaint' => 'Unhappy customer',
        'mention' => 'Mentions us',
        'recommend' => 'Asking for an alternative',
        'question' => 'Question we can answer',
    ];

    protected $fillable = ['reddit_id', 'subreddit', 'title', 'url', 'posted_at', 'comments', 'votes', 'kind', 'score', 'matched', 'brand_id', 'status', 'task_id', 'user_id'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime', 'matched' => 'array', 'comments' => 'integer', 'votes' => 'integer', 'score' => 'integer'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
