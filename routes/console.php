<?php

use App\Models\Brand;
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
