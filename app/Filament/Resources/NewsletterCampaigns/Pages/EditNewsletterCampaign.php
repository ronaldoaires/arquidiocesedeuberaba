<?php

namespace App\Filament\Resources\NewsletterCampaigns\Pages;

use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use App\Models\NewsletterCampaign;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditNewsletterCampaign extends EditRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected static ?string $title = 'Editar campanha';

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $locked = NewsletterCampaign::lockForUpdate()->findOrFail($record->id);
        abort_if($locked->dispatched_at, 403);

        return parent::handleRecordUpdate($locked, $data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
