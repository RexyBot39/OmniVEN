<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One TaskBuddy AI job: a source (pasted text or an uploaded file), the questions it asked and the
 * answers, and the draft task list until it's added. The source is encrypted and is cleared once the
 * tasks are added, or after a week.
 */
class TaskbuddyRun extends Model
{
    protected $fillable = ['user_id', 'assignee_id', 'brand_id', 'title', 'source_name', 'source_kind', 'source_mime', 'source_text', 'source_file', 'note',
        'status', 'error', 'summary', 'questions', 'answers', 'draft', 'feedback', 'rounds', 'input_tokens', 'output_tokens', 'task_ids'];

    protected $hidden = ['source_text', 'source_file'];

    protected function casts(): array
    {
        return [
            'source_text' => 'encrypted',
            'source_file' => 'encrypted',
            'note' => 'encrypted',
            'questions' => 'array',
            'answers' => 'array',
            'draft' => 'array',
            'task_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function isBusy(): bool
    {
        return $this->status === 'pending';
    }

    /** Drop the source once it's no longer needed. */
    public function forgetSource(): void
    {
        $this->forceFill(['source_text' => null, 'source_file' => null, 'note' => null])->save();
    }
}
