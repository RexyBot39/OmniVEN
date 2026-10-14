<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostItItem extends Model
{
    protected $table = 'postit_items';

    protected $fillable = ['postit_id', 'body', 'done_at'];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }

    public function postit(): BelongsTo
    {
        return $this->belongsTo(PostIt::class, 'postit_id');
    }
}
