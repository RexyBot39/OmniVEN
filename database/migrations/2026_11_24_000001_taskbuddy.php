<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TaskBuddy AI: turns a document, file or pasted text into a task list. The source is kept (encrypted)
// only until the tasks are added, or for a week, so questions can be answered in between.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taskbuddy_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Who the tasks are for unless the list says otherwise.
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('source_name', 200)->nullable();
            // text, pdf, image or doc (text read out of Word, PowerPoint, Excel…)
            $table->string('source_kind', 12);
            $table->string('source_mime', 100)->nullable();
            $table->longText('source_text')->nullable();
            $table->longText('source_file')->nullable();
            $table->text('note')->nullable();
            // pending, questions, ready, added, error
            $table->string('status', 12)->default('pending');
            $table->string('error', 500)->nullable();
            $table->text('summary')->nullable();
            $table->json('questions')->nullable();
            $table->json('answers')->nullable();
            $table->json('draft')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedSmallInteger('rounds')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->json('task_ids')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taskbuddy_runs');
    }
};
