<?php

use Database\Seeders\KlaviyoSeeder;
use Illuminate\Database\Migrations\Migration;

// 39DollarGlasses' Klaviyo dashboard: tool, weekly email routine and the brand's Klaviyo account.
return new class extends Migration
{
    public function up(): void
    {
        (new KlaviyoSeeder)->run();
    }

    public function down(): void
    {
        // Leave the data in place; admins can edit it in the app.
    }
};
