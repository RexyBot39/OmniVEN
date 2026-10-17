<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Affiliate assets: approved banners, discount codes, brand copy and links for publishers. Uploaded image
// bytes live in their own table (the app has no persistent disk) so listing assets never loads them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10);
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('url', 1024)->nullable();
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('internal')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('file_name', 200)->nullable();
            $table->string('file_mime', 40)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('sha1', 40)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['brand_id', 'kind']);
        });
        Schema::create('affiliate_asset_files', function (Blueprint $table) {
            $table->foreignId('affiliate_asset_id')->primary()->constrained()->cascadeOnDelete();
            $table->longText('data'); // base64: stores the same way on Postgres and SQLite
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_asset_files');
        Schema::dropIfExists('affiliate_assets');
    }
};
