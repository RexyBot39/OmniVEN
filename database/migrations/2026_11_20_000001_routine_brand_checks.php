<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Routines that are ticked off brand by brand. The routine counts as done for the period once every
// brand is ticked (a normal routine_completions row is added then, so everything else works as before).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->boolean('per_brand')->default(false);
        });
        Schema::create('routine_brand_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('checked_at');
            $table->unique(['routine_id', 'brand_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_brand_checks');
        Schema::table('routines', fn (Blueprint $t) => $t->dropColumn('per_brand'));
    }
};
