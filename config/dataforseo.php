<?php

// SEO suite, all data from DataForSEO (pay as you go). Sign up at dataforseo.com, then set the API login and
// password from the dashboard's "API Access" page. Without them the SEO pages stay hidden (admins see setup steps).
return [
    'login' => env('DATAFORSEO_LOGIN'),
    'password' => env('DATAFORSEO_PASSWORD'),
    // The sandbox answers every call with sample data for free: handy to try the pages before paying.
    'base' => env('DATAFORSEO_SANDBOX', false) ? 'https://sandbox.dataforseo.com' : 'https://api.dataforseo.com',

    // Which domain each brand is measured on: "39dg=39dollarglasses.com,ocusafe=ocusafe.com". When empty, every
    // brand whose website is a whole site (not a page on another brand's site) is used.
    'domains' => collect(explode(',', (string) env('DATAFORSEO_DOMAINS', '')))
        ->map(fn ($pair) => array_map('trim', explode('=', $pair, 2)))
        ->filter(fn ($p) => count($p) === 2 && $p[0] !== '' && $p[1] !== '')
        ->mapWithKeys(fn ($p) => [strtolower($p[0]) => strtolower(preg_replace('#^(https?://)?(www\.)?#i', '', rtrim($p[1], '/')))])
        ->all(),

    // Google market: United States, English.
    'location_code' => (int) env('DATAFORSEO_LOCATION', 2840),
    'language_code' => env('DATAFORSEO_LANGUAGE', 'en'),

    // Spending stops for the month at this many US dollars (scheduled refreshes and lookups alike).
    'monthly_budget' => (float) env('DATAFORSEO_MONTHLY_BUDGET', 50),

    // Backlinks is a separate DataForSEO subscription; turn it on once the account has it.
    'backlinks' => (bool) env('DATAFORSEO_BACKLINKS', false),

    // How often each part refreshes by itself, in days.
    'overview_days' => 7,
    'competitors_days' => 7,
    'backlinks_days' => 7,
    'rank_days' => 7,
    'ai_days' => 30,
    'audit_days' => 30,

    // Rank tracking: how deep to look (results, in pages of 10) and how many searches per brand.
    'rank_depth' => 30,
    'max_keywords' => 200,
    // Site audit: pages crawled per run.
    'audit_pages' => 500,
    // Keyword research lookups per person per hour.
    'lookups_per_hour' => 30,

    'timeout' => 60,
];
