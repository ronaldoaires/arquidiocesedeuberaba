<?php

namespace App\Filament\Resources\RadioStations;

use App\Filament\Resources\RadioStations\Pages\EditRadioStation;
use App\Filament\Resources\RadioStations\Pages\ManageRadioStations;
use App\Filament\Resources\RadioStations\RelationManagers\ProgramsRelationManager;
use App\Filament\Resources\RadioStations\RelationManagers\TracksRelationManager;
use App\Filament\Support\ContentResource;
use App\Models\RadioStation;

class RadioStationResource extends ContentResource
{
    protected static ?string $model = RadioStation::class;

    public static string $sourceTable = 'radio';

    public static function getPages(): array
    {
        return ['edit' => EditRadioStation::route('/{record}/edit'), 'index' => ManageRadioStations::route('/')];
    }

    public static function getRelations(): array
    {
        return [ProgramsRelationManager::class, TracksRelationManager::class];
    }
}
