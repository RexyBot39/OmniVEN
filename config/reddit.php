<?php

// Reddit opportunities: threads worth a reply, found with Reddit's read-only API (an "app-only" login:
// create a "script" app at reddit.com/prefs/apps and set REDDIT_CLIENT_ID and REDDIT_CLIENT_SECRET).
return [
    'client_id' => env('REDDIT_CLIENT_ID'),
    'client_secret' => env('REDDIT_CLIENT_SECRET'),
    // Reddit asks every app to name itself like this.
    'user_agent' => env('REDDIT_USER_AGENT', 'web:omniven:1.0 (by 39DollarGlasses marketing)'),

    // Searches across all of Reddit for our names: brand slug => search terms.
    'brands' => [
        '39dg' => ['39dollarglasses', '"39 dollar glasses"', '"39dollar glasses"'],
        'ocusafe' => ['ocusafe'],
        'ocusleep' => ['ocusleep'],
        'neurolux' => ['neurolux'],
    ],
    // Shoppers asking about these are open to an alternative.
    'competitors' => ['zenni', 'warby parker', 'warby', 'eyebuydirect', 'glassesusa', 'glasses usa', 'liingo', 'pair eyewear', 'costco optical', 'firmoo', 'goggles4u', 'payne glasses'],
    // Searched within the subreddits below.
    'topics' => ['cheap glasses online', 'prescription glasses online', 'progressive lenses online', 'zenni alternative', 'blue light glasses', 'prescription safety glasses', 'where to buy glasses', 'glasses recommendation', 'warby parker alternative'],
    'subreddits' => ['glasses', 'eyeglasses', 'optometry', 'Frugal', 'BuyItForLife', 'myopia', 'Astigmatism', 'Presbyopia', 'SafetyGlasses', 'Construction', 'nursing'],

    // Words that mean an unhappy customer.
    'complaint_words' => ['refund', 'scam', 'never arrived', 'never received', 'worst', 'terrible', 'awful', 'complaint', 'broke', 'broken', 'wrong prescription', 'customer service', 'avoid', 'disappointed', 'rip off', 'ripoff'],

    'max_age_days' => 7,
    // Threads scoring below this aren't shown.
    'min_score' => 30,
    'refresh_minutes' => 60,
    'timeout' => 20,
];
