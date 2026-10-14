<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('site', 1024)->nullable();
            $table->text('focus')->nullable();
            $table->string('channels', 1024)->nullable();
            $table->json('socials')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 1024)->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('featured')->default(false);
            $table->boolean('all_brands')->default(false);
            $table->string('login')->nullable();
            $table->string('owner')->nullable();
            $table->text('access_note')->nullable();
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('brand_tool', function (Blueprint $table) {
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->primary(['brand_id', 'tool_id']);
        });

        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('cadence', 20)->default('weekly');
            $table->text('description')->nullable();
            $table->string('link', 1024)->nullable();
            $table->boolean('all_brands')->default(false);
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('brand_routine', function (Blueprint $table) {
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->primary(['brand_id', 'routine_id']);
        });

        Schema::create('routine_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period_key', 20);
            $table->timestamp('completed_at');
            $table->unique(['routine_id', 'period_key']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('pinned')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('routine_completions');
        Schema::dropIfExists('brand_routine');
        Schema::dropIfExists('routines');
        Schema::dropIfExists('brand_tool');
        Schema::dropIfExists('tools');
        Schema::dropIfExists('brands');
    }
};
