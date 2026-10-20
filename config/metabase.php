<?php

// Metabase: browse collections, view saved questions and dashboards as OmniVEN charts, and write new queries.
// Uses a Metabase API key (Admin settings → Authentication → API keys), kept on the server. Hidden until the key
// is set. What the key's group can see in Metabase is what OmniVEN can see.
return [
    'url' => rtrim((string) env('METABASE_URL', 'https://metrics.39dg.net'), '/'),
    'api_key' => env('METABASE_API_KEY'),
    'timeout' => (int) env('METABASE_TIMEOUT', 60),
    // Rows shown on a page (Metabase returns up to 2,000 per query).
    'show_rows' => 500,
    // Collection lists, search results and table lists are kept this many minutes. Query results are never kept.
    'list_minutes' => 10,
    // Cards run on one dashboard page.
    'dashboard_cards' => 24,
];
