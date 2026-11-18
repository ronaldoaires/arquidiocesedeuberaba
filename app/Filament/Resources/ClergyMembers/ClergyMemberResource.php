<?php

namespace App\Filament\Resources\ClergyMembers;

use App\Filament\Resources\ClergyMembers\Pages\CreateClergyMember;
use App\Filament\Resources\ClergyMembers\Pages\EditClergyMember;
use App\Filament\Resources\ClergyMembers\Pages\ManageClergyMembers;
use App\Filament\Resources\ClergyMembers\RelationManagers\AssignmentsRelationManager;
use App\Filament\Support\ContentResource;
use App\Models\ClergyMember;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClergyMemberResource extends ContentResource
{
    protected static ?string $model = ClergyMember::class;

    public static string $sourceTable = 'clero';

    public static function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);
        $fields = collect($schema->getComponents())->keyBy(fn (Field $field): string => $field->getName());
        $labels = [
            'cover_path' => 'Foto', 'body_html' => 'Descrição',
            'birthday_text' => 'Data de Aniversário', 'ordination_date_text' => 'Data de Ordenação', 'presentation_date' => 'Data de Apresentação',
            'parish_1_text' => 'Paróquia em que atua', 'parish_2_text' => 'Segunda Paróquia em que atua', 'parish_3_text' => 'Terceira Paróquia em que atua',
            'parish_1_link' => 'Link da Paróquia', 'parish_2_link' => 'Link da Segunda Paróquia', 'parish_3_link' => 'Link da Terceira Paróquia',
            'role_1_text' => 'Função 1', 'role_2_text' => 'Função 2', 'role_3_text' => 'Função 3',
        ];
        foreach ($labels as $name => $label) {
            $fields[$name]->label($label);
        }
        $fields['cover_path']->columnSpanFull();
        $group = fn (array $names): array => array_map(fn (string $name): Field => $fields[$name], $names);

        return $schema->components([
            Section::make('Conteúdo')->schema([
                $fields['cover_path'], $fields['name'], $fields['category_id'],
                Grid::make(['default' => 1, 'md' => 3])->schema($group(['birthday_text', 'ordination_date_text', 'presentation_date']))->columnSpanFull(),
                $fields['body_html'],
            ])->columns(2)->columnSpanFull(),
            Section::make('Paróquias')->schema($group([
                'parish_1_text', 'parish_1_link', 'parish_2_text', 'parish_2_link', 'parish_3_text', 'parish_3_link',
            ]))->columns(2)->columnSpanFull(),
            Section::make('Funções')->schema($group(['role_1_text', 'role_2_text', 'role_3_text', 'is_active']))->columnSpanFull(),
        ])->columns(1);
    }

    public static function getPages(): array
    {
        return ['create' => CreateClergyMember::route('/create'), 'edit' => EditClergyMember::route('/{record}/edit'), 'index' => ManageClergyMembers::route('/')];
    }

    public static function getRelations(): array
    {
        return [AssignmentsRelationManager::class];
    }
}
