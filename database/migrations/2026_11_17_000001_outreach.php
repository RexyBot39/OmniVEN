<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Publisher outreach: the blogs, review sites, affiliates, creators and press we pitch, and each contact.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('site', 500)->nullable();
            $table->string('type', 20);
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 20)->default('idea');
            $table->string('contact', 200)->nullable();
            $table->string('network', 10)->nullable();
            $table->string('network_id', 64)->nullable();
            $table->date('last_contacted_on')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->string('result_url', 1024)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('live_at')->nullable();
            $table->timestamps();
            $table->index(['stage', 'follow_up_on']);
        });
        Schema::create('outreach_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('body', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_events');
        Schema::dropIfExists('publishers');
    }
};
