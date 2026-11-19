<?php

namespace App\Filament\Support;

use App\Models\User;
use App\Services\MediaLifecycle;
use App\Services\Newsletter\QuickCampaign;
use App\Support\ContentRegistry;
use App\Support\PublicMedia;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

abstract class ContentResource extends Resource
{
    public static string $sourceTable;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_active && $user->legacy_level === 3;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canRestore(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function getModelLabel(): string
    {
        return ContentRegistry::get(static::$sourceTable)['singular'];
    }

    public static function getPluralModelLabel(): string
    {
        return ContentRegistry::get(static::$sourceTable)['label'];
    }

    public static function getNavigationGroup(): ?string
    {
        return match (static::$sourceTable) {
            'usuario', 'config' => 'Administração',
            'paroquia','paroquia_cidade','paroquia_forania','paroquia_clero','clero','clero_categoria','instituto','instituto_categoria' => 'Institucional',
            'pastoral_downloads','pastoral_videos','pastoral_galeria','pastoral_galeria_foto' => 'Pastorais',
            'pastoral','galeria','galeria_foto','jornal','premio','radio','radio_musica','radio_programa' => 'Mídias',
            default => 'Conteúdo',
        };
    }

    public static function form(Schema $schema): Schema
    {
        if (static::$sourceTable === 'usuario') {
            return $schema->components([
                FileUpload::make('cover_path')->label('Foto')->image()->imageEditor()->disk('public')->directory('content/users')->visibility('public')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/avif'])->maxSize(51200)->fetchFileInformation(false)->columnSpanFull(),
                TextInput::make('name')->label('Nome')->required()->maxLength(191),
                TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true)->maxLength(191),
                TextInput::make('password')->label('Senha')->password()->revealable()->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
                Select::make('legacy_level')->label('Nível de acesso')->options([1 => 'Usuário', 3 => 'Administrador'])->required(),
                Toggle::make('is_active')->label('Ativo')->default(true),
            ])->columns(2);
        }
        $module = ContentRegistry::get(static::$sourceTable);
        $contract = config('admin.'.static::$sourceTable, []);
        if (in_array(static::$sourceTable, ['paroquia', 'artigo', 'noticia', 'clero'], true)) {
            $contract = array_values(array_diff($contract, ['is_active']));
        }
        $definitions = array_column($module['columns'], null, 'name');
        $components = [];
        foreach ($contract as $name) {
            $column = $definitions[$name];
            if ($column['relation']) {
                $related = ContentRegistry::get($column['relation']);
                $class = ContentRegistry::model($column['relation']);
                $field = Select::make($name)->searchable()->options(fn (): array => $class::query()->orderBy($related['title'])->get(['id', $related['title']])->mapWithKeys(fn (Model $record): array => [$record->id => (string) ($record->getAttribute($related['title']) ?: 'Sem título')])->all());
                if (! in_array($name, ['created_by', 'category_id'], true)) {
                    $field->required();
                }
            } elseif (static::$sourceTable === 'paroquia' && in_array($name, ['service_hours', 'office_hours'], true)) {
                $field = PreservingRichEditor::make($name)->toolbarButtons([
                    ['bold', 'italic', 'underline'], ['bulletList', 'orderedList'], ['textColor', 'highlight'], ['link'],
                ])->columnSpanFull();
            } elseif (static::$sourceTable === 'clero' && in_array($name, ['role_1_text', 'role_2_text', 'role_3_text'], true)) {
                $field = Radio::make($name)->inline()->options(function (?Model $record) use ($name): array {
                    $options = ['' => 'Não tem', 'Pároco' => 'Pároco', 'Vigário Paroquial' => 'Vigário Paroquial', 'Uso de Ordem' => 'Uso de Ordem', 'Diácono Permanente' => 'Diácono Permanente', 'Administrador Paroquial' => 'Administrador Paroquial', 'Capelão' => 'Capelão'];
                    if (filled($current = $record?->getAttribute($name))) {
                        $options[$current] = $current;
                    }

                    return $options;
                })->columnSpanFull();
            } elseif (static::$sourceTable === 'paroquia_clero' && $name === 'role_text') {
                $field = Select::make($name)->placeholder('— (Nenhuma) —')->options(fn (?Model $record): array => ParishClergySelection::roles($record?->role_text));
            } elseif ($name === 'body_html') {
                $field = PreservingRichEditor::make($name)->plugins([new DocumentAttachmentPlugin])->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('editor/'.$module['table'])->fileAttachmentsVisibility('public')->fileAttachmentsMaxSize(51200)
                    ->saveUploadedFileAttachmentUsing(function (TemporaryUploadedFile $file, PreservingRichEditor $component): string {
                        $path = $file->store($component->getFileAttachmentsDirectory(), 'public');
                        app(MediaLifecycle::class)->rememberPending($component->getLivewire()->getId(), $path);

                        return $path;
                    })->resizableImages()->columnSpanFull();
            } elseif (in_array($name, ['cover_path', 'photo_path', 'file_path'], true)) {
                $field = FileUpload::make($name)->disk('public')->directory('content/'.$module['table'])->visibility('public')->maxSize(51200)->fetchFileInformation(false);
                if ($name !== 'file_path') {
                    $field->image()->imageEditor();
                }
                if ($name === 'photo_path' && static::isPhotoResource()) {
                    $field->multiple()->required()->visible(fn (string $operation): bool => $operation === 'create')->columnSpanFull();
                }
            } elseif ($name === 'menu_group') {
                $field = Select::make($name)->options(['' => 'Selecione', 'arquidiocese' => 'Arquidiocese', 'clero' => 'Clero', 'pastoral' => 'Pastoral Arquidiocesana']);
            } elseif ($name === 'is_active') {
                $field = Toggle::make($name)->default(true);
            } elseif ($name === 'created_at') {
                $field = DateTimePicker::make($name)->timezone('America/Sao_Paulo')->default(now());
            } elseif ($column['type'] === 'date') {
                $field = DatePicker::make($name);
                if ($name === 'presentation_date') {
                    $field->native(false)->displayFormat('d/m/Y')->format('Y-m-d')->rule('date_format:Y-m-d')->minDate('1000-01-01')->maxDate('9999-12-31');
                }
            } elseif ($name === 'day' && static::$sourceTable === 'radio_programa') {
                $field = Select::make($name)->options([0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado']);
            } elseif ($name === 'month') {
                $field = Select::make($name)->options([1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro']);
            } elseif ($name === 'type' && static::$sourceTable === 'pastoral_downloads') {
                $field = Select::make($name)->options(['pdf' => 'PDF', 'mp3' => 'MP3']);
            } elseif (str_contains($column['type'], 'text') || $name === 'map_html') {
                $field = Textarea::make($name)->rows(4)->columnSpanFull();
            } else {
                $maxLength = preg_match('/(?:var)?char\((\d+)\)/', $column['type'], $match) ? (int) $match[1] : 1024;
                $field = TextInput::make($name)->maxLength($maxLength);
                if (preg_match('/int|year/', $column['type'])) {
                    $field->numeric();
                }
            }
            if ($field instanceof TextInput && (in_array($name, ['site', 'facebook', 'instagram', 'youtube', 'x', 'link'], true) || str_ends_with($name, '_link'))) {
                $field->url()->rules(['url:http,https'])->placeholder('https://')->validationMessages(['url' => 'Informe um endereço válido, começando com http:// ou https://.']);
            }
            if (static::$sourceTable === 'paroquia' && $name === 'email' && $field instanceof TextInput) {
                $field->email();
            }
            $field->label(static::$sourceTable === 'paroquia' ? (['service_hours' => 'Horários de Atendimento da Secretaria Paroquial', 'office_hours' => 'Horário de Missa'][$name] ?? $column['label']) : $column['label']);
            if (in_array(static::$sourceTable, ['artigo', 'noticia'], true)) {
                $field->label(['created_at' => 'Data de Publicação', 'body_html' => 'Texto'][$name] ?? $column['label']);
            }
            if ($name === $module['title'] && $name !== 'id') {
                $field->required();
            }
            $components[] = $field;
        }
        if (static::isPhotoResource()) {
            $components[] = ImageEntry::make('photo_preview')->label('Foto')->disk('public')->visibility('public')->imageHeight(180)
                ->state(fn (?Model $record): ?string => $record?->photo_path)->visible(fn (string $operation): bool => $operation === 'edit')->columnSpanFull();
        }
        if (static::$sourceTable === 'paroquia') {
            $components[] = ParishClergySelection::make('assignments');
        }
        if (in_array(static::$sourceTable, ['paroquia', 'artigo', 'noticia', 'clero'], true)) {
            $components[] = Toggle::make('is_active')->label('Ativo')->default(true)->columnSpanFull();
        }
        if (in_array(static::$sourceTable, ['noticia', 'artigo', 'evento'], true)) {
            $components[] = Toggle::make('send_newsletter')->label('Enviar por newsletter ao cadastrar')
                ->helperText('Cria uma campanha para este conteúdo.')
                ->default(false)->visible(fn (string $operation): bool => $operation === 'create')->columnSpanFull();
        }

        return $schema->components($components)->columns(2);
    }

    public static function createAction(bool $isRelated = false): CreateAction
    {
        if (! $isRelated && static::hasPage('create')) {
            return CreateAction::make()->modelLabel(static::getModelLabel())->pluralModelLabel(static::getPluralModelLabel())->url(static::getUrl('create'));
        }

        return CreateAction::make()->modelLabel(static::getModelLabel())->pluralModelLabel(static::getPluralModelLabel())->createAnother(false)->successRedirectUrl(fn (Component $livewire): ?string => $livewire instanceof RelatedContentManager ? null : static::getUrl('index'))
            ->using(function (array $data, string $model, CreateAction $action): Model {
                $sendNewsletter = (bool) ($data['send_newsletter'] ?? false);
                unset($data['send_newsletter']);
                $paths = static::isPhotoResource() ? array_values((array) ($data['photo_path'] ?? [])) : [null];
                $first = null;
                foreach ($paths as $path) {
                    $attributes = $data;
                    if (static::isPhotoResource()) {
                        $attributes['photo_path'] = $path;
                    }
                    $record = new $model;
                    if ($record instanceof User) {
                        $record->forceFill($attributes);
                    } else {
                        $record->fill($attributes);
                    }
                    if ($relationship = $action->getRelationship()) {
                        $relationship->save($record);
                    } else {
                        $record->save();
                    }
                    $first ??= $record;
                }

                if ($sendNewsletter && $first instanceof \App\Models\ContentModel) {
                    app(QuickCampaign::class)->send($first);
                }

                return $first;
            })->after(fn (Component $livewire) => app(MediaLifecycle::class)->finishForm($livewire->getId()));
    }

    public static function isPhotoResource(): bool
    {
        return in_array(static::$sourceTable, ['galeria_foto', 'pastoral_galeria_foto'], true);
    }

    public static function table(Table $table): Table
    {
        $module = ContentRegistry::get(static::$sourceTable);
        $columns = [];
        foreach ($module['columns'] as $column) {
            if (static::$sourceTable === 'instituto_categoria' || ! in_array($column['name'], ['cover_path', 'photo_path'], true)) {
                continue;
            }
            $name = $column['name'];
            $image = ImageColumn::make($name)->label(in_array(static::$sourceTable, ['clero', 'usuario'], true) ? 'Foto' : $column['label'])->disk('public')->visibility('public')->state(fn (Model $record): ?string => PublicMedia::thumbnailPath($record->getAttribute($name)) ?: (static::$sourceTable === 'pastoral_videos' ? $record->video_thumbnail : null))->imageSize(48);
            $columns[] = in_array(static::$sourceTable, ['usuario', 'clero'], true) ? $image->circular() : $image->square();
        }
        if (static::$sourceTable === 'paroquia_clero') {
            $columns[] = TextColumn::make('parish.name')->label('Paróquia')->searchable()->sortable();
            $columns[] = TextColumn::make('clergyMember.name')->label('Clero')->searchable()->sortable();
            $columns[] = TextColumn::make('role_text')->label('Função');
        } elseif (static::isPhotoResource()) {
            $columns[] = TextColumn::make('gallery.title')->label('Galeria')->searchable();
            if (static::$sourceTable === 'galeria_foto') {
                $columns[] = TextColumn::make('author_name')->label('Autor')->searchable();
            }
        } else {
            $name = TextColumn::make($module['title'])->label($module['title'] === 'name' ? 'Nome' : 'Título')->searchable()->sortable()->limit(65);
            if (static::$sourceTable === 'pastoral_videos') {
                $name->state(fn (Model $record): string => $record->video_title)->description(fn (Model $record): ?string => $record->video_id)->wrap()->searchable(['title', 'video_id']);
            }
            if (static::$sourceTable === 'clero') {
                $name->wrap()->limit(null)->description(fn (Model $record): ?string => collect([
                    $record->role_1_text, $record->role_2_text, $record->role_3_text,
                ])->map(fn ($role): string => trim((string) $role))->filter(fn (string $role): bool => $role !== '')->unique()->implode(' · ') ?: null);
            }
            if (in_array(static::$sourceTable, ['pagina', 'jornal', 'evento', 'usuario'], true)) {
                $name->wrap()->limit(null)->extraAttributes(['style' => 'max-width: 16rem; overflow-wrap: anywhere;']);
            }
            $columns[] = $name;
        }
        if (static::$sourceTable === 'usuario') {
            $columns[] = TextColumn::make('email')->label('E-mail')->searchable()->sortable()->wrap()->extraAttributes(['style' => 'max-width: 18rem; overflow-wrap: anywhere;']);
            $columns[] = TextColumn::make('legacy_level')->label('Nível de acesso')->badge()->formatStateUsing(fn (?int $state): string => match ($state) {
                3 => 'Administrador', 1 => 'Usuário', null => 'Não informado', default => 'Nível '.$state,
            })->color(fn (?int $state): string => $state === 3 ? 'danger' : 'gray')->sortable();
        }
        if (static::$sourceTable === 'pagina') {
            $columns[] = TextColumn::make('menu_group')->label('Menu')->formatStateUsing(fn (?string $state): ?string => match ($state) {
                'arquidiocese' => 'Arquidiocese', 'clero' => 'Clero', 'pastoral' => 'Pastoral Arquidiocesana', default => $state,
            })->wrap()->extraAttributes(['style' => 'max-width: 9rem; overflow-wrap: anywhere;'])->searchable()->sortable();
        }
        if (in_array(static::$sourceTable, ['noticia', 'clero', 'pagina', 'instituto'], true)) {
            $category = TextColumn::make('category.title')->label('Categoria')->placeholder('Sem categoria')->searchable()->sortable();
            if (static::$sourceTable === 'pagina') {
                $category->wrap()->extraAttributes(['style' => 'max-width: 10rem; overflow-wrap: anywhere;']);
            }
            $columns[] = $category;
        }
        if (static::$sourceTable === 'premio') {
            $columns[] = TextColumn::make('category_text')->label('Categoria')->searchable()->sortable()->wrap();
            $columns[] = TextColumn::make('year')->label('Ano')->sortable();
        }
        if (static::$sourceTable === 'evento') {
            $columns[] = TextColumn::make('starts_on')->label('Data de início')->date('d/m/Y')->sortable();
            $columns[] = TextColumn::make('ends_on')->label('Data de fim')->date('d/m/Y')->sortable();
        }
        if (static::$sourceTable === 'pastoral_downloads') {
            $columns[] = TextColumn::make('downloads')->label('Downloads')->default(0)->numeric(decimalPlaces: 0, locale: 'pt_BR')->sortable();
        }
        if (static::$sourceTable === 'jornal') {
            $columns[] = TextColumn::make('edition')->label('Edição')->sortable();
            $columns[] = TextColumn::make('publication_period')->label('Mês/Ano')->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('year', $direction)->orderBy('month', $direction));
            $columns[] = TextColumn::make('downloads')->label('Downloads')->default(0)->numeric(decimalPlaces: 0, locale: 'pt_BR')->sortable();
        }
        if (static::$sourceTable === 'galeria') {
            $columns[] = TextColumn::make('photos_count')->label('Fotos')->counts('photos')->numeric(decimalPlaces: 0, locale: 'pt_BR')->sortable();
        }
        if (static::$sourceTable === 'pastoral_downloads' || in_array('visits', array_column($module['columns'], 'name'), true)) {
            $columns[] = TextColumn::make('visits')->label('Visualizações')->default(0)->numeric(decimalPlaces: 0, locale: 'pt_BR')->sortable();
        }
        $filters = [];
        if (static::$sourceTable === 'pastoral_downloads' || in_array('visits', array_column($module['columns'], 'name'), true)) {
            $filters[] = Filter::make('visits')->label('Visualizações')->schema([
                TextInput::make('minimum')->label('Visualizações mínimas')->numeric()->step(1)->minValue(0)->rule('integer'),
                TextInput::make('maximum')->label('Visualizações máximas')->numeric()->step(1)->minValue(0)->rule('integer'),
            ])->query(function (Builder $query, array $data): Builder {
                $column = $query->getModel()->qualifyColumn('visits');
                foreach (['minimum' => '>=', 'maximum' => '<='] as $field => $operator) {
                    $value = filter_var($data[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                    if ($value !== false) {
                        $query->whereRaw('COALESCE('.$column.', 0) '.$operator.' ?', [$value]);
                    }
                }

                return $query;
            })->indicateUsing(function (array $data): array {
                $indicators = [];
                foreach (['minimum' => 'a partir de ', 'maximum' => 'até '] as $field => $label) {
                    $value = filter_var($data[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                    if ($value !== false) {
                        $indicators[] = Indicator::make('Visualizações: '.$label.number_format($value, 0, ',', '.'))->removeField($field);
                    }
                }

                return $indicators;
            });
        }
        if (in_array('is_active', array_column($module['columns'], 'name'), true)) {
            $columns[] = ToggleColumn::make('is_active')->label('Ativo')->tooltip(fn (bool $state): string => $state ? 'Desativar' : 'Ativar')->disabled(fn (Model $record): bool => ! static::canEdit($record) || (method_exists($record, 'trashed') && $record->trashed()))
                ->updateStateUsing(function (Model $record, bool $state): bool {
                    abort_unless(static::canEdit($record) && (! method_exists($record, 'trashed') || ! $record->trashed()), 403);
                    DB::transaction(function () use ($record, $state): void {
                        if (! $record->forceFill(['is_active' => $state])->save()) {
                            throw new RuntimeException('Unable to save the activity status.');
                        }
                    });
                    Notification::make()->title($state ? 'Status ativado' : 'Status desativado')->success()->send();

                    return $state;
                });
            $filters[] = TernaryFilter::make('is_active')->label('Ativo');
        }
        $actions = [EditAction::make()->iconButton()->size(Size::ExtraLarge)->iconSize(IconSize::ExtraLarge)->tooltip('Editar')->url(fn (Model $record, Component $livewire): ?string => (! ($livewire instanceof RelatedContentManager) && static::hasPage('create') && static::hasPage('edit')) ? static::getUrl('edit', ['record' => $record]) : null)->schema(fn (Schema $schema): Schema => static::form($schema))
            ->successRedirectUrl(fn (Component $livewire): ?string => $livewire instanceof RelatedContentManager ? null : static::getUrl('index'))->using(function (Model $record, array $data): Model {
                if ($record instanceof User) {
                    $record->forceFill($data)->save();
                } else {
                    $record->update($data);
                }

                return $record;
            })->after(fn (Component $livewire) => app(MediaLifecycle::class)->finishForm($livewire->getId()))];

        if (static::$sourceTable === 'pastoral_videos') {
            array_unshift($actions, Action::make('watch')->label('Assistir vídeo')->icon('heroicon-o-play-circle')->iconButton()->size(Size::ExtraLarge)->tooltip('Assistir vídeo')
                ->modalHeading(fn (Model $record): string => $record->video_title)->modalWidth('5xl')->modalSubmitAction(false)->modalCancelActionLabel('Fechar')
                ->modalContent(fn (Model $record) => view('filament.pastoral-video-preview', ['video' => $record])));
        }
        $actions[] = DeleteAction::make()->iconButton()->size(Size::ExtraLarge)->iconSize(IconSize::ExtraLarge)->tooltip('Excluir');

        return $table->modelLabel(static::getModelLabel())->pluralModelLabel(static::getPluralModelLabel())->columns($columns)->filters($filters)->recordActions($actions)->defaultSort('id', 'desc');
    }
}
