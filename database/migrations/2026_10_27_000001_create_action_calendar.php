<?php

use Database\Seeders\ActionCalendarSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The weekly action calendar (moved in from the "Q4 Action Calendar" artifact): weeks with their
// phase and seasonal angle, the recurring runbook plus one-off items, shared checkmarks, the boost
// log and the Sunday-review note.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_weeks', function (Blueprint $table) {
            $table->id();
            $table->date('starts_on')->unique(); // Monday
            $table->unsignedSmallInteger('number');
            $table->string('range_label', 40);
            $table->string('phase_label', 120)->nullable();
            $table->text('phase_text')->nullable();
            $table->text('season_text')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('notes_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('calendar_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_week_id')->nullable()->constrained()->cascadeOnDelete(); // null = every week
            $table->string('key', 40);
            $table->string('day', 10); // Setup, Review, Mon … Sun
            $table->string('brand_slug', 40)->nullable(); // null = all brands
            $table->text('title');
            $table->string('keyword', 60)->nullable();
            $table->string('owner', 40); // Social, Ops or Marc
            $table->text('done_when')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['calendar_week_id', 'key']);
        });

        Schema::create('calendar_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_week_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('checked_at');
            $table->unique(['calendar_week_id', 'calendar_item_id']);
        });

        Schema::create('boost_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_week_id')->constrained()->cascadeOnDelete();
            $table->string('day', 3);
            $table->string('brand_slug', 40);
            $table->string('format', 40)->nullable();
            $table->boolean('beat_median');
            $table->unsignedInteger('amount')->default(0); // dollars, counted only when boosted
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        (new ActionCalendarSeeder)->run();

        // Carry over the checkmarks already made in the artifact version (Oct 2026).
        $carried = [['2026-09-21', 'mon-fotw', '2026-09-23 19:42:30'], ['2026-09-28', 'fri-pins', '2026-09-29 16:36:26'],
            ['2026-10-12', 'tue-nx', '2026-10-15 16:29:59'], ['2026-10-19', 'mon-feedly', '2026-10-15 16:31:28']];
        foreach ($carried as [$week, $key, $at]) {
            $weekId = DB::table('calendar_weeks')->whereDate('starts_on', $week)->value('id');
            $itemId = DB::table('calendar_items')->whereNull('calendar_week_id')->where('key', $key)->value('id');
            if ($weekId && $itemId && ! DB::table('calendar_checks')->where(['calendar_week_id' => $weekId, 'calendar_item_id' => $itemId])->exists()) {
                DB::table('calendar_checks')->insert(['calendar_week_id' => $weekId, 'calendar_item_id' => $itemId, 'user_id' => null, 'checked_at' => $at]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boost_logs');
        Schema::dropIfExists('calendar_checks');
        Schema::dropIfExists('calendar_items');
        Schema::dropIfExists('calendar_weeks');
    }
};
