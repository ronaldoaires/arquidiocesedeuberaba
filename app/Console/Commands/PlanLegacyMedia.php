<?php

namespace App\Console\Commands;

use App\Services\Migration\CanonicalLegacyMedia;
use App\Services\Migration\LegacyMediaPlan;
use App\Services\Migration\SnapshotManager;
use App\Support\ContentRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PlanLegacyMedia extends Command
{
    protected $signature = 'legacy:plan-media {--snapshot : Usar a última captura restaurada e verificada}';

    protected $description = 'Simula a distribuição dos uploads legados e gera um relatório privado';

    public function handle(SnapshotManager $manager): int
    {
        $snapshot = $this->option('snapshot') ? $manager->current() : null;
        $root = $snapshot ? $snapshot['path'].'/files' : config('migration.source_path');
        $connection = DB::connection($snapshot ? 'legacy_snapshot' : 'legacy');
        $hosts = ['localhost', '127.0.0.1', 'arquidiocesedeuberaba.com.br', 'www.arquidiocesedeuberaba.com.br', 'arquidiocesedeuberaba.org.br', 'www.arquidiocesedeuberaba.org.br', strtolower(parse_url(config('app.url'), PHP_URL_HOST) ?? '')];
        $plan = new LegacyMediaPlan($root, storage_path('app/public'), $hosts);
        $counts = [];
        $tables = array_map(fn (object $row): string => array_values((array) $row)[0], $connection->select('SHOW TABLES'));
        $modules = ContentRegistry::all();
        foreach ($modules as $source => $module) {
            if (! in_array($source, $tables, true) && ! ($module['legacy_available'] ?? true)) {
                continue;
            }
            $rows = $manager->rows($connection->getPdo(), $source);
            if ($snapshot && hash('sha256', serialize($rows)) !== ($snapshot['manifest']['tables'][$source]['sha256'] ?? null)) {
                throw new RuntimeException('Falha de integridade na tabela '.$source);
            }
            foreach ($rows as $row) {
                $plan->scanRecord($source, $module, $row);
            }
            $counts[$source] = count($rows);
        }
        $databasePlan = CanonicalLegacyMedia::includeExistingMedia($plan->report());
        if ($snapshot) {
            if (array_diff($tables, array_keys($counts))) {
                throw new RuntimeException('Há tabelas sem mapeamento na captura.');
            }
            CanonicalLegacyMedia::freeze($snapshot, $databasePlan);
            $media = CanonicalLegacyMedia::load($snapshot);
            foreach ($counts as $source => $count) {
                $module = $modules[$source];
                foreach ($manager->rows($connection->getPdo(), $source) as $row) {
                    foreach ($module['columns'] as $column) {
                        $media->value($source, (string) ($row['id'] ?? ''), $column['name'], $row[$column['source']]);
                    }
                }
            }
        }
        $plan->scanTemplates();
        $report = [
            'mode' => $snapshot ? 'snapshot' : 'live_read_only',
            'snapshot_id' => $snapshot['manifest']['id'] ?? null,
            'generated_at' => now()->toIso8601String(),
            'source_database' => $connection->getDatabaseName(),
            'source_rows' => $counts,
            'unmapped_tables_for_review' => array_values(array_diff($tables, array_keys($modules))),
            'template_references_for_review' => array_values(array_filter($plan->report()['references'], fn (array $ref): bool => isset($ref['template']))),
        ] + $databasePlan;
        $directory = storage_path('app/private/migration');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/media-plan-'.($snapshot ? 'snapshot' : 'live').'.json';
        File::replace($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->line(json_encode($report['summary'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->info('Relatório de simulação: '.$path);
        if ($report['unmapped_tables_for_review']) {
            $this->warn('Tabelas sem mapeamento: '.implode(', ', $report['unmapped_tables_for_review']));
        }

        return self::SUCCESS;
    }
}
