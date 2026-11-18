<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('old_path', 512)->unique();
            $table->string('source_table', 64);
            $table->unsignedBigInteger('record_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_redirects');
    }
};
