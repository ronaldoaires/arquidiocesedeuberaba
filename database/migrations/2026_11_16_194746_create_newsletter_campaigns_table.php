<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
            $table->longText('body_html');
            $table->json('content_keys')->nullable();
            $table->json('attachments')->nullable();
            $table->json('attachment_names')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
