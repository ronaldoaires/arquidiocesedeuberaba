<?php

namespace App\Filament\Support;

use App\Services\MediaLifecycle;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasToolbarButtons;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentAttachmentPlugin implements HasToolbarButtons, RichContentPlugin
{
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    public function getEnabledToolbarButtons(): array
    {
        return ['insertDocument'];
    }

    public function getDisabledToolbarButtons(): array
    {
        return [];
    }

    public function getEditorTools(): array
    {
        return [RichEditorTool::make('insertDocument')->label('Enviar documento e inserir link')->icon('heroicon-o-paper-clip')->action()];
    }

    public function getEditorActions(): array
    {
        return [
            Action::make('insertDocument')->label('Inserir documento')->modalHeading('Enviar documento e inserir link')->schema([
                TextInput::make('label')->label('Texto do link')->required()->maxLength(255),
                FileUpload::make('file')->label('Arquivo')->disk('public')->directory('editor/documents')->visibility('public')->required()->maxSize(51200)
                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, FileUpload $component): string {
                        $path = $file->store('editor/documents', 'public');
                        app(MediaLifecycle::class)->rememberPending($component->getLivewire()->getId(), $path);

                        return $path;
                    })
                    ->acceptedFileTypes(['application/pdf', 'text/plain', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'application/xml', 'text/xml', 'application/zip']),
            ])->action(function (array $arguments, array $data, RichEditor $component): void {
                $component->runCommands([
                    EditorCommand::make('insertContent', arguments: [[
                        'type' => 'text', 'text' => $data['label'],
                        'marks' => [['type' => 'link', 'attrs' => ['href' => Storage::disk('public')->url($data['file']), 'target' => '_blank', 'rel' => 'noopener noreferrer']]],
                    ]]),
                ], editorSelection: $arguments['editorSelection'] ?? null);
            }),
        ];
    }
}
