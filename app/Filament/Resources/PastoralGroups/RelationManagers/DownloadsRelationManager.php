<?php

namespace App\Filament\Resources\PastoralGroups\RelationManagers;

use App\Filament\Support\RelatedContentManager;

class DownloadsRelationManager extends RelatedContentManager
{
    protected static string $relationship = 'downloads';

    protected static ?string $title = 'Downloads';

    public static string $relatedSource = 'pastoral_downloads';

    public static string $parentKey = 'pastoral_group_id';
}
