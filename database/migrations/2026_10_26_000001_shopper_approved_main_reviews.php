<?php

use Database\Seeders\ShopperApprovedSeeder;
use Illuminate\Database\Migrations\Migration;

// Shopper Approved is the main review platform: dashboard link, Platform consoles, routine, account.
return new class extends Migration
{
    public function up(): void
    {
        (new ShopperApprovedSeeder)->run();
    }

    public function down(): void
    {
        // Leave the data in place; admins can edit it in the app.
    }
};
