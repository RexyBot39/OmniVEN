<?php

return [
    // Only Google Workspace accounts on this domain can sign in.
    'allowed_domain' => strtolower(env('OMNIVEN_ALLOWED_DOMAIN', '39dollarglasses.com')),

    // Comma-separated emails that are always admins (can edit tools, brands, routines, people).
    'admin_emails' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('OMNIVEN_ADMIN_EMAILS', 'drmarcweinstein@39dollarglasses.com'))
    ))),

    // Only these people can open the Activity report (not tied to admin status).
    'activity_viewers' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('OMNIVEN_ACTIVITY_VIEWERS', 'drmarcweinstein@39dollarglasses.com,drmarlow@39dollarglasses.com'))
    ))),

    // Everyone is signed out this many hours after signing in, whatever their activity.
    'session_hours' => (int) env('OMNIVEN_SESSION_HOURS', 16),

    'postit_colors' => [
        'yellow' => 'Yellow',
        'pink' => 'Pink',
        'blue' => 'Blue',
        'green' => 'Green',
        'orange' => 'Orange',
    ],

    // Platforms an Account can be on. 'grid' = shown as a column in the brand × platform grid.
    // 'home' = generic sign-in page, used when an account has no direct link yet.
    'platform_groups' => [
        'social' => 'Social',
        'ads' => 'Advertising',
        'commerce' => 'Shopping & Feeds',
        'search' => 'Search & Analytics',
        'email' => 'Email & SMS',
        'reviews' => 'Reviews',
        'other' => 'Other',
    ],
    'platforms' => [
        'instagram' => ['label' => 'Instagram', 'short' => 'IG', 'group' => 'social', 'grid' => true, 'home' => 'https://www.instagram.com'],
        'facebook' => ['label' => 'Facebook', 'short' => 'FB', 'group' => 'social', 'grid' => true, 'home' => 'https://www.facebook.com'],
        'tiktok' => ['label' => 'TikTok', 'short' => 'TT', 'group' => 'social', 'grid' => true, 'home' => 'https://www.tiktok.com'],
        'youtube' => ['label' => 'YouTube', 'short' => 'YT', 'group' => 'social', 'grid' => false, 'home' => 'https://studio.youtube.com'],
        'pinterest' => ['label' => 'Pinterest', 'short' => 'PI', 'group' => 'social', 'grid' => false, 'home' => 'https://www.pinterest.com'],
        'x' => ['label' => 'X (Twitter)', 'short' => 'X', 'group' => 'social', 'grid' => false, 'home' => 'https://x.com'],
        'linkedin' => ['label' => 'LinkedIn', 'short' => 'LI', 'group' => 'social', 'grid' => true, 'home' => 'https://www.linkedin.com'],
        'reddit' => ['label' => 'Reddit', 'short' => 'RD', 'group' => 'social', 'grid' => false, 'home' => 'https://www.reddit.com'],
        'meta_ads' => ['label' => 'Meta Ads', 'short' => 'MA', 'group' => 'ads', 'grid' => true, 'home' => 'https://adsmanager.facebook.com'],
        'tiktok_ads' => ['label' => 'TikTok Ads', 'short' => 'TA', 'group' => 'ads', 'grid' => false, 'home' => 'https://ads.tiktok.com'],
        'google_ads' => ['label' => 'Google Ads', 'short' => 'GA', 'group' => 'ads', 'grid' => true, 'home' => 'https://ads.google.com'],
        'microsoft_ads' => ['label' => 'Microsoft Ads', 'short' => 'MS', 'group' => 'ads', 'grid' => false, 'home' => 'https://ads.microsoft.com'],
        'merchant_center' => ['label' => 'Merchant Center', 'short' => 'MC', 'group' => 'commerce', 'grid' => true, 'home' => 'https://merchants.google.com'],
        'meta_commerce' => ['label' => 'Meta Commerce', 'short' => 'CM', 'group' => 'commerce', 'grid' => false, 'home' => 'https://business.facebook.com/commerce'],
        'shopify' => ['label' => 'Shopify', 'short' => 'SH', 'group' => 'commerce', 'grid' => false, 'home' => 'https://admin.shopify.com'],
        'ga4' => ['label' => 'Google Analytics', 'short' => 'G4', 'group' => 'search', 'grid' => true, 'home' => 'https://analytics.google.com'],
        'search_console' => ['label' => 'Search Console', 'short' => 'SC', 'group' => 'search', 'grid' => true, 'home' => 'https://search.google.com/search-console'],
        'gtm' => ['label' => 'Tag Manager', 'short' => 'TM', 'group' => 'search', 'grid' => false, 'home' => 'https://tagmanager.google.com'],
        'bing_webmaster' => ['label' => 'Bing Webmaster', 'short' => 'BW', 'group' => 'search', 'grid' => false, 'home' => 'https://www.bing.com/webmasters'],
        'clarity' => ['label' => 'Clarity', 'short' => 'CL', 'group' => 'search', 'grid' => false, 'home' => 'https://clarity.microsoft.com'],
        'klaviyo' => ['label' => 'Klaviyo', 'short' => 'KL', 'group' => 'email', 'grid' => true, 'home' => 'https://www.klaviyo.com/login'],
        'google_business' => ['label' => 'Google Business Profile', 'short' => 'GB', 'group' => 'reviews', 'grid' => false, 'home' => 'https://business.google.com'],
        'trustpilot' => ['label' => 'Trustpilot', 'short' => 'TP', 'group' => 'reviews', 'grid' => false, 'home' => 'https://business.trustpilot.com'],
        'other' => ['label' => 'Other', 'short' => '··', 'group' => 'other', 'grid' => false, 'home' => null],
    ],

    'categories' => [
        'Advertising',
        'Shopping & Feeds',
        'Search & Analytics',
        'Email & SMS',
        'Social & Community',
        'Reviews & Reputation',
        'Listening & Research',
        'Creative & Content',
        'AI & Internal Tools',
    ],

    'statuses' => [
        'active' => 'Active',
        'verify' => 'Verify access',
        'building' => 'In development',
    ],

    'cadences' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
    ],

    // Slack channel (name or id) that gets a short "X requested access to Y" note. Empty = no Slack post.
    'access_slack_channel' => env('OMNIVEN_ACCESS_SLACK_CHANNEL', ''),

    // Brand accounts and tools not verified for this many days are flagged on the Access review.
    'verify_days' => 90,
];
