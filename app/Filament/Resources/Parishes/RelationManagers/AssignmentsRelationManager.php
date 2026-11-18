<?php

namespace App\Filament\Resources\Parishes\RelationManagers;

use App\Filament\Support\RelatedContentManager;

class AssignmentsRelationManager extends RelatedContentManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Vínculos com o clero';

    public static string $relatedSource = 'paroquia_clero';

    public static string $parentKey = 'parish_id';
}
