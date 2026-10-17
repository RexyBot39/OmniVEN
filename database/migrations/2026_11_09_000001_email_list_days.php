<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The size of our main Klaviyo list each day, so list growth can be shown. A count only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_list_days', function (Blueprint $table) {
            $table->id();
            $table->string('list_id', 32);
            $table->date('day');
            $table->unsignedInteger('profiles');
            $table->timestamps();
            $table->unique(['list_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_list_days');
    }
};
