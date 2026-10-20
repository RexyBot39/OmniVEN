<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SEO suite on DataForSEO: the searches we track and their positions over time, site audits, and a ledger of
// what each DataForSEO call cost (so the monthly budget holds). Reports themselves are cached, not stored here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataforseo_calls', function (Blueprint $table) {
            $table->id();
            $table->string('path', 160);
            $table->decimal('cost', 10, 5)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 200);
            // Latest organic position (null: not in the depth checked), the one before, and the best seen.
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->unsignedSmallInteger('best_position')->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedInteger('search_volume')->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            // A queued DataForSEO check waiting to be collected.
            $table->string('task_id', 64)->nullable();
            $table->timestamp('task_posted_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['brand_id', 'keyword']);
        });

        Schema::create('seo_rank_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_keyword_id')->constrained()->cascadeOnDelete();
            $table->date('checked_on');
            $table->unsignedSmallInteger('position')->nullable();
            $table->unique(['seo_keyword_id', 'checked_on']);
        });

        Schema::create('seo_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('task_id', 64)->nullable();
            // crawling, done, failed
            $table->string('status', 12)->default('crawling');
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->unsignedInteger('max_pages')->default(0);
            $table->decimal('score', 5, 2)->nullable();
            // Issue counts across the site, and the pages that need the most work.
            $table->json('summary')->nullable();
            $table->json('pages')->nullable();
            $table->string('error', 500)->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audits');
        Schema::dropIfExists('seo_rank_checks');
        Schema::dropIfExists('seo_keywords');
        Schema::dropIfExists('dataforseo_calls');
    }
};
