<?php

namespace App\Filament\Resources\Cities;

use App\Filament\Resources\Cities\Pages\ManageCities;
use App\Filament\Support\ContentResource;
use App\Models\City;

class CityResource extends ContentResource
{
    protected static ?string $model = City::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'paroquia_cidade';

    public static function getPages(): array
    {
        return ['index' => ManageCities::route('/')];
    }
}
