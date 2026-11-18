<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

class InspectMigrationEnvironment extends Command
{
    protected $signature = 'legacy:inspect-environment {--json : Retorna o diagnóstico em JSON}';

    protected $description = 'Verifica origem e destino da migração sem alterar tabelas ou registros';

    public function handle(): int
    {
        $sourceName = 'legacy';
        $targetName = config('database.default');

        if ($targetName !== 'mysql') {
            $this->error('O destino precisa usar a conexão MySQL configurada para o projeto novo.');

            return self::FAILURE;
        }

        try {
            $source = DB::connection($sourceName);
            $target = DB::connection($targetName);
            $sourceIdentity = $source->selectOne('SELECT @@server_uuid AS server_uuid, DATABASE() AS database_name');
            $targetIdentity = $target->selectOne('SELECT @@server_uuid AS server_uuid, DATABASE() AS database_name');

            if ($sourceIdentity->server_uuid === $targetIdentity->server_uuid
                && $sourceIdentity->database_name === $targetIdentity->database_name) {
                $this->error('Origem e destino apontam para o mesmo banco. Operação recusada.');

                return self::FAILURE;
            }

            $report = [
                'mode' => 'read_only_diagnostic',
                'consistent_snapshot' => false,
                'source' => $this->inspect($source),
                'target' => $this->inspect($target),
            ];
        } catch (Throwable) {
            $this->error('Não foi possível consultar os bancos. Verifique as conexões; nenhuma migração foi executada.');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->info('Diagnóstico somente de leitura concluído. Isto não substitui um backup consistente.');
            foreach (['source' => 'Origem', 'target' => 'Destino'] as $key => $label) {
                $database = $report[$key];
                $this->line($label.': '.$database['database'].' — '.count($database['tables']).' tabelas');
                $this->table(['Tabela', 'Engine', 'Registros'], array_map(
                    fn (array $table): array => [$table['name'], $table['engine'], $table['rows']],
                    $database['tables'],
                ));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{database: string, tables: list<array{name: string, engine: ?string, rows: int}>}
     */
    private function inspect(Connection $connection): array
    {
        $tables = $connection->select(
            'SELECT TABLE_NAME AS table_name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME',
        );

        return [
            'database' => $connection->getDatabaseName(),
            'tables' => array_map(function (object $table) use ($connection): array {
                $identifier = '`'.str_replace('`', '``', $table->table_name).'`';
                $count = $connection->selectOne('SELECT COUNT(*) AS aggregate FROM '.$identifier);

                return ['name' => $table->table_name, 'engine' => $table->engine, 'rows' => (int) $count->aggregate];
            }, $tables),
        ];
    }
}
