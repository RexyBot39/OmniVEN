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
