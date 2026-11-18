<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class SnapshotManager
{
    public function create(string $sourcePath): array
    {
        $sourcePath = realpath($sourcePath) ?: throw new RuntimeException('Source directory not found.');
        $source = DB::connection('legacy');
        $target = DB::connection('mysql');
        $sourceIdentity = $source->selectOne('SELECT @@server_uuid AS server_uuid, DATABASE() AS database_name');
        $targetIdentity = $target->selectOne('SELECT @@server_uuid AS server_uuid, DATABASE() AS database_name');
        if ($sourceIdentity == $targetIdentity) {
            throw new RuntimeException('Source and target databases must differ.');
        }
        $id = gmdate('Ymd_His').'_'.bin2hex(random_bytes(4));
        $root = storage_path('app/private/migration/snapshots/'.$id);
        File::ensureDirectoryExists($root.'/files');
        File::ensureDirectoryExists($root.'/records');
        $options = $this->optionsFile($root, config('database.connections.legacy'));
        $manifest = ['id' => $id, 'database' => $source->getDatabaseName(), 'timezone' => '+00:00', 'tables' => [], 'files' => []];
        $locked = false;
        try {
            $source->statement("SET time_zone = '+00:00'");
            $source->statement('FLUSH TABLES WITH READ LOCK');
            $locked = true;
            $dump = new Process([
                config('migration.mysqldump'), '--defaults-extra-file='.$options,
                '--default-character-set=utf8mb4', '--skip-lock-tables', '--routines', '--events', '--triggers',
                '--hex-blob', '--set-gtid-purged=OFF', '--no-tablespaces',
                '--result-file='.$root.'/database.sql', $source->getDatabaseName(),
            ]);
            $dump->setTimeout(600);
            $dump->mustRun();
            $tables = $source->select('SHOW FULL TABLES');
            foreach ($tables as $table) {
                $name = array_values((array) $table)[0];
                $rows = $this->rows($source->getPdo(), $name);
                $encoded = array_map(fn (array $row): string => base64_encode(serialize($row)), $rows);
                File::put($root.'/records/'.$name.'.json', json_encode($encoded, JSON_THROW_ON_ERROR));
                $manifest['tables'][$name] = ['rows' => count($rows), 'sha256' => hash('sha256', serialize($rows))];
            }
            foreach (['sistema', 'templates', 'uploads', 'vendor'] as $directory) {
                foreach (File::allFiles($sourcePath.'/'.$directory) as $file) {
                    $relative = $directory.'/'.str_replace('\\', '/', $file->getRelativePathname());
                    $this->copy($file->getPathname(), $root.'/files/'.$relative);
                    $manifest['files'][$relative] = ['bytes' => filesize($file->getPathname()), 'sha256' => hash_file('sha256', $file->getPathname())];
                    if ($manifest['files'][$relative]['sha256'] !== hash_file('sha256', $root.'/files/'.$relative)) {
                        throw new RuntimeException('Snapshot copy hash mismatch.');
                    }
                }
            }
            foreach (['index.php', 'rotas.php', 'composer.json', 'composer.lock', '.htaccess'] as $file) {
                $this->copy($sourcePath.'/'.$file, $root.'/files/'.$file);
                $manifest['files'][$file] = ['bytes' => filesize($sourcePath.'/'.$file), 'sha256' => hash_file('sha256', $sourcePath.'/'.$file)];
            }
            foreach ($manifest['files'] as $path => $file) {
                if (hash_file('sha256', $sourcePath.'/'.$path) !== $file['sha256']) {
                    throw new RuntimeException('A source file changed during snapshot capture.');
                }
            }
            $manifest['dump_sha256'] = hash_file('sha256', $root.'/database.sql');
        } finally {
            if ($locked) {
                $source->statement('UNLOCK TABLES');
            }
            @unlink($options);
        }
        File::put($root.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $snapshotDatabase = 'arquid_snapshot_'.$id;
        $target->statement('CREATE DATABASE `'.$snapshotDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $options = $this->optionsFile($root, config('database.connections.mysql'));
        try {
            $restore = new Process([config('migration.mysql'), '--defaults-extra-file='.$options, '--default-character-set=utf8mb4', $snapshotDatabase]);
            $restore->setInput(fopen($root.'/database.sql', 'rb'));
            $restore->setTimeout(600);
            $restore->mustRun();
        } finally {
            @unlink($options);
        }
        $this->connect($snapshotDatabase);
        $restored = DB::connection('legacy_snapshot');
        foreach ($manifest['tables'] as $table => $expected) {
            $rows = $this->rows($restored->getPdo(), $table);
            if (count($rows) !== $expected['rows'] || hash('sha256', serialize($rows)) !== $expected['sha256']) {
                throw new RuntimeException('Restored table does not match captured rows: '.$table);
            }
        }
        $manifest['restored_database'] = $snapshotDatabase;
        $manifest['restore_verified'] = true;
        File::put($root.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        File::put(storage_path('app/private/migration/current-snapshot.json'), json_encode(['path' => $root], JSON_THROW_ON_ERROR));

        return ['path' => $root, 'manifest' => $manifest];
    }

    public function current(): array
    {
        $pointer = json_decode(File::get(storage_path('app/private/migration/current-snapshot.json')), true, flags: JSON_THROW_ON_ERROR);
        $manifest = json_decode(File::get($pointer['path'].'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        if (! ($manifest['restore_verified'] ?? false) || hash_file('sha256', $pointer['path'].'/database.sql') !== $manifest['dump_sha256']) {
            throw new RuntimeException('A verified snapshot is required.');
        }
        $this->connect($manifest['restored_database']);

        return ['path' => $pointer['path'], 'manifest' => $manifest];
    }

    public function rows(\PDO $pdo, string $table): array
    {
        $rows = $pdo->query('SELECT * FROM `'.str_replace('`', '``', $table).'`')->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            ksort($row);
            foreach ($row as &$value) {
                $value = $value === null ? null : (string) $value;
            }
        }
        unset($row, $value);
        usort($rows, fn (array $a, array $b): int => strcmp(serialize($a), serialize($b)));

        return $rows;
    }

    private function connect(string $database): void
    {
        config(['database.connections.legacy_snapshot' => array_replace(config('database.connections.legacy'), ['database' => $database])]);
        DB::purge('legacy_snapshot');
        DB::connection('legacy_snapshot')->statement("SET time_zone = '+00:00'");
    }

    private function copy(string $source, string $target): void
    {
        File::ensureDirectoryExists(dirname($target));
        if (! copy($source, $target)) {
            throw new RuntimeException('Cannot copy snapshot file.');
        }
    }

    private function optionsFile(string $root, array $connection): string
    {
        $path = $root.'/client-'.bin2hex(random_bytes(8)).'.cnf';
        $content = "[client]\n";
        foreach (['host', 'port', 'username', 'password'] as $key) {
            $name = $key === 'username' ? 'user' : $key;
            $value = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $connection[$key]);
            $content .= $name.'="'.$value."\"\n";
        }
        File::put($path, $content);

        return $path;
    }
}
