<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nightly broken-link check: the latest result for every link OmniVEN shows.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_checks', function (Blueprint $table) {
            $table->id();
            $table->string('item_type', 20); // tool, account, brand or routine
            $table->unsignedBigInteger('item_id');
            $table->string('url', 1024);
            $table->string('status', 12)->default('pending'); // ok, failing, blocked, pending
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->string('error', 200)->nullable();
            $table->unsignedSmallInteger('fail_count')->default(0); // nights in a row it failed
            $table->timestamp('first_failed_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->index(['item_type', 'item_id']);
            $table->index(['status', 'fail_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_checks');
    }
};
