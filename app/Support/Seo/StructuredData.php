<?php

namespace App\Support\Seo;

/**
 * Reads a page's structured data (JSON-LD) and says what Google needs that's missing, by page kind.
 * "fail" means rich results (price, stars, stock in search) can't show; "warn" means they could be better.
 */
class StructuredData
{
    public const KINDS = [
        'product' => 'Product page',
        'home' => 'Home page',
        'category' => 'Category page',
        'article' => 'Blog post or guide',
    ];

    /** @return array{status: string, issues: list<array{0: string, 1: string}>, found: list<string>} */
    public static function analyze(string $html, string $kind, string $url): array
    {
        $issues = [];
        [$nodes, $broken] = self::nodes($html);
        $found = array_values(array_unique(array_filter(array_map(fn ($n) => self::types($n)[0] ?? null, $nodes))));

        if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]*content=["\'][^"\']*noindex/i', $html) || preg_match('/<meta[^>]+content=["\'][^"\']*noindex[^>]*name=["\']robots["\']/i', $html)) {
            $issues[] = ['fail', 'The page tells Google not to index it (robots noindex).'];
        }
        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m) || preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]*rel=["\']canonical["\']/i', $html, $m)) {
            $canon = rtrim((string) parse_url(html_entity_decode($m[1]), PHP_URL_PATH), '/');
            if ($canon !== rtrim((string) parse_url($url, PHP_URL_PATH), '/')) {
                $issues[] = ['warn', 'Its canonical tag points to another page ('.($canon ?: '/').'), so Google may show that one instead.'];
            }
        }
        if ($broken) {
            $issues[] = ['fail', $broken.' structured data '.($broken === 1 ? 'block has' : 'blocks have').' a syntax error, so Google ignores '.($broken === 1 ? 'it' : 'them').'.'];
        }
        if (! $nodes) {
            $issues[] = preg_match('/itemtype=["\']https?:\/\/schema\.org\//i', $html)
                ? ['warn', 'Uses the older microdata format, which OmniVEN can’t read. Check it with Google’s Rich Results Test.']
                : [$kind === 'product' ? 'fail' : 'warn', 'No structured data found.'];

            return self::result($issues, $found);
        }

        match ($kind) {
            'product' => self::product($nodes, $issues),
            'home' => self::home($nodes, $issues),
            'category' => self::category($nodes, $issues),
            'article' => self::article($nodes, $issues),
            default => null,
        };

        return self::result($issues, $found);
    }

    private static function product(array $nodes, array &$issues): void
    {
        $p = self::first($nodes, 'Product') ?? self::first($nodes, 'ProductGroup');
        if (! $p) {
            $issues[] = ['fail', 'No Product data, so Google can’t show price, stock or stars for this page.'];

            return;
        }
        foreach (['name' => 'name', 'image' => 'image'] as $f => $label) {
            if (self::blank($p[$f] ?? null)) {
                $issues[] = ['fail', "Product has no {$label}."];
            }
        }
        $offers = $p['offers'] ?? ($p['hasVariant'][0]['offers'] ?? null);
        $offer = is_array($offers) && array_is_list($offers) ? ($offers[0] ?? null) : $offers;
        if (! is_array($offer)) {
            $issues[] = ['fail', 'Product has no offer (price and stock).'];
        } else {
            $price = $offer['price'] ?? $offer['lowPrice'] ?? ($offer['priceSpecification']['price'] ?? null);
            if (self::blank($price) || ! is_numeric(str_replace(',', '', (string) $price))) {
                $issues[] = ['fail', 'The offer has no usable price.'];
            } elseif ((float) str_replace(',', '', (string) $price) <= 0) {
                $issues[] = ['fail', 'The price is 0.'];
            }
            if (self::blank($offer['priceCurrency'] ?? ($offer['priceSpecification']['priceCurrency'] ?? null))) {
                $issues[] = ['fail', 'The offer has no currency (priceCurrency).'];
            }
            $avail = (string) ($offer['availability'] ?? '');
            if ($avail === '') {
                $issues[] = ['warn', 'The offer doesn’t say whether it’s in stock (availability).'];
            } elseif (! preg_match('#schema\.org/(InStock|OutOfStock|PreOrder|BackOrder|LimitedAvailability|OnlineOnly|InStoreOnly|SoldOut|Discontinued|PreSale|MadeToOrder|Reserved)$#i', $avail)) {
                $issues[] = ['warn', 'Availability “'.mb_substr($avail, 0, 40).'” isn’t a value Google knows; use https://schema.org/InStock.'];
            }
            if (empty($offer['shippingDetails']) && empty($offer['hasMerchantReturnPolicy']) && empty($p['hasMerchantReturnPolicy'])) {
                $issues[] = ['warn', 'No shipping or return policy in the offer; Google shows these in free product listings.'];
            }
        }
        if (empty($p['aggregateRating']) && empty($p['review'])) {
            $issues[] = ['warn', 'No rating (aggregateRating), so no stars in search results.'];
        } elseif (! empty($p['aggregateRating']) && (self::blank($p['aggregateRating']['ratingValue'] ?? null) || self::blank($p['aggregateRating']['reviewCount'] ?? $p['aggregateRating']['ratingCount'] ?? null))) {
            $issues[] = ['fail', 'The rating is missing its value or count, so Google ignores it.'];
        }
        if (self::blank($p['brand'] ?? null)) {
            $issues[] = ['warn', 'Product has no brand.'];
        }
        if (self::blank($p['sku'] ?? null) && self::blank($p['gtin'] ?? $p['gtin13'] ?? $p['gtin12'] ?? $p['gtin14'] ?? $p['gtin8'] ?? null) && self::blank($p['mpn'] ?? null)) {
            $issues[] = ['warn', 'Product has no SKU, GTIN or MPN to match it to the product feed.'];
        }
        if (self::blank($p['description'] ?? null)) {
            $issues[] = ['warn', 'Product has no description.'];
        }
    }

    private static function home(array $nodes, array &$issues): void
    {
        $org = self::first($nodes, 'Organization') ?? self::first($nodes, 'OnlineStore') ?? self::first($nodes, 'Store') ?? self::first($nodes, 'Corporation');
        if (! $org) {
            $issues[] = ['warn', 'No Organization data (name, logo, social profiles), which helps Google and AI assistants know the brand.'];
        } else {
            if (self::blank($org['logo'] ?? null)) {
                $issues[] = ['warn', 'Organization has no logo.'];
            }
            if (self::blank($org['sameAs'] ?? null)) {
                $issues[] = ['warn', 'Organization doesn’t list the brand’s social profiles (sameAs).'];
            }
        }
        if (! self::has($nodes, 'WebSite')) {
            $issues[] = ['warn', 'No WebSite data, which gives Google the site name to show.'];
        }
    }

    private static function category(array $nodes, array &$issues): void
    {
        if (! self::has($nodes, 'BreadcrumbList')) {
            $issues[] = ['warn', 'No breadcrumb (BreadcrumbList), which shows the page’s place in the site in search results.'];
        }
    }

    private static function article(array $nodes, array &$issues): void
    {
        $a = self::first($nodes, 'Article') ?? self::first($nodes, 'BlogPosting') ?? self::first($nodes, 'NewsArticle');
        if (! $a) {
            $issues[] = ['warn', 'No Article data (headline, author, date).'];

            return;
        }
        foreach (['headline' => 'headline', 'image' => 'image', 'datePublished' => 'publish date', 'author' => 'author'] as $f => $label) {
            if (self::blank($a[$f] ?? null)) {
                $issues[] = ['warn', "Article has no {$label}."];
            }
        }
        if (! self::has($nodes, 'FAQPage')) {
            $issues[] = ['info', 'Tip: a short FAQ (FAQPage) helps AI assistants quote the answer.'];
        }
    }

    /** Every JSON-LD object on the page, @graph flattened, and how many blocks didn't parse. */
    private static function nodes(string $html): array
    {
        preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m);
        $nodes = [];
        $broken = 0;
        foreach ($m[1] as $raw) {
            $data = json_decode(trim(html_entity_decode($raw, ENT_NOQUOTES)), true);
            if (! is_array($data)) {
                $data = json_decode(trim($raw), true);
            }
            if (! is_array($data)) {
                $broken++;

                continue;
            }
            foreach (array_is_list($data) ? $data : [$data] as $d) {
                if (! is_array($d)) {
                    continue;
                }
                foreach (isset($d['@graph']) && is_array($d['@graph']) ? $d['@graph'] : [$d] as $n) {
                    if (is_array($n)) {
                        $nodes[] = $n;
                    }
                }
            }
        }

        return [$nodes, $broken];
    }

    private static function types(array $n): array
    {
        $t = $n['@type'] ?? [];

        return array_map(fn ($x) => preg_replace('#^https?://schema\.org/#', '', (string) $x), is_array($t) ? $t : [$t]);
    }

    private static function first(array $nodes, string $type): ?array
    {
        foreach ($nodes as $n) {
            if (in_array($type, self::types($n), true)) {
                return $n;
            }
        }

        return null;
    }

    private static function has(array $nodes, string $type): bool
    {
        return self::first($nodes, $type) !== null;
    }

    private static function blank(mixed $v): bool
    {
        return $v === null || $v === '' || $v === [];
    }

    private static function result(array $issues, array $found): array
    {
        $levels = array_column($issues, 0);

        return [
            'status' => in_array('fail', $levels, true) ? 'fail' : (in_array('warn', $levels, true) ? 'warn' : 'ok'),
            'issues' => $issues,
            'found' => $found,
        ];
    }
}
