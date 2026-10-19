<?php

// Sales numbers for the revenue strip and the Sales page, read from Google Analytics 4 with a
// read-only service account. Only daily totals (sessions, orders, revenue per brand and channel)
// are stored; nothing about individual customers or orders.
return [
    'ga4' => [
        // The service account's JSON key, pasted as-is or base64-encoded. Leave empty to hide revenue.
        'credentials' => env('GA4_CREDENTIALS'),
        // Which GA4 property belongs to which brand: "39dg=123456789,ocusafe=234567890".
        'properties' => collect(explode(',', (string) env('GA4_PROPERTIES', '')))
            ->map(fn ($pair) => array_map('trim', explode('=', $pair, 2)))
            ->filter(fn ($p) => count($p) === 2 && $p[0] !== '' && ctype_digit($p[1]))
            ->mapWithKeys(fn ($p) => [strtolower($p[0]) => $p[1]])
            ->all(),
        'timeout' => 20,
        // The earliest date the GA4 Data API accepts; history is loaded back to the first month with data.
        'earliest' => '2015-08-14',
    ],

    // Besides admins, these people can see revenue (comma-separated emails).
    'viewers' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('OMNIVEN_REVENUE_VIEWERS', ''))
    ))),

    // How stale the numbers may get before a page view refreshes them in the background.
    'refresh_minutes' => 30,
    // History: everything GA4 has, back to each property's first month, unless REVENUE_HISTORY_DAYS
    // sets a limit. Filled in a few months per refresh (or all at once with omniven:revenue-sync --history).
    'backfill_days' => env('REVENUE_HISTORY_DAYS') ? (int) env('REVENUE_HISTORY_DAYS') : null,
    'backfill_months_per_refresh' => 6,

    // Visits from these sources count as "AI assistants" whatever GA4's channel group says.
    'ai_sources' => ['chatgpt.com', 'chat.openai.com', 'openai.com', 'perplexity', 'gemini.google.com', 'bard.google.com', 'copilot.microsoft.com', 'claude.ai', 'you.com', 'meta.ai', 'deepseek', 'grok.com'],
    // Visits from these sources count as "Affiliate" (CJ and Awin tracking domains).
    'affiliate_sources' => ['awin', 'cj.com', 'anrdoezrs', 'dpbolvw', 'jdoqocy', 'kqzyfj', 'tkqlhce', 'emjcd', 'qksrv', 'commission junction', 'shareasale'],

    // Our channels, in display order.
    'channels' => [
        'organic_search' => 'Organic search',
        'paid_search' => 'Paid search',
        'shopping' => 'Shopping',
        'paid_social' => 'Paid social',
        'organic_social' => 'Organic social',
        'email' => 'Email & SMS',
        'affiliate' => 'Affiliate',
        'ai' => 'AI assistants',
        'referral' => 'Referral',
        'direct' => 'Direct',
        'paid_other' => 'Other paid',
        'other' => 'Other',
    ],
];
