<?php

namespace App\Filament\Resources\Deaneries\Pages;

use App\Filament\Resources\Deaneries\DeaneryResource;
use App\Filament\Resources\Parishes\ParishResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageDeaneries extends ManageRecords
{
    protected static string $resource = DeaneryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Voltar para Paróquias')->icon('heroicon-o-arrow-left')->color('gray')->url(ParishResource::getUrl('index'))->visible(fn (): bool => ParishResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
