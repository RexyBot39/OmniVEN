<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pages whose structured data the Site check reads every day, and what it found last time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 1024);
            $table->string('kind', 16);
            $table->string('status', 16)->default('new');
            $table->json('issues')->nullable();
            $table->json('found')->nullable();
            $table->string('error', 300)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pages');
    }
};
