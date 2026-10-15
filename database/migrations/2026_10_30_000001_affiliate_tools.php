<?php

use Database\Seeders\AffiliateSeeder;
use Illuminate\Database\Migrations\Migration;

// Affiliate program: CJ (Commission Junction) and Awin, under a new Affiliate tool category.
return new class extends Migration
{
    public function up(): void
    {
        (new AffiliateSeeder)->run();
    }

    public function down(): void
    {
        // Leave the data in place; admins can edit it in the app.
    }
};
