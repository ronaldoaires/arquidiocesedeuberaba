<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\PageCategories\PageCategoryResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\PageSections\PageSectionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManagePages extends ManageRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('categories')->label('Categorias')->icon('heroicon-o-tag')->color('gray')->url(PageCategoryResource::getUrl('index'))->visible(fn (): bool => PageCategoryResource::canViewAny()),
            Action::make('sections')->label('Complementos')->icon('heroicon-o-document-duplicate')->color('gray')->url(PageSectionResource::getUrl('index'))->visible(fn (): bool => PageSectionResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
