<?php

use Database\Seeders\BrandDetailsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A brand can use another brand's accounts (Lumeo and Lens Replacement use 39DollarGlasses').
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->foreignId('accounts_from_id')->nullable()->constrained('brands')->nullOnDelete();
        });
        (new BrandDetailsSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounts_from_id');
        });
    }
};
