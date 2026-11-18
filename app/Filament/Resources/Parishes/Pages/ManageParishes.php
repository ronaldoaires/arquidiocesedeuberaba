<?php

namespace App\Filament\Resources\Parishes\Pages;

use App\Filament\Resources\Cities\CityResource;
use App\Filament\Resources\Deaneries\DeaneryResource;
use App\Filament\Resources\Parishes\ParishResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageParishes extends ManageRecords
{
    protected static string $resource = ParishResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deaneries')->label('Foranias')->icon('heroicon-o-map')->color('gray')->url(DeaneryResource::getUrl('index'))->visible(fn (): bool => DeaneryResource::canViewAny()),
            Action::make('cities')->label('Cidades')->icon('heroicon-o-map-pin')->color('gray')->url(CityResource::getUrl('index'))->visible(fn (): bool => CityResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
