<?php

namespace App\Filament\Resources\PastoralGalleries\RelationManagers;

use App\Filament\Support\RelatedContentManager;

class PhotosRelationManager extends RelatedContentManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Fotos';

    public static string $relatedSource = 'pastoral_galeria_foto';

    public static string $parentKey = 'gallery_id';
}
