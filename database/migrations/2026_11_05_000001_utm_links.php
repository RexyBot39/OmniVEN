<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Campaign links made with the UTM builder, kept so the team reuses the same names.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utm_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('preset', 32)->nullable();
            $table->string('base_url', 1024);
            $table->string('source', 100);
            $table->string('medium', 100);
            $table->string('campaign', 150);
            $table->string('content', 150)->nullable();
            $table->string('term', 150)->nullable();
            $table->string('url', 2048);
            $table->timestamps();
            $table->index('campaign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utm_links');
    }
};
