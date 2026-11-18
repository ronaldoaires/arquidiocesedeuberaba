<?php

namespace App\Filament\Resources\InstituteCategories\Pages;

use App\Filament\Resources\InstituteCategories\InstituteCategoryResource;
use App\Filament\Resources\Institutes\InstituteResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageInstituteCategories extends ManageRecords
{
    protected static string $resource = InstituteCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Voltar para Institutos')->icon('heroicon-o-arrow-left')->color('gray')->url(InstituteResource::getUrl('index'))->visible(fn (): bool => InstituteResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
