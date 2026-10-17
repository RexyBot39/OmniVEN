<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = ['title', 'details', 'brand_id', 'assignee_id', 'created_by', 'hot', 'due_on', 'completed_at', 'completed_by', 'assigned_at', 'assigned_by', 'template_run_id', 'outcome', 'outcome_url'];

    protected function casts(): array
    {
        return ['hot' => 'boolean', 'due_on' => 'date', 'completed_at' => 'datetime', 'assigned_at' => 'datetime'];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** The brand it's for (39DollarGlasses, Ocusafe or Ocusleep), if labelled. */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** Brands a task can be labelled with, in dropdown order. */
    public static function brandChoices(): \Illuminate\Support\Collection
    {
        $order = config('omniven.task_brands');

        return Brand::whereIn('slug', $order)->get()->sortBy(fn ($b) => array_search($b->slug, $order, true))->values();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The admin who assigned it (or last reassigned it). */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
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
