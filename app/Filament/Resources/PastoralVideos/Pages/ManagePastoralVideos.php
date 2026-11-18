<?php

namespace App\Filament\Resources\PastoralVideos\Pages;

use App\Filament\Resources\PastoralVideos\PastoralVideoResource;
use App\Filament\Support\LoadsYouTubeMetadata;
use Filament\Resources\Pages\ManageRecords;

class ManagePastoralVideos extends ManageRecords
{
    use LoadsYouTubeMetadata;

    protected static string $resource = PastoralVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
