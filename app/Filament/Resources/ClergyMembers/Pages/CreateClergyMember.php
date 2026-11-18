<?php

namespace App\Filament\Resources\ClergyMembers\Pages;

use App\Filament\Resources\ClergyMembers\ClergyMemberResource;
use App\Services\MediaLifecycle;
use Filament\Resources\Pages\CreateRecord;

class CreateClergyMember extends CreateRecord
{
    protected static string $resource = ClergyMemberResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        app(MediaLifecycle::class)->finishForm($this->getId());
    }
}
