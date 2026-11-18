<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['banners', 'magazines'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('title')->nullable();
                $table->string('cover_path', 1024)->nullable();
                $table->boolean('is_active')->default(false);
                $table->bigInteger('visits')->nullable();
                $table->string('import_hash', 64)->nullable();
                $table->timestamps();
                $table->softDeletes();
                if ($name === 'banners') {
                    $table->string('location')->nullable();
                    $table->text('link')->nullable();
                } else {
                    $table->string('slug')->nullable()->unique();
                    $table->longText('body_html')->nullable();
                    $table->string('file_path', 1024)->nullable();
                    $table->integer('month')->nullable();
                    $table->integer('year')->nullable();
                    $table->string('edition')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('magazines');
        Schema::dropIfExists('banners');
    }
};
