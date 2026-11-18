<?php

namespace App\Filament\Resources\Banners;

use App\Filament\Resources\Banners\Pages\ManageBanners;
use App\Filament\Support\ContentResource;
use App\Models\Banner;

class BannerResource extends ContentResource
{
    protected static ?string $model = Banner::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'banners';

    public static function getPages(): array
    {
        return ['index' => ManageBanners::route('/')];
    }
}
