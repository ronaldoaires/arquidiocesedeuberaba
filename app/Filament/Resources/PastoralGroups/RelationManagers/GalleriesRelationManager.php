<?php

namespace App\Filament\Resources\PastoralGroups\RelationManagers;

use App\Filament\Support\RelatedContentManager;

class GalleriesRelationManager extends RelatedContentManager
{
    protected static string $relationship = 'galleries';

    protected static ?string $title = 'Galerias';

    public static string $relatedSource = 'pastoral_galeria';

    public static string $parentKey = 'pastoral_group_id';
}
