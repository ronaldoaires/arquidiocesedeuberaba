<?php

namespace App\Filament\Resources\NewsCategories;

use App\Filament\Resources\NewsCategories\Pages\ManageNewsCategories;
use App\Filament\Support\ContentResource;
use App\Models\NewsCategory;

class NewsCategoryResource extends ContentResource
{
    protected static ?string $model = NewsCategory::class;

    public static string $sourceTable = 'noticia_categoria';

    protected static bool $shouldRegisterNavigation = false;

    public static function getPages(): array
    {
        return ['index' => ManageNewsCategories::route('/')];
    }
}
