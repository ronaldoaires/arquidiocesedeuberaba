<?php

namespace App\Filament\Resources\ParishClergyAssignments\Pages;

use App\Filament\Resources\ParishClergyAssignments\ParishClergyAssignmentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageParishClergyAssignments extends ManageRecords
{
    protected static string $resource = ParishClergyAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::createAction(),
        ];
    }
}
