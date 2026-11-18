<?php

namespace App\Filament\Resources\Slides;

use App\Filament\Resources\Slides\Pages\ManageSlides;
use App\Filament\Support\ContentResource;
use App\Models\Slide;

class SlideResource extends ContentResource
{
    protected static ?string $model = Slide::class;

    public static string $sourceTable = 'slide';

    public static function getPages(): array
    {
        return ['index' => ManageSlides::route('/')];
    }
}
