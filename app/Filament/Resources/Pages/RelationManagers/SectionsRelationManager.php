<?php

namespace App\Filament\Resources\Pages\RelationManagers;

use App\Filament\Support\RelatedContentManager;

class SectionsRelationManager extends RelatedContentManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Complementos';

    public static string $relatedSource = 'pagina_complemento';

    public static string $parentKey = 'page_id';
}
