<?php

namespace App\Filament\Resources\Cities\Pages;

use App\Filament\Resources\Cities\CityResource;
use App\Filament\Resources\Parishes\ParishResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageCities extends ManageRecords
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Voltar para Paróquias')->icon('heroicon-o-arrow-left')->color('gray')->url(ParishResource::getUrl('index'))->visible(fn (): bool => ParishResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
