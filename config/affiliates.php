<?php

// Affiliate scoreboard: read-only reporting from CJ and Awin. Only monthly totals per publisher
// are kept (orders, sales, commission); nothing about individual customers or orders.
return [
    'cj' => [
        // A CJ personal access token (developers.cj.com → Personal Access Tokens) and our CID.
        'token' => env('CJ_API_TOKEN'),
        'advertiser_id' => env('CJ_ADVERTISER_ID'),
        'url' => 'https://commissions.api.cj.com/query',
    ],
    'awin' => [
        // An Awin API token (Awin → API credentials) and our advertiser ID.
        'token' => env('AWIN_API_TOKEN'),
        'advertiser_id' => env('AWIN_ADVERTISER_ID', '95501'),
        'region' => env('AWIN_REGION', 'US'),
        'url' => 'https://api.awin.com',
    ],
    'timeout' => 25,
    'refresh_minutes' => 60,
    // How many months of history to fill in, one month per refresh.
    'history_months' => 13,
];
