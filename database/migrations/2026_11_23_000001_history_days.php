<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Daily totals for date-range reports: Search Console per brand (clicks, impressions, position) and
// Klaviyo email activity (sends, opens, clicks, orders…) split into campaigns and flows. Totals only:
// no searches, no profiles, no email addresses.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            // Average position × impressions, so positions can be averaged over any range.
            $table->double('position_x_impressions')->default(0);
            $table->timestamps();
            $table->unique(['brand_id', 'day']);
        });

        Schema::create('email_days', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            // received, opened, clicked, bounced, unsubscribed, spam, subscribed, orders
            $table->string('metric', 24);
            // campaign, flow, or all (for metrics not split that way)
            $table->string('kind', 12);
            $table->unsignedInteger('count')->default(0);
            $table->unsignedBigInteger('value_cents')->default(0);
            $table->timestamps();
            $table->unique(['day', 'metric', 'kind']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_days');
        Schema::dropIfExists('search_days');
    }
};
