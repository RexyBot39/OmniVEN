<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A public government bid or RFP that may suit Ocusafe, and what the team decided about it. */
class Opportunity extends Model
{
    public const STATUSES = [
        'new' => 'New',
        'pursuing' => 'Pursuing',
        'submitted' => 'Bid submitted',
        'won' => 'Won',
        'lost' => 'Lost',
        'passed' => 'Passed',
    ];

    public const SOURCES = [
        'sam' => 'SAM.gov',
        'sled' => 'State & local',
        'dibbs_rfq' => 'DLA DIBBS RFQ',
        'dibbs_rfp' => 'DLA DIBBS RFP',
    ];

    protected $fillable = ['source', 'external_id', 'title', 'agency', 'solicitation_number', 'kind', 'set_aside', 'codes', 'place', 'summary', 'url',
        'posted_at', 'deadline_at', 'open', 'score', 'matched', 'status', 'owner_id', 'task_id', 'decided_by', 'decided_at', 'first_seen_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime', 'deadline_at' => 'datetime', 'decided_at' => 'datetime', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'open' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** Still taking bids: marked open and the deadline (if any) hasn't passed. */
    public function isOpen(): bool
    {
        return $this->open && (! $this->deadline_at || $this->deadline_at->isFuture());
    }

    public function daysLeft(): ?int
    {
        // Calendar days, so something due the day after tomorrow reads "2 days left" all day today.
        return $this->deadline_at && $this->deadline_at->isFuture() ? (int) today()->diffInDays($this->deadline_at->copy()->startOfDay()) : null;
    }
}
