<?php

// Search Console summary per brand, read with the same Google service account as Sales (add its email
// as a user on each Search Console property). "39dg=sc-domain:39dollarglasses.com,ocusafe=https://www.ocusafe.com/".
return [
    'sites' => collect(explode(',', (string) env('SEARCH_CONSOLE_SITES', '')))
        ->map(fn ($pair) => array_map('trim', explode('=', $pair, 2)))
        ->filter(fn ($p) => count($p) === 2 && $p[0] !== '' && $p[1] !== '')
        ->mapWithKeys(fn ($p) => [strtolower($p[0]) => $p[1]])
        ->all(),

    // Searches containing any of these count as branded (people already looking for us).
    'brand_terms' => [
        '39dg' => ['39dollar', '39 dollar', '$39 glasses', '39dg', '39 glasses'],
        'ocusafe' => ['ocusafe', 'ocu safe'],
        'ocusleep' => ['ocusleep', 'ocu sleep'],
        'neurolux' => ['neurolux'],
    ],
    // Search Console data runs about three days behind.
    'lag_days' => 3,
    'window_days' => 28,
    'refresh_minutes' => 360,
    'timeout' => 30,
];
