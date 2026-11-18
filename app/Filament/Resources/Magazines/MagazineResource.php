<?php

namespace App\Filament\Resources\Magazines;

use App\Filament\Resources\Magazines\Pages\ManageMagazines;
use App\Filament\Support\ContentResource;
use App\Models\Magazine;

class MagazineResource extends ContentResource
{
    protected static ?string $model = Magazine::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'revista';

    public static function getPages(): array
    {
        return ['index' => ManageMagazines::route('/')];
    }
}
