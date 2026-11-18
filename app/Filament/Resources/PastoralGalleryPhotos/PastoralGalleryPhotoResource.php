<?php

namespace App\Filament\Resources\PastoralGalleryPhotos;

use App\Filament\Resources\PastoralGalleryPhotos\Pages\ManagePastoralGalleryPhotos;
use App\Filament\Support\ContentResource;
use App\Models\PastoralGalleryPhoto;

class PastoralGalleryPhotoResource extends ContentResource
{
    protected static ?string $model = PastoralGalleryPhoto::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'pastoral_galeria_foto';

    public static function getPages(): array
    {
        return ['index' => ManagePastoralGalleryPhotos::route('/')];
    }
}
