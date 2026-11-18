<?php

namespace App\Filament\Resources\Newspapers;

use App\Filament\Resources\Newspapers\Pages\ManageNewspapers;
use App\Filament\Support\ContentResource;
use App\Models\Newspaper;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class NewspaperResource extends ContentResource
{
    protected static ?string $model = Newspaper::class;

    public static string $sourceTable = 'jornal';

    public static function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);
        $fields = collect($schema->getComponents())->keyBy(fn (Field $field): string => $field->getName());
        $fields['month']->default(fn (): int => now('America/Sao_Paulo')->month);
        $fields['year']->default(fn (): int => now('America/Sao_Paulo')->year);
        $components = [];
        foreach ($fields as $name => $field) {
            if ($name === 'month') {
                $components[] = Grid::make(['default' => 1, 'md' => 3])->schema([
                    $fields['month'], $fields['year'], $fields['edition'],
                ])->columnSpanFull();
            } elseif (! in_array($name, ['year', 'edition'], true)) {
                $components[] = $field;
            }
        }

        return $schema->components($components);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNewspapers::route('/')];
    }
}
