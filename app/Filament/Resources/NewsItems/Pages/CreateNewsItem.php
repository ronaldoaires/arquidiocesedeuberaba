<?php

namespace App\Filament\Resources\NewsItems\Pages;

use App\Filament\Resources\NewsItems\NewsItemResource;
use App\Services\MediaLifecycle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Components\Attributes\Unexposed;

class CreateNewsItem extends CreateRecord
{
    protected static string $resource = NewsItemResource::class;

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
