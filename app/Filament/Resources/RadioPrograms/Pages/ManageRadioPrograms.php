<?php

namespace App\Filament\Resources\RadioPrograms\Pages;

use App\Filament\Resources\RadioPrograms\RadioProgramResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRadioPrograms extends ManageRecords
{
    protected static string $resource = RadioProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
