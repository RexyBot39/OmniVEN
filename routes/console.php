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
// one more month of history; with --history it fills in all of it (about 13 months) in one go.
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

// Pull affiliate totals from CJ and Awin. --history fills in the last 13 months now.
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
