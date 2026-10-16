<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The task board: each marketing person gets a column (users.board_position), and a task can be
// labelled with the brand it's for (tasks.brand_id). Leona, Chanley and Kaylie get the first three columns.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('details')->constrained()->nullOnDelete();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('board_position')->nullable()->after('is_active');
        });

        foreach (config('omniven.task_board') as $first => $pos) {
            DB::table('users')->whereNull('board_position')
                ->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$first.'%'])->orWhereRaw('lower(email) like ?', [$first.'%']))
                ->update(['board_position' => $pos]);
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('board_position');
        });
    }
};
