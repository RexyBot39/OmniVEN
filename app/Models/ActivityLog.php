<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'description', 'subject_type', 'subject_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Groups for the report's filters and summary columns. */
    public const CATEGORIES = [
        'sessions' => ['label' => 'Sign-ins & visits', 'actions' => ['signed_in', 'signed_out', 'visited']],
        'tasks' => ['label' => 'Tasks', 'actions' => ['task.assigned', 'task.updated', 'task.deleted', 'task.completed', 'task.reopened']],
        'routines' => ['label' => 'Routines', 'actions' => ['routine.done', 'routine.undone']],
        'content' => ['label' => 'Content', 'actions' => [
            'tool.created', 'tool.updated', 'tool.deleted',
            'brand.created', 'brand.updated', 'brand.deleted',
            'routine.created', 'routine.updated', 'routine.deleted',
            'note.posted', 'note.updated', 'note.deleted',
            'account.created', 'account.updated', 'account.deleted', 'account.verified',
        ]],
        'postit' => ['label' => 'PostIT Notes', 'actions' => ['postit.created', 'postit.deleted', 'postit.item_added', 'postit.item_done', 'postit.item_undone', 'postit.cleared']],
        'slack' => ['label' => 'Slack', 'actions' => ['slack.posted', 'slack.channel_added', 'slack.channel_removed']],
        'people' => ['label' => 'People', 'actions' => ['person.added', 'person.updated']],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): string
    {
        foreach (self::CATEGORIES as $key => $c) {
            if (in_array($this->action, $c['actions'], true)) {
                return $key;
            }
        }

        return 'other';
    }
}
