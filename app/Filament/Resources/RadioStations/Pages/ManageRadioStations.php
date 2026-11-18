<?php

namespace App\Filament\Resources\RadioStations\Pages;

use App\Filament\Resources\RadioStations\RadioStationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRadioStations extends ManageRecords
{
    protected static string $resource = RadioStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
