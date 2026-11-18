<?php

namespace App\Filament\Resources\Institutes;

use App\Filament\Resources\Institutes\Pages\ManageInstitutes;
use App\Filament\Support\ContentResource;
use App\Models\Institute;

class InstituteResource extends ContentResource
{
    protected static ?string $model = Institute::class;

    public static string $sourceTable = 'instituto';

    public static function getPages(): array
    {
        return ['index' => ManageInstitutes::route('/')];
    }
}
