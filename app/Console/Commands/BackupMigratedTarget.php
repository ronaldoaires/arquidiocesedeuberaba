<?php

namespace App\Console\Commands;

use App\Services\Migration\SnapshotManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupMigratedTarget extends Command
{
    protected $signature = 'legacy:backup-target';

    protected $description = 'Cria backup do destino e verifica sua restauração em banco isolado';

    public function handle(SnapshotManager $snapshots): int
    {
        $connection = config('database.connections.mysql');
        if ($connection['database'] !== 'arquid68_app') {
            throw new RuntimeException('Unexpected target database.');
        }
        $id = gmdate('Ymd_His').'_'.bin2hex(random_bytes(4));
        $root = storage_path('app/private/migration/target-backups/'.$id);
        File::ensureDirectoryExists($root);
        $options = $root.'/client.cnf';
        $content = "[client]\n";
        foreach (['host', 'port', 'username', 'password'] as $key) {
            $name = $key === 'username' ? 'user' : $key;
            $value = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $connection[$key]);
            $content .= $name.'="'.$value."\"\n";
        }
        File::put($options, $content);
        $manifest = ['id' => $id, 'database' => $connection['database'], 'tables' => [], 'files' => []];
        $target = DB::connection('mysql');
        $locked = false;
        try {
            $target->statement('FLUSH TABLES WITH READ LOCK');
            $locked = true;
            $dump = new Process([config('migration.mysqldump'), '--defaults-extra-file='.$options, '--default-character-set=utf8mb4', '--skip-lock-tables', '--routines', '--events', '--triggers', '--hex-blob', '--set-gtid-purged=OFF', '--no-tablespaces', '--result-file='.$root.'/database.sql', $connection['database']]);
            $dump->setTimeout(600);
            $dump->mustRun();
            foreach ($target->select('SHOW FULL TABLES') as $table) {
                $name = array_values((array) $table)[0];
                $rows = $snapshots->rows($target->getPdo(), $name);
                $manifest['tables'][$name] = ['rows' => count($rows), 'sha256' => hash('sha256', serialize($rows))];
            }
            foreach (['public', 'private/contact-attachments'] as $directory) {
                $source = storage_path('app/'.$directory);
                if (! is_dir($source)) {
                    continue;
                }
                foreach (File::allFiles($source) as $file) {
                    $relative = $directory.'/'.str_replace('\\', '/', $file->getRelativePathname());
                    $destination = $root.'/files/'.$relative;
                    File::ensureDirectoryExists(dirname($destination));
                    if (! copy($file->getPathname(), $destination) || hash_file('sha256', $file->getPathname()) !== hash_file('sha256', $destination)) {
                        throw new RuntimeException('Backup file hash mismatch.');
                    }
                    $manifest['files'][$relative] = ['bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $destination)];
                }
            }
            $target->statement('UNLOCK TABLES');
            $locked = false;
            $database = 'arquid_target_restore_'.$id;
            $target->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $restore = new Process([config('migration.mysql'), '--defaults-extra-file='.$options, '--default-character-set=utf8mb4', $database]);
            $restore->setInput(fopen($root.'/database.sql', 'rb'));
            $restore->setTimeout(600);
            $restore->mustRun();
            config(['database.connections.target_restore' => array_replace($connection, ['database' => $database])]);
            DB::purge('target_restore');
            foreach ($manifest['tables'] as $table => $expected) {
                $rows = $snapshots->rows(DB::connection('target_restore')->getPdo(), $table);
                if (count($rows) !== $expected['rows'] || hash('sha256', serialize($rows)) !== $expected['sha256']) {
                    throw new RuntimeException('Target restoration mismatch: '.$table);
                }
            }
            $manifest['restored_database'] = $database;
            $manifest['restore_verified'] = true;
            $manifest['dump_sha256'] = hash_file('sha256', $root.'/database.sql');
            File::put($root.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            File::put(storage_path('app/private/migration/current-target-backup.json'), json_encode(['path' => $root], JSON_THROW_ON_ERROR));
            $this->info('Backup e restauração conferidos: '.count($manifest['tables']).' tabelas, '.count($manifest['files']).' arquivos.');

            return self::SUCCESS;
        } finally {
            if ($locked) {
                $target->statement('UNLOCK TABLES');
            }
            @unlink($options);
        }
    }
}
