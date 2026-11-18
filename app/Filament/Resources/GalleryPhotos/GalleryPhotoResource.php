<?php

namespace App\Filament\Resources\GalleryPhotos;

use App\Filament\Resources\GalleryPhotos\Pages\ManageGalleryPhotos;
use App\Filament\Support\ContentResource;
use App\Models\GalleryPhoto;

class GalleryPhotoResource extends ContentResource
{
    protected static ?string $model = GalleryPhoto::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'galeria_foto';

    public static function getPages(): array
    {
        return ['index' => ManageGalleryPhotos::route('/')];
    }
}
