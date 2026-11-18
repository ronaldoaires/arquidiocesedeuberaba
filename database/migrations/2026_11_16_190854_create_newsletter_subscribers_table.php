<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 254)->unique();
            $table->uuid('link_key')->unique();
            $table->string('status', 16)->default('pending')->index();
            $table->string('name', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->char('state', 2)->nullable();
            $table->timestamp('last_requested_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
