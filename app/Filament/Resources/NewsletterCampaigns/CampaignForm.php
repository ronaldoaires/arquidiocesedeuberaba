<?php

namespace App\Filament\Resources\NewsletterCampaigns;

use App\Filament\Support\PreservingRichEditor;
use App\Services\Newsletter\CampaignAttachments;
use App\Services\Newsletter\CampaignCatalog;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Mensagem')->columnSpanFull()->schema([
                TextInput::make('subject')->label('Assunto')->required()->maxLength(255),
                PreservingRichEditor::make('body_html')->label('Corpo da mensagem')->required()
                    ->toolbarButtons([['bold', 'italic', 'underline'], ['bulletList', 'orderedList'], ['link'], ['undo', 'redo']]),
            ]),
            Section::make('Conteúdos e anexos')->columnSpanFull()->schema([
                ViewField::make('content_keys')->label('Conteúdos selecionados')->default([])
                    ->formatStateUsing(fn (?array $state): array => $state ?? [])
                    ->view('filament.forms.components.newsletter-content')->rules([
                        'array',
                        fn () => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! is_array($value) || app(CampaignCatalog::class)->resolve($value)->count() !== count($value)) {
                                $fail('Revise a seleção e remova os conteúdos duplicados ou indisponíveis.');
                            }
                        },
                    ]),
                FileUpload::make('attachments')->label('Anexos')->multiple()->disk('local')->visibility('private')
                    ->directory(CampaignAttachments::DIRECTORY)->preventFilePathTampering()
                    ->storeFileNamesIn('attachment_names')->previewable(false)
                    ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'])
                    ->maxFiles(3)->maxSize(5120)->helperText('Até 3 arquivos PDF, DOCX, JPG ou PNG, de até 5 MB cada.'),
            ]),
        ]);
    }
}
