<?php

namespace App\Filament\Resources\Newspapers\Pages;

use App\Filament\Resources\Newspapers\NewspaperResource;
use Filament\Resources\Pages\ManageRecords;

class ManageNewspapers extends ManageRecords
{
    protected static string $resource = NewspaperResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
