<?php

use Database\Seeders\BrandDetailsSeeder;
use Database\Seeders\BrandSocialsSeeder;
use Illuminate\Database\Migrations\Migration;

// Lumeo and Lens Replacement live on 39dollarglasses.com; Trailview is trailview.co (a redirect
// into 39dollarglasses.com) with its own Instagram. Fills only empty fields and missing accounts.
return new class extends Migration
{
    public function up(): void
    {
        (new BrandDetailsSeeder)->run();
        (new BrandSocialsSeeder)->run();
    }

    public function down(): void
    {
        // Leave the details in place; admins can edit them in the app.
    }
};
