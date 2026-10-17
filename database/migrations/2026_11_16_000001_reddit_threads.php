<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reddit threads worth a reply, and what was done about each. Public thread facts only; no usernames or post text.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reddit_threads', function (Blueprint $table) {
            $table->id();
            $table->string('reddit_id', 20)->unique();
            $table->string('subreddit', 64);
            $table->string('title', 300);
            $table->string('url', 500);
            $table->timestamp('posted_at');
            $table->unsignedInteger('comments')->default(0);
            $table->integer('votes')->default(0);
            $table->string('kind', 16);
            $table->unsignedTinyInteger('score');
            $table->json('matched')->nullable();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('new');
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reddit_threads');
    }
};
