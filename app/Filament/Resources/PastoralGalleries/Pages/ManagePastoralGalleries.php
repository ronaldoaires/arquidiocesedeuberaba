<?php

namespace App\Filament\Resources\PastoralGalleries\Pages;

use App\Filament\Resources\PastoralGalleries\PastoralGalleryResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePastoralGalleries extends ManageRecords
{
    protected static string $resource = PastoralGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
