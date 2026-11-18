<?php

namespace App\Filament\Resources\PageCategories;

use App\Filament\Resources\PageCategories\Pages\ManagePageCategories;
use App\Filament\Support\ContentResource;
use App\Models\PageCategory;

class PageCategoryResource extends ContentResource
{
    protected static ?string $model = PageCategory::class;

    public static string $sourceTable = 'pagina_categoria';

    protected static bool $shouldRegisterNavigation = false;

    public static function getPages(): array
    {
        return ['index' => ManagePageCategories::route('/')];
    }
}
