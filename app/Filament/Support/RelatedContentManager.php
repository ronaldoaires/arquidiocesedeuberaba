<?php

namespace App\Filament\Support;

use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

abstract class RelatedContentManager extends RelationManager
{
    public static string $relatedSource;

    public static string $parentKey;

    public function form(Schema $schema): Schema
    {
        $resource = $this->resourceClass();
        $schema = $resource::form($schema);
        foreach ($schema->getComponents() as $component) {
            if (method_exists($component, 'getName') && $component->getName() === static::$parentKey) {
                $component->hidden()->dehydrated(false);
            }
        }

        return $schema;
    }

    public function table(Table $table): Table
    {
        $resource = $this->resourceClass();

        $create = $resource::createAction(isRelated: true)->schema(fn (Schema $schema): Schema => $this->form($schema));
        if (static::$relatedSource === 'paroquia_clero') {
            $create->label('Adicionar vínculo')->modalHeading(static::$parentKey === 'clergy_member_id' ? 'Adicionar vínculo com paróquia' : 'Adicionar vínculo com clero')->modalSubmitActionLabel('Salvar vínculo');
        }

        return $resource::table($table)->headerActions([$create]);
    }

    private function resourceClass(): string
    {
        foreach (Filament::getCurrentPanel()->getResources() as $resource) {
            if (is_subclass_of($resource, ContentResource::class) && $resource::$sourceTable === static::$relatedSource) {
                return $resource;
            }
        }
        throw new \RuntimeException('Related resource not registered.');
    }
}
