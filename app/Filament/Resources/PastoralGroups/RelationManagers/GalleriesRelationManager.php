<?php

namespace App\Filament\Resources\PastoralGroups\RelationManagers;

use App\Filament\Resources\PastoralGalleries\PastoralGalleryResource;
use App\Filament\Resources\PastoralGalleryPhotos\PastoralGalleryPhotoResource;
use App\Filament\Support\RelatedContentManager;
use App\Models\PastoralGallery;
use App\Models\PastoralGalleryPhoto;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class GalleriesRelationManager extends RelatedContentManager
{
    protected static string $relationship = 'galleries';

    protected static ?string $title = 'Galerias';

    public static string $relatedSource = 'pastoral_galeria';

    public static string $parentKey = 'pastoral_group_id';

    public function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table->pushRecordActions([
            Action::make('addPhotos')
                ->label('Adicionar fotos')
                ->icon('heroicon-o-photo')
                ->button()
                ->visible(fn (PastoralGallery $record): bool => PastoralGalleryPhotoResource::canCreate() && PastoralGalleryResource::canEdit($record))
                ->modalHeading(fn (PastoralGallery $record): string => 'Adicionar fotos: '.$record->title)
                ->modalSubmitActionLabel('Enviar fotos')
                ->schema([
                    FileUpload::make('photo_path')->label('Fotos')->image()->imageEditor()->multiple()->required()
                        ->disk('public')->directory('content/pastoral_gallery_photos')->visibility('public')
                        ->maxSize(51200)->fetchFileInformation(false),
                    Toggle::make('is_active')->label('Ativo')->default(true),
                ])
                ->action(function (PastoralGallery $record, array $data): void {
                    abort_unless(PastoralGalleryPhotoResource::canCreate() && PastoralGalleryResource::canEdit($record), 403);

                    DB::transaction(function () use ($record, $data): void {
                        foreach ($data['photo_path'] as $path) {
                            PastoralGalleryPhoto::create([
                                'gallery_id' => $record->id,
                                'pastoral_group_id' => $record->pastoral_group_id,
                                'photo_path' => $path,
                                'is_active' => $data['is_active'] ?? true,
                            ]);
                        }
                    });
                }),
        ]);
    }
}
