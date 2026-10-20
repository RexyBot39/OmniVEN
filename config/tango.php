<?php

// Ocusafe opportunities: government bids and RFPs for safety glasses, found once a day through Tango (tango.makegov.com),
// which gathers SAM.gov, state and local portals, and DLA's DIBBS. Get an API key at tango.makegov.com and set
// TANGO_API_KEY. Hidden until it's set (admins see setup steps).
return [
    'api_key' => env('TANGO_API_KEY'),
    'base' => rtrim((string) env('TANGO_BASE_URL', 'https://tango.makegov.com'), '/'),
    'timeout' => 45,

    // Which sources to search. State and local bids, and DIBBS, may need a paid Tango plan; a source the plan
    // doesn't include is skipped and admins are told why.
    'sources' => [
        'sam' => (bool) env('TANGO_SAM', true),
        'sled' => (bool) env('TANGO_SLED', true),
        'dibbs' => (bool) env('TANGO_DIBBS', true),
    ],

    // Starting search phrases (admins can add and remove them on the page). Each runs against every source.
    'terms' => ['safety glasses', 'safety eyewear', 'protective eyewear', 'prescription safety glasses', 'safety spectacles', 'Z87', 'eye protection'],

    // DIBBS lists items by their supply-catalog name ("SPECTACLES,INDUSTRIAL", "GOGGLES,INDUSTRIAL"), so it gets its own words.
    'dibbs_terms' => ['spectacles', 'goggles industrial', 'eyewear', 'eye protection'],

    // Federal codes that cover our products: PSC 6540 ophthalmic supplies, 4240 safety equipment;
    // NAICS 339115 ophthalmic goods manufacturing, 423460 ophthalmic goods wholesale.
    'psc' => ['6540', '4240'],
    'naics' => ['339115', '423460'],

    // Words that make a match likelier (in the title they count most) or unlikelier.
    'strong' => ['safety glass', 'safety eyewear', 'protective eyewear', 'prescription safety', 'z87', 'safety spectacle', 'eyewear', 'spectacles', 'eyeglass', 'goggle'],
    'weak' => ['night vision', 'nvg', 'thermal', 'laser', 'welding helmet', 'face shield only', 'binocular', 'windshield', 'glass replacement', 'glazing', 'window'],

    // Hits with at least this score are "good matches" (the default view).
    'good_score' => 3,
    // First run looks back this many days; later runs pick up where the last left off (with a little overlap).
    'lookback_days' => 30,
    // Refresh at most this often when someone opens the page (the daily command runs regardless).
    'refresh_hours' => 12,
    // Pages fetched per search (100 results each).
    'max_pages' => 3,

    // Optional Slack channel for the daily digest of new good matches (e.g. #ocusafe-bids).
    'slack_channel' => env('TANGO_SLACK_CHANNEL', ''),
];
