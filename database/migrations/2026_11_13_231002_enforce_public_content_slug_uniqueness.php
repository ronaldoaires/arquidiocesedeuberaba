<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['news_categories', 'clergy_categories', 'institute_categories', 'page_categories', 'cities', 'deaneries', 'articles', 'news', 'clergy_members', 'institutes', 'pages', 'parishes', 'events', 'galleries', 'pastoral_groups', 'radio_stations', 'slides'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique('slug');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['slug']);
            });
        }
    }
};
