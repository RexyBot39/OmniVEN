<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The newest Slack message each person has seen, per channel. Drives the red unread asterisks.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slack_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slack_channel_id')->constrained()->cascadeOnDelete();
            $table->string('last_read_ts', 32);
            $table->timestamps();
            $table->unique(['user_id', 'slack_channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_reads');
    }
};
