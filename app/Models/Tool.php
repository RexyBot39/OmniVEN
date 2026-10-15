<?php

namespace App\Models;

use App\Models\Concerns\HasBrandScope;
use App\Models\Concerns\OnLaunchpad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tool extends Model
{
    use HasBrandScope, OnLaunchpad;

    protected $fillable = ['name', 'url', 'category', 'description', 'status', 'featured', 'all_brands', 'login', 'owner', 'access_note', 'sort'];

    protected function casts(): array
    {
        return ['featured' => 'boolean', 'all_brands' => 'boolean', 'sort' => 'integer'];
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
    }

    public function categorySlot(): int
    {
        $i = array_search($this->category, config('omniven.categories'), true);

        return ($i === false ? 0 : $i) % 8 + 1;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/[^A-Za-z0-9 ]/', ' ', $this->name)));

        return strtoupper(implode('', array_map(fn ($w) => $w[0] ?? '', array_slice($words, 0, 2)))) ?: '?';
    }

    public function host(): string
    {
        return preg_replace('/^www\./', '', (string) parse_url((string) $this->url, PHP_URL_HOST));
    }

    public function statusLabel(): string
    {
        return config('omniven.statuses')[$this->status] ?? 'Active';
    }
}
