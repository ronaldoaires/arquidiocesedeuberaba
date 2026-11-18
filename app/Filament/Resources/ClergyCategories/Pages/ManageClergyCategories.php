<?php

namespace App\Filament\Resources\ClergyCategories\Pages;

use App\Filament\Resources\ClergyCategories\ClergyCategoryResource;
use App\Filament\Resources\ClergyMembers\ClergyMemberResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageClergyCategories extends ManageRecords
{
    protected static string $resource = ClergyCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Voltar para Clero')->icon('heroicon-o-arrow-left')->color('gray')->url(ClergyMemberResource::getUrl('index'))->visible(fn (): bool => ClergyMemberResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
