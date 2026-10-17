<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step in a publisher's outreach: a pitch, a nudge, a reply, a note or a stage change. */
class OutreachEvent extends Model
{
    public const KINDS = [
        'pitched' => 'Pitched',
        'nudged' => 'Followed up',
        'replied' => 'They replied',
        'note' => 'Note',
        'stage' => 'Moved',
        'live' => 'Went live',
    ];

    protected $fillable = ['publisher_id', 'user_id', 'kind', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }
}
