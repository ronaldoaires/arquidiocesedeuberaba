<?php

namespace App\Filament\Resources\NewsItems\Pages;

use App\Filament\Resources\NewsItems\NewsItemResource;
use App\Services\MediaLifecycle;
use App\Services\Newsletter\QuickCampaign;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Components\Attributes\Unexposed;

class CreateNewsItem extends CreateRecord
{
    protected static string $resource = NewsItemResource::class;

    protected static bool $canCreateAnother = false;

    private bool $sendNewsletter = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->sendNewsletter = (bool) ($data['send_newsletter'] ?? false);
        unset($data['send_newsletter']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        app(MediaLifecycle::class)->finishForm($this->getId());
        if ($this->sendNewsletter) {
            app(QuickCampaign::class)->send($this->record);
        }
    }

    #[Unexposed]
    public function getFormContentComponent(): Component
    {
        return Section::make()->schema([parent::getFormContentComponent()])->columnSpanFull();
    }
}
