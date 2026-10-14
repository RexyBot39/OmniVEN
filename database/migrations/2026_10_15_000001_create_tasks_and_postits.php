<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('details')->nullable();
            $table->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('hot')->default(true);
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['completed_at', 'hot']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->date('hotlist_popup_on')->nullable();
        });

        Schema::create('postits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('color', 20)->default('yellow');
            $table->timestamps();
        });

        Schema::create('postit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postit_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postit_items');
        Schema::dropIfExists('postits');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('hotlist_popup_on'));
        Schema::dropIfExists('tasks');
    }
};
