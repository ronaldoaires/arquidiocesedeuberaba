<?php

namespace App\Filament\Resources\NewsletterCampaigns\Pages;

use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use App\Models\NewsletterDelivery;
use App\Services\Newsletter\CampaignDispatch;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NewsletterDeliveryHistory extends ManageRelatedRecords
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Histórico de envio';

    protected static ?string $relationshipTitle = 'Envios';

    public static function canAccess(array $parameters = []): bool
    {
        return NewsletterCampaignResource::canViewAny() && filled(($parameters['record'] ?? null)?->dispatched_at);
    }

    public function getSubheading(): string
    {
        $counts = $this->getRecord()->deliveries()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $summary = [];
        foreach (NewsletterDelivery::LABELS as $status => $label) {
            if ($counts->has($status)) {
                $summary[] = $label.' ('.$counts[$status].')';
            }
        }

        return $this->getRecord()->subject.' · '.implode(' · ', $summary);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('email')->label('Destinatário')->searchable()->sortable()->wrap(),
            TextColumn::make('status')->label('Situação')->badge()
                ->formatStateUsing(fn (string $state): string => NewsletterDelivery::LABELS[$state]),
            TextColumn::make('attempted_at')->label('Tentativa')->dateTime('d/m/Y H:i:s'),
            TextColumn::make('finished_at')->label('Conclusão')->dateTime('d/m/Y H:i:s'),
            TextColumn::make('detail')->label('Observação')->wrap(),
        ])->filters([
            SelectFilter::make('status')->label('Situação')->options(NewsletterDelivery::LABELS),
        ])->recordActions([
            Action::make('retry')->label('Tentar novamente')->visible(fn (NewsletterDelivery $record): bool => $record->status === 'failed')
                ->requiresConfirmation()->modalHeading('Recolocar este destinatário na fila?')
                ->modalSubmitActionLabel('Recolocar na fila')
                ->action(fn (NewsletterDelivery $record) => app(CampaignDispatch::class)->retryFailed($record)),
        ])->defaultSort('id');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Voltar para campanhas')->color('gray')->url(NewsletterCampaignResource::getUrl('index')),
            Action::make('refresh')->label('Atualizar resultados')->action(fn () => $this->resetTable()),
        ];
    }
}
