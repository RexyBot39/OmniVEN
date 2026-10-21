<?php

use App\Models\Brand;
use App\Support\LinkChecker;
use Database\Seeders\OmniVenSeeder;
use Illuminate\Support\Facades\Artisan;

// Loads the starter brands, tools and routines on a brand-new database only,
// so tools an admin deletes later don't come back on the next deploy.
Artisan::command('omniven:seed-if-empty', function () {
    if (Brand::query()->exists()) {
        $this->info('OmniVEN already has data. Skipping starter seed.');

        return;
    }
    $this->call('db:seed', ['--class' => OmniVenSeeder::class, '--force' => true]);
    $this->info('Loaded OmniVEN starter data.');
})->purpose('Seed starter data when the database is empty');

// Nightly broken-link check. Runs as a Railway cron service (see railway.cron.json and the README).
Artisan::command('omniven:check-links', function () {
    $result = LinkChecker::run();
    if ($result === null) {
        $this->warn('A link check is already running. Skipping.');

        return;
    }
    $this->info("Checked {$result['checked']} links: {$result['ok']} OK, {$result['failing']} broken, {$result['blocked']} couldn't check, {$result['newly_broken']} newly broken.");
})->purpose('Check every tool, account, brand and routine link');

// Pull daily sales totals from GA4. Without --history it refreshes the last few days and fills in
// a few more months of history; with --history it fills in all of it (back to the first month GA4 has) in one go.
Artisan::command('omniven:revenue-sync {--history : Fill in the full history now}', function () {
    if (! \App\Support\Revenue\Revenue::configured()) {
        $this->warn('GA4 isn’t set up: add GA4_CREDENTIALS and GA4_PROPERTIES.');

        return 1;
    }
    $ok = \App\Support\Revenue\Revenue::refresh((bool) $this->option('history'));
    $status = \App\Support\Revenue\Revenue::status();
    $ok ? $this->info('Revenue updated'.($status['history_from'] ? ', history from '.$status['history_from'] : '').'.') : $this->error((string) $status['error']);

    return $ok ? 0 : 1;
})->purpose('Refresh sales totals from Google Analytics 4');

// Pull email totals from Klaviyo: the 30-day report and daily history. --history loads all five years now.
Artisan::command('omniven:email-sync {--history : Fill in the full history now}', function () {
    if (! \App\Support\Email\Klaviyo::configured()) {
        $this->warn('Klaviyo isn’t set up: add KLAVIYO_PRIVATE_KEY.');

        return 1;
    }
    $ok = \App\Support\Email\Klaviyo::refresh();
    $ok = \App\Support\Email\EmailHistory::refresh((bool) $this->option('history')) && $ok;
    $h = \App\Support\Email\EmailHistory::status();
    $ok ? $this->info('Email updated'.($h['history_from'] ? ', history from '.$h['history_from'] : '').'.') : $this->error((string) (\App\Support\Email\Klaviyo::status()['error'] ?? $h['error']));

    return $ok ? 0 : 1;
})->purpose('Refresh email and SMS numbers from Klaviyo');

// Pull affiliate totals from CJ and Awin. --history fills in the whole history window now.
Artisan::command('omniven:affiliate-sync {--history : Fill in the full history now}', function () {
    if (! \App\Support\Affiliate\Affiliates::configured()) {
        $this->warn('Neither CJ nor Awin is set up: add CJ_API_TOKEN + CJ_ADVERTISER_ID and/or AWIN_API_TOKEN.');

        return 1;
    }
    $ok = \App\Support\Affiliate\Affiliates::refresh((bool) $this->option('history'));
    foreach (\App\Support\Affiliate\Affiliates::status() as $s) {
        if ($s['configured']) {
            $s['error'] ? $this->error($s['label'].': '.$s['error']) : $this->info($s['label'].': updated.');
        }
    }

    return $ok ? 0 : 1;
})->purpose('Refresh affiliate totals from CJ and Awin');

// The morning "my day" Slack DM for everyone who has it on (weekdays). Run by railway.myday.json.
Artisan::command('omniven:my-day {--force : Send even on a weekend}', function () {
    if (! \App\Support\Slack::configured()) {
        $this->warn('Slack isn’t set up (SLACK_BOT_TOKEN).');

        return 1;
    }
    $counts = \App\Support\MyDay::sendAll((bool) $this->option('force'));
    foreach ($counts as $what => $n) {
        $this->line("{$what}: {$n}");
    }

    return collect(array_keys($counts))->contains(fn ($k) => str_starts_with($k, 'error')) ? 1 : 0;
})->purpose('Send each person their morning summary in Slack');

// Daily structured data and product feed check (also runs by itself when the Site check page is opened).
Artisan::command('omniven:site-check', function () {
    \App\Support\Seo\SiteCheck::run();
    $this->info('Site check done.');
})->purpose('Check structured data on the listed pages and the product feeds');

// Reddit opportunities (also refreshed by itself when the Reddit page is opened).
Artisan::command('omniven:reddit-sync', function () {
    $ok = \App\Support\Reddit::refresh();
    $ok ? $this->info('Reddit threads updated.') : $this->error((string) \Illuminate\Support\Facades\Cache::get('reddit:error'));
})->purpose('Find Reddit threads worth a reply');

// The SEO suite: refresh any DataForSEO report that's due, collect and queue rank checks, and check site audits.
// Pages refresh themselves when opened; run this daily (e.g. after omniven:check-links) to keep them current anyway.
Artisan::command('omniven:seo-sync {--force : Refresh every report now, even if it is recent}', function () {
    if (! \App\Support\Seo\DataForSeo::configured()) {
        $this->warn('DataForSEO isn’t set up: add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD.');

        return 1;
    }
    $ok = true;
    foreach (\App\Support\Seo\SeoSuite::brands() as $brand) {
        foreach (\App\Support\Seo\SeoSuite::KINDS as $kind) {
            if ($kind === 'backlinks' && ! \App\Support\Seo\DataForSeo::backlinks()) {
                continue;
            }
            $r = \App\Support\Seo\SeoSuite::report($kind, $brand);
            if ($this->option('force') || ! $r['at'] || $r['at']->lt(now()->subDays((int) config("dataforseo.{$kind}_days")))) {
                $done = \App\Support\Seo\SeoSuite::refresh($kind, $brand);
                $ok = $ok && $done;
                $done ? $this->info("{$brand->name}: $kind updated.") : $this->error("{$brand->name}: $kind failed. ".\App\Support\Seo\SeoSuite::report($kind, $brand)['error']);
            }
        }
    }
    \App\Support\Seo\RankTracker::run();
    \App\Support\Seo\SiteAudit::run();
    if ($e = \App\Support\Seo\RankTracker::error()) {
        $this->error('Rank tracking: '.$e);
        $ok = false;
    }
    $this->info('DataForSEO spend this month: $'.number_format(\App\Support\Seo\DataForSeo::spentThisMonth(), 2).' of $'.number_format(\App\Support\Seo\DataForSeo::budget(), 2).'.');

    return $ok ? 0 : 1;
})->purpose('Refresh the SEO suite from DataForSEO');

// Ocusafe opportunities: search Tango for new government bids for safety glasses. The page also refreshes itself
// (every 12 hours when opened); run this daily (e.g. after omniven:check-links) so new bids arrive without a visit.
Artisan::command('omniven:opportunities-sync', function () {
    if (! \App\Support\Opportunities\Tango::configured()) {
        $this->warn('Tango isn’t set up: add TANGO_API_KEY.');

        return 1;
    }
    $r = \App\Support\Opportunities\OpportunityFinder::run();
    $this->info("Found {$r['found']} bids, {$r['new']} new.");
    foreach ($r['errors'] as $source => $error) {
        $this->error("$source: $error");
    }

    return $r['errors'] ? 1 : 0;
})->purpose('Search Tango for new government bids for safety glasses');

// Jira: check the columns of tickets sent from OmniVEN and tick off tasks whose ticket is Done. The board also
// does this by itself (every few minutes when opened); a cron can run it so tasks close without a visit.
Artisan::command('omniven:jira-sync', function () {
    if (! \App\Support\Jira\Jira::configured()) {
        $this->warn('Jira isn’t set up: add JIRA_BASE_URL, JIRA_EMAIL, JIRA_API_TOKEN and JIRA_PROJECT.');

        return 1;
    }
    $done = \App\Support\Jira\Jira::sync();
    $this->info("Jira checked; $done ".($done === 1 ? 'task' : 'tasks').' ticked off.');

    return 0;
})->purpose('Sync task status with the company Jira board');
