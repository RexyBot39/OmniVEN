<?php

use Database\Seeders\BrandLinkedInSeeder;
use Illuminate\Database\Migrations\Migration;

// The brands' LinkedIn pages: accounts, Social links, channels, a LinkedIn tool and the DM routine.
// On a brand-new database the brands don't exist yet, so the starter seed runs the same seeder.
return new class extends Migration
{
    public function up(): void
    {
        (new BrandLinkedInSeeder)->run();
    }

    public function down(): void
    {
        // Leave the data in place; admins can edit or delete it in the app.
    }
};
