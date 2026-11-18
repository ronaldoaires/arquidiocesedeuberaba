<?php

namespace App\Filament\Resources\ClergyMembers\RelationManagers;

use App\Filament\Support\RelatedContentManager;

class AssignmentsRelationManager extends RelatedContentManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Vínculos com paróquias';

    public static string $relatedSource = 'paroquia_clero';

    public static string $parentKey = 'clergy_member_id';
}
