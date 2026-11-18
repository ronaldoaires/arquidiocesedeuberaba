<?php

namespace App\Filament\Resources\Deaneries;

use App\Filament\Resources\Deaneries\Pages\ManageDeaneries;
use App\Filament\Support\ContentResource;
use App\Models\Deanery;

class DeaneryResource extends ContentResource
{
    protected static ?string $model = Deanery::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'paroquia_forania';

    public static function getPages(): array
    {
        return ['index' => ManageDeaneries::route('/')];
    }
}
