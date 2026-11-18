<?php

namespace App\Filament\Resources\Awards;

use App\Filament\Resources\Awards\Pages\ManageAwards;
use App\Filament\Support\ContentResource;
use App\Models\Award;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AwardResource extends ContentResource
{
    protected static ?string $model = Award::class;

    public static string $sourceTable = 'premio';

    public static function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);
        foreach ($schema->getComponents() as $field) {
            if ($field->getName() === 'year') {
                $field->default(fn (): int => now('America/Sao_Paulo')->year);
            }
        }

        return $schema;
    }

    public static function table(Table $table): Table
    {
        $table = parent::table($table);
        $table->getColumn('visits')->hidden();

        return $table;
    }

    public static function getPages(): array
    {
        return ['index' => ManageAwards::route('/')];
    }
}
