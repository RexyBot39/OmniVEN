<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A reusable checklist (product launch, seasonal sale…) whose tasks are due relative to a start day. */
class TaskTemplate extends Model
{
    protected $fillable = ['name', 'description', 'sort'];

    public function items(): HasMany
    {
        return $this->hasMany(TaskTemplateItem::class)->orderBy('offset_days')->orderBy('sort')->orderBy('id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TemplateRun::class);
    }
}
