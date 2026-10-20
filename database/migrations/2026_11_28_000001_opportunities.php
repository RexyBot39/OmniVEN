<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ocusafe opportunities: public government bids and RFPs found through Tango, and the team's decision on each.
// Search phrases are kept here so admins can tune them without a deploy.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            // sam (SAM.gov), sled (state and local), dibbs_rfq or dibbs_rfp (DLA)
            $table->string('source', 12);
            $table->string('external_id', 120);
            $table->string('title', 500);
            $table->string('agency', 300)->nullable();
            $table->string('solicitation_number', 120)->nullable();
            $table->string('kind', 60)->nullable();
            $table->string('set_aside', 200)->nullable();
            $table->string('codes', 200)->nullable();
            $table->string('place', 200)->nullable();
            $table->string('summary', 1000)->nullable();
            $table->string('url', 1000)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('deadline_at')->nullable()->index();
            $table->boolean('open')->default(true);
            $table->smallInteger('score')->default(0);
            $table->string('matched', 300)->nullable();
            // new, pursuing, submitted, won, lost, passed
            $table->string('status', 12)->default('new');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'external_id']);
            $table->index(['status', 'score']);
        });

        Schema::create('opportunity_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 120)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_terms');
        Schema::dropIfExists('opportunities');
    }
};
