<?php

namespace App\Filament\Resources\PastoralGalleries;

use App\Filament\Resources\PastoralGalleries\Pages\EditPastoralGallery;
use App\Filament\Resources\PastoralGalleries\Pages\ManagePastoralGalleries;
use App\Filament\Resources\PastoralGalleries\RelationManagers\PhotosRelationManager;
use App\Filament\Support\ContentResource;
use App\Models\PastoralGallery;

class PastoralGalleryResource extends ContentResource
{
    protected static ?string $model = PastoralGallery::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'pastoral_galeria';

    public static function getPages(): array
    {
        return ['edit' => EditPastoralGallery::route('/{record}/edit'), 'index' => ManagePastoralGalleries::route('/')];
    }

    public static function getRelations(): array
    {
        return [PhotosRelationManager::class];
    }
}
