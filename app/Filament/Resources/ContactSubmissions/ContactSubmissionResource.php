<?php

namespace App\Filament\Resources\ContactSubmissions;

use App\Filament\Resources\ContactSubmissions\Pages\ManageContactSubmissions;
use App\Models\ContactSubmission;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ContactSubmissionResource extends Resource
{
    protected static ?string $model = ContactSubmission::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Mensagem recebida';

    protected static ?string $pluralModelLabel = 'Mensagens recebidas';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_active && $user->legacy_level === 3;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome'),
            TextInput::make('email')->label('E-mail'),
            TextInput::make('subject')->label('Assunto'),
            TextInput::make('delivery_status')->label('Entrega')->formatStateUsing(fn (?string $state): string => ['pending' => 'Pendente', 'sent' => 'Enviada', 'failed' => 'Falhou'][$state] ?? ''),
            Placeholder::make('attachments_list')->label('Anexos')->content(function (?ContactSubmission $record): HtmlString {
                $links = [];
                foreach ($record?->attachments ?? [] as $index => $attachment) {
                    $name = is_array($attachment) ? $attachment['name'] : basename($attachment);
                    $links[] = '<a href="'.e(route('admin.contact-attachments.download', ['submission' => $record->id, 'index' => $index])).'">'.e($name).'</a>';
                }

                return new HtmlString(implode('<br>', $links));
            }),
            Textarea::make('payload_display')->label('Mensagem')->afterStateHydrated(fn ($component, $record) => $component->state(implode("\n\n", array_map(fn (string $key): string => (config('contact.support_fields.'.$key.'.0') ?? ['name' => 'Nome', 'email' => 'E-mail', 'subject' => 'Assunto', 'message' => 'Mensagem', 'phone' => 'Telefone', 'responsibility_accepted' => 'Termo de responsabilidade'][$key] ?? 'Informação').': '.(string) $record->payload[$key], array_keys($record?->payload ?? [])))))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('subject')->label('Assunto')->searchable(),
            TextColumn::make('name')->label('Nome')->searchable(),
            TextColumn::make('email')->label('E-mail'),
            TextColumn::make('delivery_status')->label('Entrega')->formatStateUsing(fn (string $state): string => ['pending' => 'Pendente', 'sent' => 'Enviada', 'failed' => 'Falhou'][$state] ?? $state),
            TextColumn::make('created_at')->label('Recebida em')->dateTime('d/m/Y H:i'),
        ])->recordActions([ViewAction::make()->schema(fn (Schema $schema): Schema => static::form($schema))])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageContactSubmissions::route('/')];
    }
}
