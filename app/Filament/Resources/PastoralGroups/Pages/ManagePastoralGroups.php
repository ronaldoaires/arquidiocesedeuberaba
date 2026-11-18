<?php

namespace App\Filament\Resources\PastoralGroups\Pages;

use App\Filament\Resources\PastoralGroups\PastoralGroupResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePastoralGroups extends ManageRecords
{
    protected static string $resource = PastoralGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
