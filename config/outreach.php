<?php

// Publisher outreach: kinds of publisher, the stages a pitch moves through, and the follow-up rhythm.
return [
    'types' => [
        'blog' => 'Blog or guide site',
        'review' => 'Review or comparison site',
        'affiliate' => 'Affiliate publisher',
        'creator' => 'Creator or influencer',
        'press' => 'Press or journalist',
        'podcast' => 'Podcast',
    ],
    // Open stages first, in order; then the closed ones.
    'stages' => [
        'idea' => 'To contact',
        'contacted' => 'Contacted',
        'replied' => 'Replied',
        'negotiating' => 'Working it out',
        'live' => 'Live',
        'declined' => 'Declined',
        'no_reply' => 'No reply',
    ],
    'closed' => ['live', 'declined', 'no_reply'],
    // Days until the next nudge after a pitch, and after a reply.
    'follow_up_days' => 7,
    'reply_follow_up_days' => 3,
    // After this many pitches with no answer, suggest closing it as "No reply".
    'max_nudges' => 3,
    'networks' => ['cj' => 'CJ', 'awin' => 'Awin'],
];
