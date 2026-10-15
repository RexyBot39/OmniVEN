<?php

namespace App\Support;

use App\Models\Tool;
use Illuminate\Support\HtmlString;

/** Finds the brand mark (Instagram, Google Ads, Klaviyo…) for a platform, link or tool, and draws it in its brand color. */
class BrandIcon
{
    /** Account platform key => icon slug. */
    private const PLATFORMS = [
        'instagram' => 'instagram', 'facebook' => 'facebook', 'tiktok' => 'tiktok', 'youtube' => 'youtube',
        'pinterest' => 'pinterest', 'x' => 'x', 'linkedin' => 'linkedin', 'reddit' => 'reddit',
        'meta_ads' => 'meta', 'meta_commerce' => 'meta', 'tiktok_ads' => 'tiktok', 'google_ads' => 'googleads',
        'microsoft_ads' => 'microsoft', 'merchant_center' => 'google', 'shopify' => 'shopify', 'ga4' => 'googleanalytics',
        'search_console' => 'googlesearchconsole', 'gtm' => 'googletagmanager', 'bing_webmaster' => 'microsoft',
        'clarity' => 'microsoft', 'klaviyo' => 'klaviyo', 'google_business' => 'google', 'trustpilot' => 'trustpilot', 'shopper_approved' => 'shopperapproved',
    ];

    /** Host (or host suffix) => icon slug, most specific first. */
    private const HOSTS = [
        'ads.google.com' => 'googleads', 'analytics.google.com' => 'googleanalytics', 'search.google.com' => 'googlesearchconsole',
        'tagmanager.google.com' => 'googletagmanager', 'drive.google.com' => 'googledrive', 'mail.google.com' => 'gmail',
        'business.facebook.com' => 'meta', 'adsmanager.facebook.com' => 'meta',
        'instagram.com' => 'instagram', 'facebook.com' => 'facebook', 'tiktok.com' => 'tiktok', 'youtube.com' => 'youtube',
        'youtu.be' => 'youtube', 'pinterest.com' => 'pinterest', 'x.com' => 'x', 'twitter.com' => 'x', 'linkedin.com' => 'linkedin',
        'reddit.com' => 'reddit', 'klaviyo.com' => 'klaviyo', 'shopify.com' => 'shopify', 'slack.com' => 'slack',
        'trustpilot.com' => 'trustpilot', 'shopperapproved.com' => 'shopperapproved', 'figma.com' => 'figma', 'claude.ai' => 'claude', 'feedly.com' => 'feedly',
        'bing.com' => 'microsoft', 'microsoft.com' => 'microsoft', 'google.com' => 'google',
    ];

    /** Tool or label words => icon slug, for tools without a link. */
    private const NAMES = [
        'google ads' => 'googleads', 'google analytics' => 'googleanalytics', 'search console' => 'googlesearchconsole',
        'tag manager' => 'googletagmanager', 'google drive' => 'googledrive', 'gmail' => 'gmail', 'meta' => 'meta',
        'instagram' => 'instagram', 'facebook' => 'facebook', 'tiktok' => 'tiktok', 'youtube' => 'youtube',
        'pinterest' => 'pinterest', 'linkedin' => 'linkedin', 'reddit' => 'reddit', 'klaviyo' => 'klaviyo',
        'shopify' => 'shopify', 'slack' => 'slack', 'trustpilot' => 'trustpilot', 'shopper approved' => 'shopperapproved', 'figma' => 'figma', 'claude' => 'claude',
        'feedly' => 'feedly', 'microsoft' => 'microsoft', 'bing' => 'microsoft', 'clarity' => 'microsoft', 'google' => 'google',
    ];

    public static function forPlatform(?string $platform): ?string
    {
        return self::PLATFORMS[$platform] ?? null;
    }

    public static function forUrl(?string $url): ?string
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }
        $host = preg_replace('/^www\./', '', $host);
        foreach (self::HOSTS as $h => $slug) {
            if ($host === $h || str_ends_with($host, '.'.$h)) {
                return $slug;
            }
        }

        return null;
    }

    public static function forName(?string $name): ?string
    {
        $n = strtolower((string) $name);
        foreach (self::NAMES as $word => $slug) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $n)) {
                return $slug;
            }
        }

        return null;
    }

    /** Platforms named in free text ("IG carousel + TikTok reel"), in order, for small logos beside it. */
    public static function mentions(?string $text): array
    {
        $patterns = [
            'instagram' => '/\b(instagram|ig)\b/i', 'facebook' => '/\b(facebook|fb)\b/i', 'tiktok' => '/\btiktok\b/i',
            'linkedin' => '/\blinkedin\b/i', 'youtube' => '/\byoutube\b/i', 'pinterest' => '/\bpinterest\b/i',
            'meta' => '/\bads manager\b/i', 'feedly' => '/\bfeedly\b/i', 'klaviyo' => '/\bklaviyo\b/i', 'reddit' => '/\breddit\b/i',
        ];
        $found = [];
        foreach ($patterns as $slug => $re) {
            if (preg_match($re, (string) $text, $m, PREG_OFFSET_CAPTURE)) {
                $found[$slug] = $m[0][1];
            }
        }
        asort($found);

        return array_keys($found);
    }

    public static function forTool(Tool $tool): ?string
    {
        return self::forUrl($tool->url) ?? self::forName($tool->name);
    }

    /** The link first, then the label: a "Klaviyo" social entry with no link still gets Klaviyo. */
    public static function forLink(?string $label, ?string $url): ?string
    {
        return self::forUrl($url) ?? self::forName($label);
    }

    /** ['slug' => …, 'hex' => …] for the launcher JSON, or null. */
    public static function meta(?string $slug): ?array
    {
        $m = $slug ? config("brand_icons.$slug") : null;

        return $m ? ['s' => $slug, 'h' => $m['hex'], 'c' => self::classes($slug, $m)] : null;
    }

    private static function classes(string $slug, array $m): string
    {
        return 'bi-'.$slug.(! empty($m['multicolor']) ? ' bi-multi' : '').(in_array($m['hex'], ['#000000', '#232426'], true) ? ' bi-dark' : '');
    }

    public static function spriteUrl(): string
    {
        static $url = null;

        return $url ??= asset('images/brand-icons.svg').'?v='.@filemtime(public_path('images/brand-icons.svg'));
    }

    /** The mark on a tile of its brand color. $size: xs (inline with text), sm, md, lg. Empty when no mark is known. */
    public static function html(?string $slug, string $size = 'sm', ?string $title = null): HtmlString
    {
        $m = $slug ? config("brand_icons.$slug") : null;
        if (! $m) {
            return new HtmlString('');
        }
        $label = e($title ?? $m['label']);
        $cls = 'bi bi-'.$size.' '.self::classes($slug, $m);

        // Small marks sit next to text that already names the brand, so screen readers skip them.
        $a11y = $size === 'xs' ? 'aria-hidden="true"' : 'role="img" aria-label="'.$label.'" title="'.$label.'"';

        return new HtmlString('<span class="'.$cls.'" style="--bi:'.e($m['hex']).'" '.$a11y.'>'
            .'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="'.e(self::spriteUrl()).'#bi-'.$slug.'"></use></svg></span>');
    }
}
