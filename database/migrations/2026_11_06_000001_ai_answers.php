<?php

use Database\Seeders\AiAnswersSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// AI answer tracking: the buyer questions we ask AI assistants each month, and what each one said.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question', 300);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('ai_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_question_id')->constrained()->cascadeOnDelete();
            $table->string('engine', 32);
            $table->date('month');
            $table->string('result', 16);
            $table->string('competitors', 300)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['ai_question_id', 'engine', 'month']);
            $table->index('month');
        });

        (new AiAnswersSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_checks');
        Schema::dropIfExists('ai_questions');
    }
};
