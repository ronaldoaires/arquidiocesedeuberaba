<?php

namespace App\Filament\Resources\InstituteCategories;

use App\Filament\Resources\InstituteCategories\Pages\ManageInstituteCategories;
use App\Filament\Support\ContentResource;
use App\Models\InstituteCategory;

class InstituteCategoryResource extends ContentResource
{
    protected static ?string $model = InstituteCategory::class;

    public static string $sourceTable = 'instituto_categoria';

    protected static bool $shouldRegisterNavigation = false;

    public static function getPages(): array
    {
        return ['index' => ManageInstituteCategories::route('/')];
    }
}
