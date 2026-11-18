<?php

namespace App\Filament\Resources\RadioTracks\Pages;

use App\Filament\Resources\RadioTracks\RadioTrackResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRadioTracks extends ManageRecords
{
    protected static string $resource = RadioTrackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
