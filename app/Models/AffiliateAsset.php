<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * An approved asset for affiliates and publishers: an image (banner, product photo, logo), a discount
 * code, a piece of copy, or a link (a photo folder, a landing page). Codes can belong to one publisher.
 */
class AffiliateAsset extends Model
{
    public const KINDS = [
        'image' => 'Banners and images',
        'code' => 'Discount codes',
        'copy' => 'Copy',
        'link' => 'Links',
    ];

    public const MIMES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    protected $fillable = ['brand_id', 'kind', 'title', 'body', 'url', 'publisher_id', 'internal', 'starts_on', 'ends_on', 'file_name', 'file_mime', 'file_size', 'width', 'height', 'sha1', 'user_id'];

    protected function casts(): array
    {
        return ['internal' => 'boolean'];
    }

    protected function startsOn(): Attribute
    {
        return self::plainDate();
    }

    protected function endsOn(): Attribute
    {
        return self::plainDate();
    }

    private static function plainDate(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v === null || $v === '' ? null : CarbonImmutable::parse(substr((string) $v, 0, 10), config('app.timezone')),
            set: fn ($v) => $v === null || $v === '' ? null : CarbonImmutable::parse($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v)->format('Y-m-d'),
        );
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /** Not started yet, running, or ended. Assets without dates are always running. */
    public function state(): string
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        return match (true) {
            $this->starts_on && $this->starts_on->gt($today) => 'upcoming',
            $this->ends_on && $this->ends_on->lt($today) => 'ended',
            default => 'active',
        };
    }

    /** Running today (or with no dates). */
    public function scopeCurrent(Builder $q): Builder
    {
        $today = CarbonImmutable::now(config('app.timezone'))->format('Y-m-d');

        return $q->where(fn ($w) => $w->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
            ->where(fn ($w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', $today));
    }

    /** What a publisher's kit shows: shared assets for the brand (or any brand), plus their own codes. */
    public function scopeForKit(Builder $q, ?int $brandId, ?int $publisherId): Builder
    {
        return $q->current()->where('internal', false)
            ->when($brandId, fn ($w) => $w->where(fn ($b) => $b->where('brand_id', $brandId)->orWhereNull('brand_id')))
            ->where(fn ($w) => $w->whereNull('publisher_id')->when($publisherId, fn ($p) => $p->orWhere('publisher_id', $publisherId)));
    }

    public function saveFile(string $bytes): void
    {
        DB::table('affiliate_asset_files')->updateOrInsert(['affiliate_asset_id' => $this->id], ['data' => base64_encode($bytes)]);
    }

    public function fileBytes(): ?string
    {
        $v = DB::table('affiliate_asset_files')->where('affiliate_asset_id', $this->id)->value('data');

        return $v === null ? null : (base64_decode((string) $v, true) ?: null);
    }

    public function downloadName(): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo((string) $this->file_name, PATHINFO_FILENAME) ?: $this->title);

        return trim($base, '-').'.'.(self::MIMES[$this->file_mime] ?? 'bin');
    }
}
