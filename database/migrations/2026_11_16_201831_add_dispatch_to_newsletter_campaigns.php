<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_campaigns', function (Blueprint $table): void {
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->longText('dispatch_html')->nullable();
            $table->string('dispatch_mailer')->nullable();
            $table->json('attachment_hashes')->nullable();
        });
        Schema::create('newsletter_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('newsletter_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('newsletter_subscriber_id')->constrained();
            $table->string('email', 254);
            $table->uuid('subscription_key');
            $table->string('status', 16)->default('pending');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('detail')->nullable();
            $table->unique(['newsletter_campaign_id', 'newsletter_subscriber_id'], 'newsletter_delivery_recipient');
            $table->index(['newsletter_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_deliveries');
        Schema::table('newsletter_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['dispatched_at', 'completed_at', 'dispatch_html', 'dispatch_mailer', 'attachment_hashes']);
        });
    }
};
