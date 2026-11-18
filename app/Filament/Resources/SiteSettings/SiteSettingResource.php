<?php

namespace App\Filament\Resources\SiteSettings;

use App\Filament\Resources\SiteSettings\Pages\ManageSiteSettings;
use App\Filament\Support\ContentResource;
use App\Models\SiteSetting;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SiteSettingResource extends ContentResource
{
    protected static ?string $model = SiteSetting::class;

    public static string $sourceTable = 'config';

    public static function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);
        foreach ($schema->getComponents() as $field) {
            if ($field->getName() === 'cover_path') {
                $field->label('Logo')->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/avif'])->columnSpanFull();
            }
        }

        return $schema;
    }

    public static function table(Table $table): Table
    {
        $table = parent::table($table);
        $table->getColumn('cover_path')->label('Logo');

        return $table;
    }

    public static function getPages(): array
    {
        return ['index' => ManageSiteSettings::route('/')];
    }
}
