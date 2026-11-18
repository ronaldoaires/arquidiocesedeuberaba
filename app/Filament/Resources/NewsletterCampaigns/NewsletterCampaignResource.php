<?php

namespace App\Filament\Resources\NewsletterCampaigns;

use App\Filament\Clusters\Newsletter;
use App\Filament\Resources\NewsletterCampaigns\Pages\CreateNewsletterCampaign;
use App\Filament\Resources\NewsletterCampaigns\Pages\EditNewsletterCampaign;
use App\Filament\Resources\NewsletterCampaigns\Pages\ListNewsletterCampaigns;
use App\Filament\Resources\NewsletterCampaigns\Pages\NewsletterDeliveryHistory;
use App\Filament\Resources\NewsletterCampaigns\Pages\ReviewNewsletterCampaign;
use App\Models\NewsletterCampaign;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterCampaignResource extends Resource
{
    protected static ?string $model = NewsletterCampaign::class;

    protected static ?string $cluster = Newsletter::class;

    protected static ?string $modelLabel = 'campanha';

    protected static ?string $pluralModelLabel = 'Campanhas';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?string $slug = 'campanhas';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return Newsletter::canAccess();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return $record->dispatched_at ? Response::deny('Esta campanha já foi confirmada.') : parent::getEditAuthorizationResponse($record);
    }

    public static function form(Schema $schema): Schema
    {
        return CampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('subject')->label('Assunto')->searchable()->sortable()->wrap(),
            TextColumn::make('status')->label('Situação')->state(fn (NewsletterCampaign $record): string => $record->completed_at ? 'Concluída' : ($record->dispatched_at ? 'Em processamento' : 'Rascunho'))->badge(),
            TextColumn::make('dispatched_at')->label('Envio confirmado em')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('updated_at')->label('Atualizada em')->dateTime('d/m/Y H:i')->sortable(),
        ])->recordActions([
            EditAction::make()->label('Editar')->url(fn (NewsletterCampaign $record): string => static::getUrl('edit', ['record' => $record])),
            CampaignActions::preview(),
            CampaignActions::sendTest()->visible(fn (NewsletterCampaign $record): bool => ! $record->dispatched_at),
            Action::make('review')->label('Revisar envio')->visible(fn (NewsletterCampaign $record): bool => ! $record->dispatched_at)
                ->url(fn (NewsletterCampaign $record): string => static::getUrl('review', ['record' => $record])),
            Action::make('history')->label('Histórico')->visible(fn (NewsletterCampaign $record): bool => (bool) $record->dispatched_at)
                ->url(fn (NewsletterCampaign $record): string => static::getUrl('history', ['record' => $record])),
            ReplicateAction::make()->excludeAttributes(NewsletterCampaign::DISPATCH_FIELDS)->label('Duplicar')->modalHeading('Duplicar campanha')->modalSubmitActionLabel('Duplicar')
                ->beforeReplicaSaved(function (NewsletterCampaign $replica): void {
                    $replica->subject = Str::limit('Cópia de '.$replica->subject, 255, '');
                })->successNotificationTitle('Rascunho duplicado'),
        ])->defaultSort('updated_at', 'desc')->emptyStateHeading('Nenhuma campanha cadastrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterCampaigns::route('/'),
            'create' => CreateNewsletterCampaign::route('/criar'),
            'review' => ReviewNewsletterCampaign::route('/{record}/revisar'),
            'history' => NewsletterDeliveryHistory::route('/{record}/historico'),
            'edit' => EditNewsletterCampaign::route('/{record}/editar'),
        ];
    }
}
