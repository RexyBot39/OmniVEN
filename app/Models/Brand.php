<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Brand extends Model
{
    protected $fillable = ['slug', 'name', 'site', 'focus', 'channels', 'socials', 'notes', 'sort'];

    protected function casts(): array
    {
        return ['socials' => 'array', 'sort' => 'integer'];
    }

    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class);
    }

    public function accounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function routines(): BelongsToMany
    {
        return $this->belongsToMany(Routine::class);
    }

    /** Palette slot 1-8 for this brand's color token. */
    public function colorSlot(): int
    {
        return (($this->sort - 1) % 8 + 8) % 8 + 1;
    }
}
