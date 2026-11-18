<?php

namespace App\Filament\Support;

use App\Services\MediaLifecycle;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;

abstract class EditContentRecord extends EditRecord
{
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        app(MediaLifecycle::class)->finishForm($this->getId());
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->iconButton()->size(Size::ExtraLarge)->iconSize(IconSize::ExtraLarge)->tooltip('Excluir')->successRedirectUrl(static::getResource()::getUrl('index'))];
    }
}
