<?php

namespace App\Filament\Resources\PastoralGroups;

use App\Filament\Resources\PastoralGroups\Pages\EditPastoralGroup;
use App\Filament\Resources\PastoralGroups\Pages\ManagePastoralGroups;
use App\Filament\Resources\PastoralGroups\RelationManagers\DownloadsRelationManager;
use App\Filament\Resources\PastoralGroups\RelationManagers\GalleriesRelationManager;
use App\Filament\Resources\PastoralGroups\RelationManagers\VideosRelationManager;
use App\Filament\Support\ContentResource;
use App\Models\PastoralGroup;

class PastoralGroupResource extends ContentResource
{
    protected static ?string $model = PastoralGroup::class;

    public static string $sourceTable = 'pastoral';

    public static function getPages(): array
    {
        return ['edit' => EditPastoralGroup::route('/{record}/edit'), 'index' => ManagePastoralGroups::route('/')];
    }

    public static function getRelations(): array
    {
        return [DownloadsRelationManager::class, VideosRelationManager::class, GalleriesRelationManager::class];
    }
}
