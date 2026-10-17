<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Daily sales totals per brand and channel, copied from GA4. Totals only: no orders, no customers.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('channel', 32);
            $table->string('source', 16)->default('ga4');
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedBigInteger('revenue_cents')->default(0);
            $table->timestamps();
            $table->unique(['brand_id', 'day', 'channel', 'source']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_days');
    }
};
