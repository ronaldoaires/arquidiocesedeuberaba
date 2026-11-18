<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Filament\Support\ContentResource;
use App\Models\Article;

class ArticleResource extends ContentResource
{
    protected static ?string $model = Article::class;

    public static string $sourceTable = 'artigo';

    public static function getPages(): array
    {
        return ['create' => CreateArticle::route('/create'), 'edit' => EditArticle::route('/{record}/edit'), 'index' => ManageArticles::route('/')];
    }
}
