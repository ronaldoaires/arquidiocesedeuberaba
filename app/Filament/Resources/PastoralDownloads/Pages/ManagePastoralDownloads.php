<?php

namespace App\Filament\Resources\PastoralDownloads\Pages;

use App\Filament\Resources\PastoralDownloads\PastoralDownloadResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePastoralDownloads extends ManageRecords
{
    protected static string $resource = PastoralDownloadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
