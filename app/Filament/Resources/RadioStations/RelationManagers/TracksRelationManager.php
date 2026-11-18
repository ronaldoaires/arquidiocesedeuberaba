<?php

namespace App\Filament\Resources\RadioStations\RelationManagers;

use App\Filament\Support\RelatedContentManager;

class TracksRelationManager extends RelatedContentManager
{
    protected static string $relationship = 'tracks';

    protected static ?string $title = 'Músicas';

    public static string $relatedSource = 'radio_musica';

    public static string $parentKey = 'radio_station_id';
}
