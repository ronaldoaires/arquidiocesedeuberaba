<?php

namespace App\Filament\Resources\Parishes\Pages;

use App\Filament\Resources\Parishes\ParishResource;
use App\Services\MediaLifecycle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Components\Attributes\Unexposed;

class CreateParish extends CreateRecord
{
    protected static string $resource = ParishResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        app(MediaLifecycle::class)->finishForm($this->getId());
    }

    #[Unexposed]
    public function getFormContentComponent(): Component
    {
        return Section::make()->schema([parent::getFormContentComponent()])->columnSpanFull();
    }
}
