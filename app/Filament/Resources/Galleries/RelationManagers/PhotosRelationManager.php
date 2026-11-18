<?php

namespace App\Filament\Resources\Galleries\RelationManagers;

use App\Filament\Support\RelatedContentManager;

class PhotosRelationManager extends RelatedContentManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Fotos';

    public static string $relatedSource = 'galeria_foto';

    public static string $parentKey = 'gallery_id';
}
