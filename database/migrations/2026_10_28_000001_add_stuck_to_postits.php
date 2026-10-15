<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A stuck sticky note floats in the top right of every page until its owner unsticks it.
// New notes start stuck; notes made before this stay where they are, on the Sticky Notes tab.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postits', function (Blueprint $table) {
            $table->boolean('stuck')->default(false)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('postits', function (Blueprint $table) {
            $table->dropColumn('stuck');
        });
    }
};
