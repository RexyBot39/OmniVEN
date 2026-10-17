<?php

// Campaign link presets. Each sets utm_source and utm_medium so GA4 files the visit under the
// channel shown on the Sales page. Google Ads and Microsoft Ads use auto-tagging instead.
return [
    'presets' => [
        'Organic social' => [
            'instagram' => ['Instagram (post, story, bio)', 'instagram', 'social', 'organic_social'],
            'facebook' => ['Facebook post', 'facebook', 'social', 'organic_social'],
            'tiktok' => ['TikTok', 'tiktok', 'social', 'organic_social'],
            'pinterest' => ['Pinterest pin', 'pinterest', 'social', 'organic_social'],
            'linkedin' => ['LinkedIn post', 'linkedin', 'social', 'organic_social'],
            'reddit' => ['Reddit post or comment', 'reddit', 'social', 'organic_social'],
            'youtube' => ['YouTube description', 'youtube', 'social', 'organic_social'],
        ],
        'Paid social' => [
            'meta_ads' => ['Meta ads (Facebook & Instagram)', 'facebook', 'paid_social', 'paid_social'],
            'tiktok_ads' => ['TikTok ads', 'tiktok', 'paid_social', 'paid_social'],
            'reddit_ads' => ['Reddit ads', 'reddit', 'paid_social', 'paid_social'],
            'pinterest_ads' => ['Pinterest ads', 'pinterest', 'paid_social', 'paid_social'],
        ],
        'Email & SMS' => [
            'email' => ['Klaviyo email', 'klaviyo', 'email', 'email'],
            'sms' => ['Klaviyo SMS', 'klaviyo', 'sms', 'email'],
        ],
        'Affiliate' => [
            'cj' => ['CJ (Commission Junction)', 'cj', 'affiliate', 'affiliate'],
            'awin' => ['Awin', 'awin', 'affiliate', 'affiliate'],
        ],
        'Other' => [
            'partner' => ['Partner, press or blog site', null, 'referral', 'referral'],
            'qr' => ['QR code or print', 'qr', 'print', 'other'],
            'custom' => ['Custom source and medium', null, null, null],
        ],
    ],
];
