<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A task sent to Jira keeps its ticket key and the ticket's current column, so the board can show it and "done" can
// follow from one side to the other.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('jira_key', 40)->nullable()->unique()->after('board_sort');
            $table->string('jira_status', 80)->nullable()->after('jira_key');
            $table->timestamp('jira_synced_at')->nullable()->after('jira_status');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['jira_key']);
            $table->dropColumn(['jira_key', 'jira_status', 'jira_synced_at']);
        });
    }
};
