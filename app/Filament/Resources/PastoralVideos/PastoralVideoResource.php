<?php

namespace App\Filament\Resources\PastoralVideos;

use App\Filament\Resources\PastoralVideos\Pages\ManagePastoralVideos;
use App\Filament\Support\ContentResource;
use App\Models\PastoralVideo;

class PastoralVideoResource extends ContentResource
{
    protected static ?string $model = PastoralVideo::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'pastoral_videos';

    public static function getPages(): array
    {
        return ['index' => ManagePastoralVideos::route('/')];
    }
}
