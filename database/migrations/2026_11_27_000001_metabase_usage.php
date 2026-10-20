<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// How much each person pulled from Metabase each day (counts only, never the data), so daily limits hold.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metabase_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('queries')->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('blocked')->default(0);
            $table->unique(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metabase_usage');
    }
};
