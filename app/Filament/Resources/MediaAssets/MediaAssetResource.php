<?php

namespace App\Filament\Resources\MediaAssets;

use App\Filament\Resources\MediaAssets\Pages\ManageMediaAssets;
use App\Filament\Support\ContentResource;
use App\Models\MediaAsset;
use App\Support\PublicMedia;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Arquivo de mídia';

    protected static ?string $pluralModelLabel = 'Biblioteca de mídias';

    public static function canViewAny(): bool
    {
        return ContentResource::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('preview')->label('Foto')->disk('public')->visibility('public')->square()->imageSize(48)
                ->state(fn (MediaAsset $record): ?string => PublicMedia::thumbnailPath($record->path)),
            TextColumn::make('path')->label('Caminho')->searchable()->limit(85),
            TextColumn::make('bytes')->label('Tamanho em bytes')->numeric()->sortable(),
            TextColumn::make('sha256')->label('SHA-256')->copyable()->limit(18),
            TextColumn::make('state')->label('Estado')->formatStateUsing(fn (string $state): string => ['available' => 'Disponível', 'recovered_from_public_url' => 'Recuperada', 'archived' => 'Removida'][$state] ?? 'Indisponível'),
        ])->recordActions([Action::make('open')->label('Abrir arquivo')->url(fn (MediaAsset $record): string => Storage::disk('public')->url($record->path))->openUrlInNewTab()->visible(fn (MediaAsset $record): bool => $record->state !== 'archived')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMediaAssets::route('/')];
    }
}
