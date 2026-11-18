<?php

namespace App\Filament\Resources\NewsItems\Pages;

use App\Filament\Resources\NewsCategories\NewsCategoryResource;
use App\Filament\Resources\NewsItems\NewsItemResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageNewsItems extends ManageRecords
{
    protected static string $resource = NewsItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('categories')->label('Categorias')->icon('heroicon-o-tag')->color('gray')->url(NewsCategoryResource::getUrl('index'))->visible(fn (): bool => NewsCategoryResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
