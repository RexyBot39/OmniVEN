<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The morning "my day" Slack message: each person can turn it off; their Slack id is remembered.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('myday_slack')->default(true);
            $table->string('slack_user_id', 32)->nullable();
            $table->date('myday_sent_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['myday_slack', 'slack_user_id', 'myday_sent_on']);
        });
    }
};
