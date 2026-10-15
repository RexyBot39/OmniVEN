<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// People can arrange their starred items. Existing stars keep the order they were starred in.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable()->after('item_id');
        });

        foreach (DB::table('favorites')->orderBy('user_id')->orderBy('id')->get(['id', 'user_id'])->groupBy('user_id') as $rows) {
            foreach ($rows->values() as $i => $row) {
                DB::table('favorites')->where('id', $row->id)->update(['position' => $i + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
