<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Review dashboard: rating snapshots per platform, and recent reviews from the platforms with an API.
// Reviews keep only the star rating, date, a short public excerpt and a link, not the reviewer.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32);
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->date('day');
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->boolean('manual')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['platform', 'brand_id', 'day']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32);
            $table->string('external_id', 100);
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->timestamp('reviewed_at');
            $table->string('excerpt', 300)->nullable();
            $table->string('url', 1024)->nullable();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['platform', 'external_id']);
            $table->index('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('review_snapshots');
    }
};
