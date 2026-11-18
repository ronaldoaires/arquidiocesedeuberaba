<?php

namespace App\Filament\Resources\RadioTracks;

use App\Filament\Resources\RadioTracks\Pages\ManageRadioTracks;
use App\Filament\Support\ContentResource;
use App\Models\RadioTrack;

class RadioTrackResource extends ContentResource
{
    protected static ?string $model = RadioTrack::class;

    protected static bool $shouldRegisterNavigation = false;

    public static string $sourceTable = 'radio_musica';

    public static function getPages(): array
    {
        return ['index' => ManageRadioTracks::route('/')];
    }
}
