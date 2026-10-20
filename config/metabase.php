<?php

// Metabase: browse collections, view saved questions and dashboards as OmniVEN charts, and write new queries.
// Uses a Metabase API key (Admin settings → Authentication → API keys), kept on the server. Hidden until the key
// is set. What the key's group can see in Metabase is what OmniVEN can see.
return [
    'url' => rtrim((string) env('METABASE_URL', 'https://metrics.39dg.net'), '/'),
    'api_key' => env('METABASE_API_KEY'),
    'timeout' => (int) env('METABASE_TIMEOUT', 60),
    // Protections against pulling the database out through OmniVEN:
    // - each query fetches at most this many rows (the rest never leaves Metabase),
    'max_rows' => (int) env('METABASE_MAX_ROWS', 200),
    // - each person gets this many query runs (questions, dashboard cards, new queries) and rows a day,
    'daily_queries' => (int) env('METABASE_DAILY_QUERIES', 150),
    'daily_rows' => (int) env('METABASE_DAILY_ROWS', 5000),
    // - writing SQL is for admins only ("admins"), or off for everyone ("off"); the table builder always aggregates,
    'sql' => env('METABASE_SQL', 'admins'),
    // - new queries can be switched off entirely (saved questions and dashboards still show),
    'queries' => (bool) env('METABASE_QUERIES', true),
    // - columns that look like personal details are masked in every result and can't be grouped by in the builder.
    'sensitive_types' => ['type/Email', 'type/Address', 'type/ZipCode', 'type/Latitude', 'type/Longitude'],
    'sensitive_names' => '/(e_?mail|phone|mobile|\\bcell|fax|address|street|addr_?\\d|postal|zip|first_?name|last_?name|full_?name|surname|customer_?name|contact_?name|shipping_?(name|addr)|recipient|\\bssn\\b|social_?sec|birth|\\bdob\\b|password|passwd|pass_?hash|token|secret|api_?key|card_?(number|num|no)|\\bcc_|cvv|iban|account_?number|routing|ip_?addr|user_?agent|\\blat(itude)?\\b|\\blng\\b|\\blon(gitude)?\\b|license|passport)/i',
    // Numbers are only masked when they look like phone, card or account numbers, so totals stay visible.
    'sensitive_numbers' => '/(phone|mobile|fax|ssn|social_?sec|card|cvv|iban|account_?number|routing|zip|postal|\\blat|\\blng|\\blon)/i',
    // Collection lists, search results and table lists are kept this many minutes. Query results are never kept.
    'list_minutes' => 10,
    // Cards run on one dashboard page.
    'dashboard_cards' => 24,
];
