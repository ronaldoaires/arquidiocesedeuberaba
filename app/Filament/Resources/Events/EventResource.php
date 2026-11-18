<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\ManageEvents;
use App\Filament\Support\ContentResource;
use App\Models\Event;

class EventResource extends ContentResource
{
    protected static ?string $model = Event::class;

    public static string $sourceTable = 'evento';

    public static function getPages(): array
    {
        return ['index' => ManageEvents::route('/')];
    }
}
