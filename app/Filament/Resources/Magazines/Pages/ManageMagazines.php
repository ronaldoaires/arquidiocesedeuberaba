<?php

namespace App\Filament\Resources\Magazines\Pages;

use App\Filament\Resources\Magazines\MagazineResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMagazines extends ManageRecords
{
    protected static string $resource = MagazineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
