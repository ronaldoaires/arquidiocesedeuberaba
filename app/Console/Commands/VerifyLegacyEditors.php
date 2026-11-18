<?php

namespace App\Console\Commands;

use App\Filament\Support\DocumentAttachmentPlugin;
use App\Filament\Support\PreservingRichEditor;
use App\Filament\Support\PreservingRichEditorStateCast;
use App\Support\ContentRegistry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Livewire\Component;

class VerifyLegacyEditors extends Command
{
    protected $signature = 'legacy:verify-editors {--source=} {--record=}';

    protected $description = 'Testa o ciclo de conversão do editor em todos os textos importados sem salvar alterações';

    public function handle(): int
    {
        $tested = 0;
        $failures = [];
        foreach (ContentRegistry::all() as $source => $module) {
            if ($source === 'usuario' || ($this->option('source') && $this->option('source') !== $source)) {
                continue;
            }
            $class = ContentRegistry::model($source);
            $fields = array_values(array_intersect(array_column($module['columns'], 'name'), ['body_html', 'description']));
            foreach ($class::all() as $record) {
                if ($this->option('record') && (string) $record->id !== $this->option('record')) {
                    continue;
                }
                foreach ($fields as $field) {
                    $original = $record->getRawOriginal($field);
                    if (! is_string($original) || $original === '') {
                        continue;
                    }
                    try {
                        $editor = PreservingRichEditor::make($field)->fileAttachmentsDisk('public')->fileAttachmentsVisibility('public')->resizableImages()->plugins([new DocumentAttachmentPlugin]);
                        $probe = new class extends Component implements HasSchemas
                        {
                            use InteractsWithSchemas;

                            public array $data = [];

                            public function render(): string
                            {
                                return '<div></div>';
                            }
                        };
                        $schema = Schema::make($probe)->model($record)->statePath('data')->components([$editor]);
                        $schema->getComponents();
                        $cast = new PreservingRichEditorStateCast($editor);
                        $converted = $cast->get($cast->set($original));
                        if ($converted !== $original) {
                            $failures[] = ['source' => $source, 'id' => $record->id, 'field' => $field, 'reason' => 'round_trip_changed'];
                        }
                    } catch (\Throwable $exception) {
                        $failures[] = ['source' => $source, 'id' => $record->id, 'field' => $field, 'reason' => $exception::class, 'message' => $exception->getMessage(), 'location' => $exception->getFile().':'.$exception->getLine(), 'trace' => array_map(fn (array $frame): string => ($frame['class'] ?? '').($frame['function'] ?? ''), array_slice($exception->getTrace(), 0, 8))];
                    }
                    $tested++;
                }
            }
        }
        $report = ['tested' => $tested, 'passed' => $failures === [], 'failures' => $failures];
        File::put(storage_path('app/private/migration/editor-report.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->line(json_encode(['tested' => $tested, 'passed' => $failures === [], 'failure_count' => count($failures), 'first_failures' => array_slice($failures, 0, 3)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
