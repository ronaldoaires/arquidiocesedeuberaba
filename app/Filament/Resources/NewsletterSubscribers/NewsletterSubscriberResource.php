<?php

namespace App\Filament\Resources\NewsletterSubscribers;

use App\Filament\Clusters\Newsletter;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $cluster = Newsletter::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Inscritos';

    protected static ?string $modelLabel = 'inscrito';

    protected static ?string $pluralModelLabel = 'Inscritos na newsletter';

    protected static ?string $slug = 'inscritos';

    public static function canViewAny(): bool
    {
        return Newsletter::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('email')->label('E-mail')->searchable()->sortable()->wrap(),
            TextColumn::make('name')->label('Nome')->searchable()->sortable(),
            TextColumn::make('city')->label('Cidade')->searchable(),
            TextColumn::make('state')->label('UF'),
            TextColumn::make('status')->label('Situação')->badge()
                ->formatStateUsing(fn (string $state): string => NewsletterSubscriber::STATUS_LABELS[$state]),
            TextColumn::make('created_at')->label('Solicitado em')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('verified_at')->label('Confirmado em')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->label('Situação')->options(NewsletterSubscriber::STATUS_LABELS),
            SelectFilter::make('state')->label('Estado')->options(array_combine(config('newsletter.states'), config('newsletter.states'))),
        ])->defaultSort('created_at', 'desc')->emptyStateHeading('Nenhum inscrito encontrado');
    }

    public static function getPages(): array
    {
        return ['index' => ListNewsletterSubscribers::route('/')];
    }
}
