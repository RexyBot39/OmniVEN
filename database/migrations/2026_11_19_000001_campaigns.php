<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Campaigns: a named push with dates. Its links are the campaign links with the same utm_campaign tag;
// its tasks are tasks (and launch-template runs) attached to it. They show on the Action Calendar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('tag', 150)->unique();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('goal', 300)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('template_runs', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('template_runs', fn (Blueprint $t) => $t->dropConstrainedForeignId('campaign_id'));
        Schema::table('tasks', fn (Blueprint $t) => $t->dropConstrainedForeignId('campaign_id'));
        Schema::dropIfExists('campaigns');
    }
};
