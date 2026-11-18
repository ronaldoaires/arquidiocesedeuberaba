<?php

namespace App\Filament\Resources\ClergyMembers\Pages;

use App\Filament\Resources\ClergyCategories\ClergyCategoryResource;
use App\Filament\Resources\ClergyMembers\ClergyMemberResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageClergyMembers extends ManageRecords
{
    protected static string $resource = ClergyMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('categories')->label('Categorias')->icon('heroicon-o-tag')->color('gray')->url(ClergyCategoryResource::getUrl('index'))->visible(fn (): bool => ClergyCategoryResource::canViewAny()),
            static::getResource()::createAction(),
        ];
    }
}
