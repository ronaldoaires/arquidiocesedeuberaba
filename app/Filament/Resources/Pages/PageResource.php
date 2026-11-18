<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Filament\Resources\Pages\RelationManagers\SectionsRelationManager;
use App\Filament\Support\ContentResource;
use App\Models\Page;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class PageResource extends ContentResource
{
    protected static ?string $model = Page::class;

    public static string $sourceTable = 'pagina';

    public static function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);
        $fields = collect($schema->getComponents())->keyBy(fn (Field $field): string => $field->getName());
        $fields['cover_path']->columnSpanFull();
        $fields['title']->columnSpanFull();
        $fields['body_html']->label('Texto');
        $fields['category_id']->label('Sub Página')->columnSpan(['default' => 1, 'md' => 3]);
        $fields['menu_group']->columnSpan(['default' => 1, 'md' => 3]);
        $fields['sort_order']->integer()->step(1)->columnSpan(['default' => 1, 'md' => 1]);
        $fields['is_active']->columnSpanFull();

        return $schema->components([
            $fields['cover_path'], $fields['title'], $fields['body_html'],
            Grid::make(['default' => 1, 'md' => 7])->schema([
                $fields['menu_group'], $fields['category_id'], $fields['sort_order'],
            ])->columnSpanFull(),
            $fields['is_active'],
        ])->columns(1);
    }

    public static function getPages(): array
    {
        return ['create' => CreatePage::route('/create'), 'edit' => EditPage::route('/{record}/edit'), 'index' => ManagePages::route('/')];
    }

    public static function getRelations(): array
    {
        return [SectionsRelationManager::class];
    }
}
