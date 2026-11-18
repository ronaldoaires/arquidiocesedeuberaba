<?php

namespace App\Filament\Resources\ParishClergyAssignments;

use App\Filament\Resources\ParishClergyAssignments\Pages\ManageParishClergyAssignments;
use App\Filament\Support\ContentResource;
use App\Models\ParishClergyAssignment;

class ParishClergyAssignmentResource extends ContentResource
{
    protected static ?string $model = ParishClergyAssignment::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'paroquia_clero';

    public static function getPages(): array
    {
        return ['index' => ManageParishClergyAssignments::route('/')];
    }
}
