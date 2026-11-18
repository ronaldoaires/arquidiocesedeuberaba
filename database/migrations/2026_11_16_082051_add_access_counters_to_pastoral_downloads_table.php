<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['visits', 'downloads'] as $column) {
            if (! Schema::hasColumn('pastoral_downloads', $column)) {
                Schema::table('pastoral_downloads', fn (Blueprint $table) => $table->unsignedBigInteger($column)->default(0));
            }
        }
    }

    public function down(): void
    {
        if (DB::table('pastoral_downloads')->where('visits', '>', 0)->orWhere('downloads', '>', 0)->exists()) {
            throw new RuntimeException('Preserve recorded counters before rolling back this migration.');
        }
        Schema::table('pastoral_downloads', fn (Blueprint $table) => $table->dropColumn(['visits', 'downloads']));
    }
};
