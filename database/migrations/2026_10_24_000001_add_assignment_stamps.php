<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// When, and by which admin, each task and routine was assigned. Shown to admins only.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks', 'routines'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->timestamp('assigned_at')->nullable();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        // Best guess for existing rows: a task was assigned when it was created, by its creator;
        // an assigned routine was assigned at its last edit (by an unknown admin).
        DB::table('tasks')->update(['assigned_at' => DB::raw('created_at'), 'assigned_by' => DB::raw('created_by')]);
        DB::table('routines')->whereNotNull('assignee_id')->update(['assigned_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        foreach (['tasks', 'routines'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('assigned_by');
                $table->dropColumn('assigned_at');
            });
        }
    }
};
