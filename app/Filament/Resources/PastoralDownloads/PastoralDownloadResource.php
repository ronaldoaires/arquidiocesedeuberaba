<?php

namespace App\Filament\Resources\PastoralDownloads;

use App\Filament\Resources\PastoralDownloads\Pages\ManagePastoralDownloads;
use App\Filament\Support\ContentResource;
use App\Models\PastoralDownload;

class PastoralDownloadResource extends ContentResource
{
    protected static ?string $model = PastoralDownload::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'pastoral_downloads';

    public static function getPages(): array
    {
        return ['index' => ManagePastoralDownloads::route('/')];
    }
}
