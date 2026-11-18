<?php

namespace App\Filament\Resources\PageCategories\Pages;

use App\Filament\Resources\PageCategories\PageCategoryResource;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManagePageCategories extends ManageRecords
{
    protected static string $resource = PageCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Voltar para Páginas')->icon('heroicon-o-arrow-left')->color('gray')->url(PageResource::getUrl('index'))->visible(fn (): bool => PageResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
