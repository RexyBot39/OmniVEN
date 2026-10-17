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

    // Content gaps: which non-branded searches need work. Impressions are over the last 28 days.
    'gaps' => [
        'min_impressions' => 30,
        // Ranks in the top 5 but few people click: the title and description need work.
        'ctr' => ['max_position' => 5, 'min_impressions' => 200, 'below_ctr' => 0.02],
        // Position 8–20: a better section on the page could reach the top 5.
        'striking' => ['from' => 8, 'to' => 20, 'min_impressions' => 100],
        // A question people ask where we're past the top 10: needs a page that answers it.
        'question' => ['beyond' => 10, 'min_impressions' => 30],
        'per_brand' => 40,
    ],
    'timeout' => 30,
];
