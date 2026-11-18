<?php

namespace App\Filament\Resources\Parishes\Pages;

use App\Filament\Resources\Parishes\ParishResource;
use App\Filament\Support\EditContentRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Components\Attributes\Unexposed;

class EditParish extends EditContentRecord
{
    protected static string $resource = ParishResource::class;

    #[Unexposed]
    public function getFormContentComponent(): Component
    {
        return Section::make()->schema([parent::getFormContentComponent()])->columnSpanFull();
    }
}
