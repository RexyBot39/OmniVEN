<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A destination URL tagged with utm_source / medium / campaign (and optional content, term). */
class UtmLink extends Model
{
    protected $fillable = ['user_id', 'brand_id', 'preset', 'base_url', 'source', 'medium', 'campaign', 'content', 'term', 'url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** Lowercase, spaces to hyphens, only letters, digits, dot, dash and underscore: the same name every time. */
    public static function clean(?string $v): string
    {
        $v = mb_strtolower(trim((string) $v));
        $v = preg_replace('/\s+/', '-', $v);
        $v = preg_replace('/[^a-z0-9._-]+/', '', $v);

        return trim(preg_replace('/-{2,}/', '-', $v), '-');
    }

    /** All presets, flattened: key => [label, source, medium, channel, group]. */
    public static function presets(): array
    {
        $out = [];
        foreach (config('utm.presets') as $group => $items) {
            foreach ($items as $key => [$label, $source, $medium, $channel]) {
                $out[$key] = [$label, $source, $medium, $channel, $group];
            }
        }

        return $out;
    }

    /** The destination with any old utm_ parameters replaced by ours; other parameters and the #fragment are kept. */
    public static function tag(string $base, array $utm): string
    {
        $parts = parse_url($base);
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter($query, fn ($k) => ! str_starts_with(strtolower((string) $k), 'utm_'), ARRAY_FILTER_USE_KEY);
        foreach (['source', 'medium', 'campaign', 'content', 'term'] as $k) {
            if (($utm[$k] ?? '') !== '') {
                $query['utm_'.$k] = $utm[$k];
            }
        }

        $url = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '/');

        return $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986).(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    public function channelLabel(): ?string
    {
        $channel = self::presets()[$this->preset][3] ?? null;

        return $channel ? config('revenue.channels.'.$channel) : null;
    }
}
