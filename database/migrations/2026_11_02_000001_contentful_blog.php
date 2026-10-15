<?php

use Database\Seeders\ContentfulSeeder;
use Illuminate\Database\Migrations\Migration;

// Blogs for 39DollarGlasses and Ocusafe run in Contentful: tool, brand accounts and a weekly routine.
return new class extends Migration
{
    public function up(): void
    {
        (new ContentfulSeeder)->run();
    }

    public function down(): void
    {
        // Leave the data in place; admins can edit it in the app.
    }
};
