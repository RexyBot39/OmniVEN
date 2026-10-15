<?php

namespace App\Models;

use App\Models\Concerns\HasAccessRequests;
use App\Models\Concerns\OnLaunchpad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A brand's account on one platform, e.g. Ocusafe · Instagram · @ocusafe. */
class Account extends Model
{
    use HasAccessRequests, OnLaunchpad;

    protected $fillable = ['brand_id', 'platform', 'handle', 'url', 'account_ref', 'login', 'owner', 'notes', 'verified_on'];

    protected function casts(): array
    {
        return ['verified_on' => 'date'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function platformInfo(): array
    {
        return config('omniven.platforms')[$this->platform] ?? config('omniven.platforms')['other'];
    }

    public function platformLabel(): string
    {
        return $this->platformInfo()['label'];
    }

    public function group(): string
    {
        return $this->platformInfo()['group'];
    }

    /** Direct link when we have one, otherwise the platform's sign-in page. */
    public function openUrl(): ?string
    {
        return $this->url ?: $this->platformInfo()['home'];
    }

    public function hasDirectLink(): bool
    {
        return filled($this->url);
    }

    /** e.g. "Ocusafe · Instagram · @ocusafe" */
    public function title(): string
    {
        return collect([$this->brand?->name ?? 'All brands', $this->platformLabel(), $this->handle])->filter()->implode(' · ');
    }
}
