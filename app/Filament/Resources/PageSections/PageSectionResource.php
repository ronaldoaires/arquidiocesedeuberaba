<?php

namespace App\Filament\Resources\PageSections;

use App\Filament\Resources\PageSections\Pages\ManagePageSections;
use App\Filament\Support\ContentResource;
use App\Models\PageSection;

class PageSectionResource extends ContentResource
{
    protected static ?string $model = PageSection::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'pagina_complemento';

    public static function getPages(): array
    {
        return ['index' => ManagePageSections::route('/')];
    }
}
