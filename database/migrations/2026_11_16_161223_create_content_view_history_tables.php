<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_view_daily', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 32);
            $table->unsignedBigInteger('content_id');
            $table->string('title_snapshot', 255);
            $table->date('view_date');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
            $table->unique(['source', 'content_id', 'view_date']);
            $table->index(['view_date', 'source']);
        });
        Schema::create('content_view_tracking', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->dateTime('started_at');
        });
        DB::table('content_view_tracking')->insert(['id' => 1, 'started_at' => now('UTC')]);
    }

    public function down(): void
    {
        if (DB::table('content_view_daily')->exists()) {
            throw new RuntimeException('Back up recorded content history before rolling back this migration.');
        }
        Schema::dropIfExists('content_view_daily');
        Schema::dropIfExists('content_view_tracking');
    }
};
