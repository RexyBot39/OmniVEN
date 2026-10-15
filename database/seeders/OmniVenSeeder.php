<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Note;
use App\Models\Routine;
use App\Models\Tool;
use Illuminate\Database\Seeder;

/**
 * Starter data for OmniVEN. Safe to run more than once: rows are matched by
 * slug / name / title and only created when missing, so edits made in the app are kept.
 */
class OmniVenSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['39dg', '39DollarGlasses', 'https://www.39dollarglasses.com', 'Value prescription eyewear sold online. In business 26 years, based in Hauppauge, NY.', 'Email (Klaviyo), Google Shopping, SEO, Instagram, Facebook, TikTok, Reddit'],
            ['ocusafe', 'Ocusafe', 'https://www.ocusafe.com', 'Prescription safety eyewear.', 'Instagram, Facebook, TikTok'],
            ['neurolux', 'Neurolux', 'https://www.39dollarglasses.com/lens/neurolux', 'FL-41 tinted lenses for light sensitivity and migraine.', 'Instagram, Facebook, TikTok'],
            ['ocusleep', 'Ocusleep', 'https://www.ocusleep.com', null, null],
            ['onlinecontacts', 'Online Contacts', null, 'Contact lenses.', null],
            ['lumeo', 'Lumeo', null, 'Lumeo™ lens line sold on 39DollarGlasses.', 'SEO / AEO product page'],
            ['lensreplacement', 'Lens Replacement', 'https://www.39dollarglasses.com/help/re-lens/your-old-frame', "Re-lens service: new prescription lenses fitted into the customer's own frames.", null],
            ['trailview', 'Trailview', null, null, null],
        ];
        foreach ($brands as $i => [$slug, $name, $site, $focus, $channels]) {
            Brand::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'site' => $site, 'focus' => $focus, 'channels' => $channels, 'socials' => [], 'sort' => $i + 1,
            ]);
        }

        $social = ['39dg', 'ocusafe', 'neurolux'];
        // [name, url, category, description, brands, status, extra]
        $tools = [
            ['Meta Business Suite', 'https://business.facebook.com', 'Advertising', 'Facebook and Instagram pages, inbox, ad accounts, pixels and user permissions.', ['all'], 'active', ['featured' => true, 'sort' => 1, 'owner' => 'Marc', 'access_note' => 'Ask the owner to add you under Business Settings > People.']],
            ['Meta Ads Manager', 'https://adsmanager.facebook.com', 'Advertising', 'Build, run and report on Facebook and Instagram ad campaigns.', ['all'], 'verify', ['sort' => 2]],
            ['Meta Events Manager', 'https://business.facebook.com/events_manager2', 'Advertising', 'Meta pixel and Conversions API health, event matching and diagnostics.', ['all'], 'verify', ['sort' => 3]],
            ['TikTok Business Center', 'https://business.tiktok.com', 'Advertising', 'TikTok business accounts, ad accounts, pixels, catalogs and member access.', ['all'], 'active', ['featured' => true, 'sort' => 4, 'owner' => 'Marc', 'access_note' => 'Ask the owner to invite you as a member of the Business Center.']],
            ['TikTok Ads Manager', 'https://ads.tiktok.com', 'Advertising', 'Create and report on TikTok ad campaigns.', ['all'], 'verify', ['sort' => 5]],
            ['Google Ads', 'https://ads.google.com', 'Advertising', 'Search, Shopping and Performance Max campaigns.', ['all'], 'verify', ['sort' => 6]],
            ['Microsoft Advertising', 'https://ads.microsoft.com', 'Advertising', 'Bing search and shopping ads.', ['all'], 'verify', ['sort' => 7]],
            ['Reddit Ads', 'https://ads.reddit.com', 'Advertising', 'Promoted posts and conversation ads on Reddit.', ['all'], 'verify', ['sort' => 8]],

            ['Google Merchant Center', 'https://merchants.google.com', 'Shopping & Feeds', 'Product feed, Shopping listings, disapprovals and free listings.', ['all'], 'active', ['featured' => true, 'sort' => 1, 'owner' => 'Marc', 'access_note' => 'Ask the owner to add you under Settings > People and access.']],
            ['Meta Commerce Manager', 'https://business.facebook.com/commerce', 'Shopping & Feeds', 'Product catalogs for Facebook and Instagram shopping and dynamic ads.', ['all'], 'verify', ['sort' => 2]],
            ['Shopify Admin', 'https://admin.shopify.com', 'Shopping & Feeds', 'Store admin for products, discounts, apps and orders.', ['39dg', 'ocusleep'], 'active', ['sort' => 3]],

            ['Google Search Console', 'https://search.google.com/search-console', 'Search & Analytics', 'Indexing, search queries, Core Web Vitals and site errors.', ['all'], 'active', ['featured' => true, 'sort' => 1, 'owner' => 'Marc', 'access_note' => 'Each domain is its own property. Ask the owner to add you per property.']],
            ['Google Analytics 4', 'https://analytics.google.com', 'Search & Analytics', 'Traffic, conversions and funnels for each site.', ['all'], 'active', ['sort' => 2]],
            ['Google Tag Manager', 'https://tagmanager.google.com', 'Search & Analytics', 'Tags and pixels deployed on the sites.', ['all'], 'verify', ['sort' => 3]],
            ['Microsoft Clarity', 'https://clarity.microsoft.com', 'Search & Analytics', 'Session recordings and heatmaps. Used for cart and checkout funnel investigation.', ['39dg'], 'active', ['sort' => 4]],
            ['Bing Webmaster Tools', 'https://www.bing.com/webmasters', 'Search & Analytics', 'Bing indexing and search performance. Also feeds Copilot answers.', ['all'], 'verify', ['sort' => 5]],

            ['Klaviyo', 'https://www.klaviyo.com/login', 'Email & SMS', 'Email and SMS campaigns, flows, sign-up forms and segments.', ['39dg'], 'active', ['sort' => 1, 'access_note' => '39DollarGlasses account ID: WQR2ZT.']],

            ['Blotato', 'https://my.blotato.com', 'Social & Community', 'Schedule and publish posts across connected social accounts; post analytics.', $social, 'active', ['sort' => 1, 'login' => 'marketing@39dollarglasses.com']],
            ['Instagram', 'https://www.instagram.com', 'Social & Community', 'Brand profiles. Handles are listed on each brand card.', $social, 'active', ['sort' => 2]],
            ['Facebook', 'https://www.facebook.com', 'Social & Community', 'Brand pages. Manage posts and inbox from Meta Business Suite.', $social, 'active', ['sort' => 3]],
            ['TikTok', 'https://www.tiktok.com', 'Social & Community', 'Brand profiles for organic video.', $social, 'active', ['sort' => 4]],
            ['Reddit', 'https://www.reddit.com', 'Social & Community', 'Community replies in r/glasses, r/frugal, r/optometry and others. Blotato does not post to Reddit.', ['39dg'], 'active', ['sort' => 5]],

            ['Trustpilot Business', 'https://business.trustpilot.com', 'Reviews & Reputation', 'Reply to and flag Trustpilot reviews.', ['39dg'], 'active', ['sort' => 1, 'access_note' => 'Public page: trustpilot.com/review/www.39dollarglasses.com']],
            ['Shopper Approved', 'https://www.shopperapproved.com/account/dashboard', 'Reviews & Reputation', 'Our main third-party review platform: post-purchase site and product reviews, and the seller ratings feed for Google.', ['39dg'], 'active', ['sort' => 0, 'featured' => true]],
            ['ConsumerAffairs', 'https://www.consumeraffairs.com/eyeglasses/39dollarglasses.html', 'Reviews & Reputation', 'Brand review page. Watch for restocking-fee and defect disputes.', ['39dg'], 'active', ['sort' => 3]],
            ['Better Business Bureau', 'https://www.bbb.org', 'Reviews & Reputation', 'BBB complaints and business profile. Any BBB mention is an escalation.', ['39dg'], 'verify', ['sort' => 4]],
            ['Google Business Profile', 'https://business.google.com', 'Reviews & Reputation', 'Google Maps listing, Google reviews and Q&A.', ['39dg'], 'verify', ['sort' => 5]],

            ['F5Bot', 'https://f5bot.com', 'Listening & Research', 'Keyword alerts for Reddit and Hacker News mentions.', ['39dg'], 'active', ['sort' => 1, 'login' => 'ballcomp1@gmail.com']],
            ['Slack #reddit-monitor', 'https://app.slack.com', 'Listening & Research', 'Scored Reddit threads posted automatically at 9am and 2pm ET on weekdays.', ['39dg'], 'active', ['sort' => 2]],
            ['Feedly', 'https://feedly.com', 'Listening & Research', 'RSS feeds from professional optical and eye-health publications.', ['all'], 'active', ['sort' => 3]],
            ['NewsBlur', 'https://newsblur.com', 'Listening & Research', 'Lower-cost RSS reader for feed ingestion.', ['all'], 'active', ['sort' => 4]],
            ['PulseToPost', 'https://pulsetopost.com', 'Listening & Research', 'Content intelligence: daily research scan and channel-specific drafts for human review. Nothing publishes automatically.', ['39dg', 'neurolux', 'ocusafe', 'ocusleep'], 'building', ['sort' => 5]],

            ['Figma', 'https://www.figma.com', 'Creative & Content', 'Design files, mockups and social graphics.', ['all'], 'active', ['sort' => 1]],
            ['Google Drive', 'https://drive.google.com', 'Creative & Content', 'Shared marketing files, brand assets and content calendars.', ['all'], 'active', ['sort' => 2]],
            ['Marketing inbox (Gmail)', 'https://mail.google.com', 'Creative & Content', 'Shared marketing mailbox used for tool sign-ups and billing.', ['all'], 'active', ['sort' => 3, 'login' => 'marketing@39dollarglasses.com']],

            ['Claude', 'https://claude.ai', 'AI & Internal Tools', 'Drafting, research, content calendars, review sentiment and Reddit reports.', ['all'], 'active', ['sort' => 1]],
            ['Sloan AI', 'https://sloanai.cc', 'AI & Internal Tools', 'Internal AI advisor. Ask it product, lens and pricing questions.', ['all'], 'active', ['sort' => 2]],
            ['Noticeify', 'https://noticeify-w39k.vercel.app', 'AI & Internal Tools', 'Cookie consent and pixel blocking for the Shopify stores.', ['39dg', 'ocusleep'], 'building', ['sort' => 3]],
        ];
        foreach ($tools as [$name, $url, $cat, $desc, $brandSlugs, $status, $extra]) {
            $tool = Tool::firstOrNew(['name' => $name]);
            if ($tool->exists) {
                continue;
            }
            $tool->fill(['url' => $url, 'category' => $cat, 'description' => $desc, 'status' => $status, 'all_brands' => in_array('all', $brandSlugs, true)] + $extra)->save();
            if (! $tool->all_brands) {
                $tool->brands()->sync(Brand::whereIn('slug', $brandSlugs)->pluck('id'));
            }
        }

        $routines = [
            ['Work the Reddit report', 'daily', 'Read the top-scored threads in #reddit-monitor and post helpful, non-salesy replies to the best 1–2.', 'https://app.slack.com', ['39dg']],
            ['Check new reviews and escalate', 'daily', 'Shopper Approved first (our main review platform), then Trustpilot and ConsumerAffairs. Escalate any chargeback, BBB, legal or prescription-error mention the same day.', 'https://www.shopperapproved.com/account/dashboard', ['39dg']],
            ['Answer comments and DMs', 'daily', 'Instagram, Facebook and TikTok inboxes for every brand.', 'https://business.facebook.com', $social],
            ['Fix Merchant Center disapprovals', 'weekly', 'Review product diagnostics and resolve disapproved or limited items.', 'https://merchants.google.com', ['all']],
            ['Search Console check', 'weekly', 'Indexing errors, top query movement and new pages to request indexing.', 'https://search.google.com/search-console', ['all']],
            ['Review email performance', 'weekly', 'Campaign and flow revenue, open and click rates, list growth.', 'https://www.klaviyo.com/login', ['39dg']],
            ['Review ad spend and results', 'weekly', 'Meta and TikTok spend, ROAS and creative fatigue.', 'https://adsmanager.facebook.com', ['all']],
            ["Plan next week's social calendar", 'weekly', 'Draft and schedule posts for each brand.', 'https://my.blotato.com', $social],
            ['Watch checkout recordings', 'monthly', 'Look for cart and checkout friction; post findings to the bulletin.', 'https://clarity.microsoft.com', ['39dg']],
            ['Audit pixels and consent', 'monthly', 'Meta Events Manager, TikTok pixel and GTM: confirm events fire and consent blocking works.', 'https://business.facebook.com/events_manager2', ['all']],
            ['Review access and owners', 'monthly', 'Update the Access tab and turn off OmniVEN access for anyone who has left.', null, ['all']],
        ];
        foreach ($routines as $i => [$title, $cadence, $desc, $link, $brandSlugs]) {
            $r = Routine::firstOrNew(['title' => $title]);
            if ($r->exists) {
                continue;
            }
            $r->fill(['cadence' => $cadence, 'description' => $desc, 'link' => $link, 'all_brands' => in_array('all', $brandSlugs, true), 'sort' => $i + 1])->save();
            if (! $r->all_brands) {
                $r->brands()->sync(Brand::whereIn('slug', $brandSlugs)->pluck('id'));
            }
        }

        Note::firstOrCreate(['title' => 'Welcome to OmniVEN'], [
            'body' => 'This is the one place for every marketing tool, account owner and recurring task across our brands. Filter by brand with the chips, tick routines as you finish them, and post updates here on the bulletin.',
            'pinned' => true,
        ]);
        Note::firstOrCreate(['title' => 'First week: confirm logins'], [
            'body' => "Tools marked 'Verify access' were added as likely accounts. Open each one, confirm we have it and who owns it, and send the details to an admin so the card can be set to Active with its login account and owner.",
        ]);

        $this->call([BrandDetailsSeeder::class, BrandSocialsSeeder::class, BrandLinkedInSeeder::class, ShopperApprovedSeeder::class, KlaviyoSeeder::class, AffiliateSeeder::class]);
    }
}
