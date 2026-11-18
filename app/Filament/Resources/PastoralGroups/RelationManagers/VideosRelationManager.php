<?php

namespace App\Filament\Resources\PastoralGroups\RelationManagers;

use App\Filament\Support\LoadsYouTubeMetadata;
use App\Filament\Support\RelatedContentManager;

class VideosRelationManager extends RelatedContentManager
{
    use LoadsYouTubeMetadata;

    protected static string $relationship = 'videos';

    protected static ?string $title = 'Vídeos';

    public static string $relatedSource = 'pastoral_videos';

    public static string $parentKey = 'pastoral_group_id';
}
