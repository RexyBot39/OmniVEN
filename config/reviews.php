<?php

// Review dashboard. Shopper Approved and Trustpilot update by themselves once their keys are set;
// the others are updated by hand from their sites. Low reviews from the automatic ones become
// Hotlist tasks for the review owner.
return [
    'platforms' => [
        'shopperapproved' => ['label' => 'Shopper Approved', 'brand' => '39dg', 'auto' => true, 'url' => 'https://www.shopperapproved.com/account/dashboard'],
        'trustpilot' => ['label' => 'Trustpilot', 'brand' => '39dg', 'auto' => true, 'url' => 'https://www.trustpilot.com/review/www.39dollarglasses.com'],
        'google' => ['label' => 'Google', 'brand' => '39dg', 'auto' => false, 'url' => 'https://business.google.com'],
        'consumeraffairs' => ['label' => 'ConsumerAffairs', 'brand' => '39dg', 'auto' => false, 'url' => 'https://www.consumeraffairs.com/eyeglasses/39dollarglasses.html'],
        'bbb' => ['label' => 'BBB', 'brand' => '39dg', 'auto' => false, 'url' => 'https://www.bbb.org'],
    ],

    'shopperapproved' => [
        'site_id' => env('SHOPPERAPPROVED_SITE_ID'),
        'token' => env('SHOPPERAPPROVED_API_TOKEN'),
        'url' => 'https://api.shopperapproved.com',
    ],
    'trustpilot' => [
        'api_key' => env('TRUSTPILOT_API_KEY'),
        'business_unit_id' => env('TRUSTPILOT_BUSINESS_UNIT_ID'),
        'url' => 'https://api.trustpilot.com/v1',
    ],

    // Who gets a Hotlist task for each new review at or below this many stars.
    'owner_email' => env('OMNIVEN_REVIEW_OWNER', 'leona.p@39dollarglasses.com'),
    'task_max_stars' => 2,
    // Only reviews this recent become tasks (so the first sync doesn't flood the Hotlist).
    'task_max_age_days' => 7,
    'refresh_minutes' => 60,
    'timeout' => 20,
];
