<?php

use Database\Seeders\TaskTemplatesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Launch and promo templates: reusable checklists with due dates relative to a start day, and each
// time one is run (so its tasks can be followed together).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('task_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_template_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('details')->nullable();
            $table->integer('offset_days')->default(0);
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('hot')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('template_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_on');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('template_run_id')->nullable()->constrained()->nullOnDelete();
        });

        (new TaskTemplatesSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_run_id');
        });
        Schema::dropIfExists('template_runs');
        Schema::dropIfExists('task_template_items');
        Schema::dropIfExists('task_templates');
    }
};
