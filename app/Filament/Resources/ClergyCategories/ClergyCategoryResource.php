<?php

namespace App\Filament\Resources\ClergyCategories;

use App\Filament\Resources\ClergyCategories\Pages\ManageClergyCategories;
use App\Filament\Support\ContentResource;
use App\Models\ClergyCategory;

class ClergyCategoryResource extends ContentResource
{
    protected static ?string $model = ClergyCategory::class;

    public static string $sourceTable = 'clero_categoria';

    protected static bool $shouldRegisterNavigation = false;

    public static function getPages(): array
    {
        return ['index' => ManageClergyCategories::route('/')];
    }
}
