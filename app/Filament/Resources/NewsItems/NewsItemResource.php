<?php

namespace App\Filament\Resources\NewsItems;

use App\Filament\Resources\NewsItems\Pages\CreateNewsItem;
use App\Filament\Resources\NewsItems\Pages\EditNewsItem;
use App\Filament\Resources\NewsItems\Pages\ManageNewsItems;
use App\Filament\Support\ContentResource;
use App\Models\NewsItem;

class NewsItemResource extends ContentResource
{
    protected static ?string $model = NewsItem::class;

    public static string $sourceTable = 'noticia';

    public static function getPages(): array
    {
        return ['create' => CreateNewsItem::route('/create'), 'edit' => EditNewsItem::route('/{record}/edit'), 'index' => ManageNewsItems::route('/')];
    }
}
