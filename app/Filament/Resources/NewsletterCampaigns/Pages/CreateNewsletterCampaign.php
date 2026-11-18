<?php

namespace App\Filament\Resources\NewsletterCampaigns\Pages;

use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletterCampaign extends CreateRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Nova campanha';

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
