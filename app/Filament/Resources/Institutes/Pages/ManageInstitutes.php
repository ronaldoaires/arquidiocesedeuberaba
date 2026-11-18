<?php

namespace App\Filament\Resources\Institutes\Pages;

use App\Filament\Resources\InstituteCategories\InstituteCategoryResource;
use App\Filament\Resources\Institutes\InstituteResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageInstitutes extends ManageRecords
{
    protected static string $resource = InstituteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('categories')->label('Categorias')->icon('heroicon-o-tag')->color('gray')->url(InstituteCategoryResource::getUrl('index'))->visible(fn (): bool => InstituteCategoryResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
