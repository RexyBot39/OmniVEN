<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostIt extends Model
{
    protected $table = 'postits';

    protected $fillable = ['user_id', 'title', 'color'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        // Open items first (in the order added), then finished ones.
        return $this->hasMany(PostItItem::class, 'postit_id')->orderByRaw('done_at is not null')->orderBy('id');
    }
}
