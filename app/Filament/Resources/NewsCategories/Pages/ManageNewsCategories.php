<?php

namespace App\Filament\Resources\NewsCategories\Pages;

use App\Filament\Resources\NewsCategories\NewsCategoryResource;
use App\Filament\Resources\NewsItems\NewsItemResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageNewsCategories extends ManageRecords
{
    protected static string $resource = NewsCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Voltar para Notícias')->icon('heroicon-o-arrow-left')->color('gray')->url(NewsItemResource::getUrl('index'))->visible(fn (): bool => NewsItemResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
