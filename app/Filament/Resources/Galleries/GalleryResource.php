<?php

namespace App\Filament\Resources\Galleries;

use App\Filament\Resources\Galleries\Pages\EditGallery;
use App\Filament\Resources\Galleries\Pages\ManageGalleries;
use App\Filament\Resources\Galleries\RelationManagers\PhotosRelationManager;
use App\Filament\Support\ContentResource;
use App\Models\Gallery;

class GalleryResource extends ContentResource
{
    protected static ?string $model = Gallery::class;

    public static string $sourceTable = 'galeria';

    public static function getPages(): array
    {
        return ['edit' => EditGallery::route('/{record}/edit'), 'index' => ManageGalleries::route('/')];
    }

    public static function getRelations(): array
    {
        return [PhotosRelationManager::class];
    }
}
