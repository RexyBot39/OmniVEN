<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Monthly affiliate totals per network and publisher, from the CJ and Awin reporting APIs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_months', function (Blueprint $table) {
            $table->id();
            $table->string('network', 16);
            $table->date('month');
            $table->string('publisher_id', 64);
            $table->string('publisher_name', 200)->nullable();
            $table->integer('orders')->default(0);
            $table->bigInteger('sale_cents')->default(0);
            $table->bigInteger('commission_cents')->default(0);
            $table->bigInteger('pending_cents')->default(0);
            $table->timestamps();
            $table->unique(['network', 'month', 'publisher_id']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_months');
    }
};
