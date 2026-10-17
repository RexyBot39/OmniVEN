<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step of a template: due offset_days from the start day (negative = before). "{name}" is replaced. */
class TaskTemplateItem extends Model
{
    protected $fillable = ['task_template_id', 'title', 'details', 'offset_days', 'assignee_id', 'hot', 'sort'];

    protected function casts(): array
    {
        return ['hot' => 'boolean', 'offset_days' => 'integer'];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function whenLabel(): string
    {
        $d = $this->offset_days;

        return match (true) {
            $d === 0 => 'Start day',
            $d < 0 => abs($d).' '.($d === -1 ? 'day' : 'days').' before',
            default => $d.' '.($d === 1 ? 'day' : 'days').' after',
        };
    }

    /** Fill in "{name}" with the run's name. */
    public static function withName(?string $text, string $name): ?string
    {
        return $text === null ? null : str_replace(['{name}', '{Name}'], $name, $text);
    }
}
