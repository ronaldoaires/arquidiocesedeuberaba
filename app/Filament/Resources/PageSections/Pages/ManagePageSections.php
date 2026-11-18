<?php

namespace App\Filament\Resources\PageSections\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\PageSections\PageSectionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManagePageSections extends ManageRecords
{
    protected static string $resource = PageSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Voltar para Páginas')->icon('heroicon-o-arrow-left')->color('gray')->url(PageResource::getUrl('index'))->visible(fn (): bool => PageResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
