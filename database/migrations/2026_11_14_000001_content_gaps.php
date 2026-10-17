<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What was decided about each content gap (made into a task, or set aside), so it doesn't come back.
// The gaps themselves are worked out fresh from Search Console and AI answers each time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_gaps', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('kind', 16);
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query', 300);
            $table->string('status', 16);
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_gaps');
    }
};
