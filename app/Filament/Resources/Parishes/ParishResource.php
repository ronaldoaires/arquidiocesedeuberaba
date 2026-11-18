<?php

namespace App\Filament\Resources\Parishes;

use App\Filament\Resources\Parishes\Pages\CreateParish;
use App\Filament\Resources\Parishes\Pages\EditParish;
use App\Filament\Resources\Parishes\Pages\ManageParishes;
use App\Filament\Support\ContentResource;
use App\Models\Parish;

class ParishResource extends ContentResource
{
    protected static ?string $model = Parish::class;

    public static string $sourceTable = 'paroquia';

    public static function getPages(): array
    {
        return ['create' => CreateParish::route('/create'), 'edit' => EditParish::route('/{record}/edit'), 'index' => ManageParishes::route('/')];
    }
}
