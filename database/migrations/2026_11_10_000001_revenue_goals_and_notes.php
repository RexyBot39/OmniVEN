<?php

use Database\Seeders\WeeklyRevenueReviewSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Monthly revenue goals per brand, and the weekly "what moved and why" note on the Sales page.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedBigInteger('goal_cents');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['brand_id', 'month']);
        });

        Schema::create('sales_notes', function (Blueprint $table) {
            $table->id();
            $table->date('week');
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique('week');
        });

        (new WeeklyRevenueReviewSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_notes');
        Schema::dropIfExists('revenue_goals');
    }
};
