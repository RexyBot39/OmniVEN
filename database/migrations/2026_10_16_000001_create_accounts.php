<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row per brand + platform account (e.g. Ocusafe · Instagram · @ocusafe).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 40);
            $table->string('handle', 160)->nullable();      // @ocusafe, page name, or account name
            $table->string('url', 1024)->nullable();        // direct link to this brand's account
            $table->string('account_ref', 120)->nullable(); // account / property / ad account ID
            $table->string('login', 200)->nullable();
            $table->string('owner', 200)->nullable();
            $table->text('notes')->nullable();
            $table->date('verified_on')->nullable();
            $table->timestamps();
            $table->index(['brand_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
