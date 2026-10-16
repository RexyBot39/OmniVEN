<?php

// PulseToPost (pulsetopost.com): read-only status for the dashboard card. The token stays on the
// server; it is only ever sent to PulseToPost. Leave the token empty to hide the card.
return [
    'base_url' => rtrim((string) env('PULSETOPOST_BASE_URL', 'https://pulsetopost.com'), '/'),
    'token' => env('PULSETOPOST_API_TOKEN'),
    // The pipeline runs once a day, so a five-minute cache is plenty.
    'cache_seconds' => (int) env('PULSETOPOST_CACHE_SECONDS', 300),
    // After a failed read, wait this long before trying again.
    'retry_seconds' => 60,
    'timeout' => 4,
    // The status line turns red when the last run is older than this.
    'stale_hours' => 26,
];
