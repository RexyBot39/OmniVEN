<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The whole public review text (still not the reviewer), so Ask OmniVEN AI can read reviews in depth.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('body')->nullable()->after('excerpt');
            $table->index(['platform', 'stars']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['platform', 'stars']);
            $table->dropColumn('body');
        });
    }
};
