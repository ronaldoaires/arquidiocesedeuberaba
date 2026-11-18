<?php

namespace App\Filament\Resources\PastoralGalleryPhotos\Pages;

use App\Filament\Resources\PastoralGalleryPhotos\PastoralGalleryPhotoResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePastoralGalleryPhotos extends ManageRecords
{
    protected static string $resource = PastoralGalleryPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
