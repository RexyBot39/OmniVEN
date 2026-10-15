<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Access simplification: people request access to a tool or brand account; admins grant or decline.
// Tools get a last-verified date like accounts, and a quarterly access review routine is added.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20); // tool or account
            $table->unsignedBigInteger('item_id');
            $table->string('reason', 500)->nullable();
            $table->string('status', 12)->default('open'); // open, granted, declined
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('decision_note', 300)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['item_type', 'item_id']);
            $table->index(['user_id', 'item_type', 'item_id']);
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->date('verified_on')->nullable();
        });

        if (! DB::table('routines')->where('title', 'Quarterly access review')->exists()) {
            DB::table('routines')->insert([
                'title' => 'Quarterly access review',
                'cadence' => 'quarterly',
                'description' => 'Open the Access review, confirm every login and owner is still right, and turn off OmniVEN for anyone who has left.',
                'link' => rtrim((string) config('app.url'), '/').'/access/review',
                'all_brands' => true,
                'sort' => 200,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('routines')->where('title', 'Quarterly access review')->delete();
        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn('verified_on');
        });
        Schema::dropIfExists('access_requests');
    }
};
