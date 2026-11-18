<?php

namespace App\Filament\Resources\RadioPrograms;

use App\Filament\Resources\RadioPrograms\Pages\ManageRadioPrograms;
use App\Filament\Support\ContentResource;
use App\Models\RadioProgram;

class RadioProgramResource extends ContentResource
{
    protected static ?string $model = RadioProgram::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'radio_programa';

    public static function getPages(): array
    {
        return ['index' => ManageRadioPrograms::route('/')];
    }
}
