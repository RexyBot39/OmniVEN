<?php

return [
    // Only Google Workspace accounts on this domain can sign in.
    'allowed_domain' => strtolower(env('OMNIVEN_ALLOWED_DOMAIN', '39dollarglasses.com')),

    // Comma-separated emails that are always admins (can edit tools, brands, routines, people).
    'admin_emails' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('OMNIVEN_ADMIN_EMAILS', 'drmarcweinstein@39dollarglasses.com'))
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
    ],
];
