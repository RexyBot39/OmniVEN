<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Platforms a brand doesn't need (e.g. no TikTok Ads for Lens Replacement), so Coverage only counts real gaps.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_platform_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['brand_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_platform_skips');
    }
};
