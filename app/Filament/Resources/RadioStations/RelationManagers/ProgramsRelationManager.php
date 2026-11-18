<?php

namespace App\Filament\Resources\RadioStations\RelationManagers;

use App\Filament\Support\RelatedContentManager;
use Filament\Tables\Table;

class ProgramsRelationManager extends RelatedContentManager
{
    protected static string $relationship = 'programs';

    protected static ?string $title = 'Programação';

    public static string $relatedSource = 'radio_programa';

    public static string $parentKey = 'radio_station_id';

    public function table(Table $table): Table
    {
        $table = parent::table($table);
        $columns = $table->getColumns();
        unset($columns['visits']);

        return $table->columns(array_values($columns));
    }
}
