<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = ['title', 'details', 'assignee_id', 'created_by', 'hot', 'due_on', 'completed_at', 'completed_by'];

    protected function casts(): array
    {
        return ['hot' => 'boolean', 'due_on' => 'date', 'completed_at' => 'datetime'];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('completed_at');
    }

    /** Hotlist = flagged tasks that aren't done yet. */
    public function scopeHotlist(Builder $q): Builder
    {
        return $q->open()->where('hot', true);
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_on && $this->due_on->lt(today());
    }

    public function dueLabel(): ?string
    {
        if (! $this->due_on) {
            return null;
        }
        if ($this->due_on->isToday()) {
            return 'Due today';
        }
        if ($this->due_on->isTomorrow()) {
            return 'Due tomorrow';
        }

        return ($this->isOverdue() ? 'Overdue · ' : 'Due ').$this->due_on->format('M j');
    }
}
