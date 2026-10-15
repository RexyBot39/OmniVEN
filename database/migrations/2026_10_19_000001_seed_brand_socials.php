<?php

use Database\Seeders\BrandSocialsSeeder;
use Illuminate\Database\Migrations\Migration;

// Adds the public social profiles from each brand site's footer as brand accounts.
// Skips any brand + platform that already has an account. On a brand-new database the
// brands don't exist yet, so the starter seed runs the same seeder afterwards.
return new class extends Migration
{
    public function up(): void
    {
        (new BrandSocialsSeeder)->run();
    }

    public function down(): void
    {
        // Leave the accounts in place; admins can edit or delete them in the app.
    }
};
