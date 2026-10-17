<?php

// Email & SMS health from Klaviyo, read-only. Needs a private API key with read access to campaigns,
// flows, lists and metrics. Defaults point at our account's order event and main list.
return [
    'key' => env('KLAVIYO_PRIVATE_KEY'),
    'url' => 'https://a.klaviyo.com/api',
    'revision' => '2024-10-15',
    // The event Klaviyo credits orders to. "39dg production: Item Ordered" is the one the site sends;
    // "Placed Order" has had no events since at least June 2026.
    'order_metric_id' => env('KLAVIYO_ORDER_METRIC_ID', 'RsvUas'),
    'main_list_id' => env('KLAVIYO_MAIN_LIST_ID', 'TEKDQ9'),
    // Campaigns sent to fewer people than this are tests and are left out.
    'min_recipients' => 500,
    'refresh_minutes' => 120,
    'timeout' => 30,
    // Warn when a 30-day rate passes these.
    'warn' => ['unsubscribe_rate' => 0.005, 'spam_rate' => 0.0008, 'delivery_rate' => 0.98],
];
