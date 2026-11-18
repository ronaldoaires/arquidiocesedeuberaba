<?php

namespace App\Services\Migration;

use App\Support\ContentRegistry;
use App\Support\PresentationDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class LegacyImporter
{
    public function run(): array
    {
        $snapshot = app(SnapshotManager::class)->current();
        $this->assertSeparateDatabases();
        $media = CanonicalLegacyMedia::load($snapshot);
        if (DB::table('legacy_records')->where('snapshot_id', '!=', $snapshot['manifest']['id'])->exists() || DB::table('media_assets')->where('path', 'like', 'legacy/uploads/%')->exists()) {
            throw new RuntimeException('O destino contém uma importação anterior.');
        }
        $assets = [];
        foreach ($media->destinations as $destination => $file) {
            $origin = ($file['recovered'] ?? false) ? '/canonical-recovery/' : '/files/';
            $this->copyVerified($snapshot['path'].$origin.$file['source'], storage_path('app/public/'.$destination), $file['sha256']);
            $assets[] = ['path' => $destination, 'legacy_path' => $file['source'], 'sha256' => $file['sha256'], 'bytes' => $file['bytes'], 'state' => 'available'];
        }
        foreach (array_chunk($assets, 300) as $batch) {
            DB::table('media_assets')->upsert($batch, ['path'], ['legacy_path', 'sha256', 'bytes', 'state']);
        }
        $counts = [];
        foreach (ContentRegistry::all() as $source => $module) {
            if (! ($module['legacy_available'] ?? true)) {
                continue;
            }
            $rows = app(SnapshotManager::class)->rows(DB::connection('legacy_snapshot')->getPdo(), $source);
            if (hash('sha256', serialize($rows)) !== $snapshot['manifest']['tables'][$source]['sha256']) {
                throw new RuntimeException('Snapshot table integrity mismatch: '.$source);
            }
            foreach ($rows as $row) {
                $key = $source === 'paroquia_clero'
                    ? json_encode([$row['id_paroquia'], $row['id_clero']], JSON_THROW_ON_ERROR)
                    : $row['id'];
                DB::connection('mysql')->transaction(function () use ($source, $module, $row, $key, $snapshot, $media): void {
                    $previous = DB::table('legacy_records')->where('source_table', $source)->where('source_key', $key)->first();
                    $hash = hash('sha256', serialize($row));
                    if ($previous) {
                        if ($previous->source_hash !== $hash) {
                            throw new RuntimeException('A different snapshot requires an explicit incremental migration.');
                        }

                        return;
                    }
                    $data = [];
                    foreach ($module['columns'] as $column) {
                        $value = $row[$column['source']];
                        $name = $column['name'];
                        if ($name === 'id') {
                            $data[$name] = (int) $value;

                            continue;
                        }
                        if ($column['relation']) {
                            $related = ContentRegistry::get($column['relation'])['table'];
                            if ($value === null || $value === '' || ! DB::table($related)->where('id', $value)->exists()) {
                                if ($value !== null && $value !== '') {
                                    $this->issue($source, $key, $name, 'missing_parent', (string) $value);
                                }
                                $value = null;
                            }
                        } elseif (str_starts_with($column['type'], 'timestamp')) {
                            $value = $this->date($value, 'UTC', $source, $key, $name);
                        } elseif (str_starts_with($column['type'], 'datetime')) {
                            $value = $this->date($value, 'America/Sao_Paulo', $source, $key, $name);
                        } elseif ($column['type'] === 'date') {
                            if ($name === 'presentation_date') {
                                $value = PresentationDate::toIso($value);
                            } elseif ($value && str_starts_with($value, '0000-')) {
                                $this->issue($source, $key, $name, 'zero_date', $value);
                                $value = null;
                            }
                        } elseif (preg_match('/int|year/', $column['type'])) {
                            $value = $value === null || $value === '' ? null : (int) $value;
                        }
                        $data[$name] = $media->value($source, $key, $name, $value);
                    }
                    if ($source === 'clero') {
                        $data['legacy_presentation_date_text'] = $row['data_apresentacao'] ?? null;
                    }
                    if ($source === 'usuario') {
                        $userFields = array_intersect_key($data, array_flip(['id', 'name', 'email', 'password', 'is_active', 'legacy_level', 'created_at', 'updated_at', 'cover_path', 'last_login_at']));
                        DB::table('users')->insert($userFields);
                        $profile = array_diff_key($data, $userFields);
                        $profileFields = array_intersect_key($profile, array_flip(Schema::getColumnListing('user_profiles')));
                        DB::table('user_profiles')->insert($profileFields + ['user_id' => $data['id'], 'profile_data' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                        $targetId = $data['id'];
                    } else {
                        $data['import_hash'] = $hash;
                        $data['created_at'] ??= null;
                        $data['updated_at'] ??= null;
                        $targetId = $source === 'paroquia_clero'
                            ? DB::table($module['table'])->insertGetId($data)
                            : $data['id'];
                        if ($source !== 'paroquia_clero') {
                            DB::table($module['table'])->insert($data);
                        }
                    }
                    DB::table('legacy_records')->insert([
                        'source_table' => $source, 'source_key' => $key,
                        'payload_base64' => base64_encode(serialize($row)), 'source_hash' => $hash,
                        'target_table' => $module['table'], 'target_id' => $targetId, 'snapshot_id' => $snapshot['manifest']['id'],
                    ]);
                });
            }
            $counts[$source] = count($rows);
        }
        $this->syncAffiliations();
        foreach ($snapshot['manifest']['files'] as $path => $file) {
            foreach (['templates/site/assets/img/' => 'site/', 'templates/assets/img/' => 'shared/'] as $prefix => $target) {
                if (str_starts_with($path, $prefix)) {
                    $this->copyVerified($snapshot['path'].'/files/'.$path, public_path('assets/'.$target.substr($path, strlen($prefix))), $file['sha256']);
                }
            }
        }
        $report = ['snapshot_id' => $snapshot['manifest']['id'], 'tables' => $counts, 'records' => array_sum($counts), 'files' => count($media->destinations), 'issues' => DB::table('migration_issues')->count()];
        File::put(storage_path('app/private/migration/import-report.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $report;
    }

    private function syncAffiliations(): void
    {
        foreach (DB::table('clergy_members')->get() as $clergy) {
            foreach ([1, 2, 3] as $slot) {
                if (DB::table('clergy_legacy_affiliations')->where('clergy_member_id', $clergy->id)->where('slot', $slot)->exists()) {
                    continue;
                }
                $text = $clergy->{'parish_'.$slot.'_text'};
                $link = $clergy->{'parish_'.$slot.'_link'};
                $role = $clergy->{'role_'.$slot.'_text'};
                if (blank($text) && blank($link) && blank($role)) {
                    continue;
                }
                $parishId = null;
                $host = parse_url($link ?? '', PHP_URL_HOST);
                if ((! $host || in_array(strtolower($host), ['localhost', 'arquidiocesedeuberaba.com.br', 'www.arquidiocesedeuberaba.com.br', 'arquidiocesedeuberaba.org.br', 'www.arquidiocesedeuberaba.org.br'], true)) && preg_match('~/paroquia/([^/?#]+)~', $link ?? '', $match)) {
                    $parishId = DB::table('parishes')->where('slug', rawurldecode($match[1]))->value('id');
                }
                DB::table('clergy_legacy_affiliations')->insert(['clergy_member_id' => $clergy->id, 'slot' => $slot, 'parish_id' => $parishId, 'parish_text' => $text, 'link' => $link, 'role_text' => $role]);
            }
        }
    }

    private function assertSeparateDatabases(): void
    {
        $source = DB::connection('legacy_snapshot')->selectOne('SELECT @@server_uuid AS server_uuid, DATABASE() AS database_name');
        $target = DB::connection('mysql')->selectOne('SELECT @@server_uuid AS server_uuid, DATABASE() AS database_name');
        if (config('database.default') !== 'mysql' || $source == $target || DB::connection('mysql')->getDatabaseName() !== 'arquid68_app') {
            throw new RuntimeException('Unexpected migration database identity.');
        }
    }

    private function date(?string $value, string $timezone, string $source, string $key, string $field): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, '0000-')) {
            $this->issue($source, $key, $field, 'zero_date', $value);

            return null;
        }

        return CarbonImmutable::parse($value, $timezone)->utc()->format('Y-m-d H:i:s');
    }

    private function issue(string $source, string $key, string $field, string $code, string $details): void
    {
        DB::table('migration_issues')->insert(['source_table' => $source, 'source_key' => $key, 'field' => $field, 'code' => $code, 'details' => $details, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function copyVerified(string $source, string $target, string $hash): void
    {
        File::ensureDirectoryExists(dirname($target));
        if (is_file($target)) {
            if (hash_file('sha256', $target) !== $hash) {
                throw new RuntimeException('Destination file conflict: '.$target);
            }

            return;
        }
        $temporary = tempnam(dirname($target), '.migration-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot stage media: '.$target);
        }
        try {
            if (! copy($source, $temporary) || hash_file('sha256', $temporary) !== $hash) {
                throw new RuntimeException('Snapshot copy integrity mismatch: '.$source);
            }
            if (! rename($temporary, $target)) {
                throw new RuntimeException('Cannot publish verified media: '.$target);
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
