<?php

namespace App\Models;

use App\Models\Concerns\OnLaunchpad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Brand extends Model
{
    use OnLaunchpad;

    protected $fillable = ['slug', 'name', 'site', 'focus', 'channels', 'socials', 'notes', 'sort', 'accounts_from_id'];

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

    /** The brand whose accounts this one uses, e.g. Lumeo → 39DollarGlasses. */
    public function accountsFrom(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Brand::class, 'accounts_from_id');
    }

    /** The brand whose accounts to show for this one: itself, or the brand it shares with. */
    public function accountSource(): Brand
    {
        return $this->accountsFrom ?? $this;
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
