<?php

// Site check: structured data on key pages (the list is kept on the Site check page) and each brand's
// Google Shopping product feed. PRODUCT_FEEDS="39dg=https://www.39dollarglasses.com/feeds/google.xml,ocusafe=…"
return [
    'feeds' => collect(explode(',', (string) env('PRODUCT_FEEDS', '')))
        ->map(fn ($pair) => array_map('trim', explode('=', $pair, 2)))
        ->filter(fn ($p) => count($p) === 2 && $p[0] !== '' && preg_match('#^https?://#i', $p[1]))
        ->mapWithKeys(fn ($p) => [strtolower($p[0]) => $p[1]])
        ->all(),
    'refresh_minutes' => 1440,
    'page_timeout' => 20,
    'feed_timeout' => 120,
    // Largest feed file read, in megabytes.
    'feed_max_mb' => 200,
];
