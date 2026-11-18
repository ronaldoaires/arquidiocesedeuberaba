<?php

namespace App\Services\Migration;

use App\Services\MediaLifecycle;
use App\Support\ContentRegistry;
use App\Support\PresentationDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrationVerifier
{
    public function run(): array
    {
        $manager = app(SnapshotManager::class);
        $snapshot = $manager->current();
        $media = CanonicalLegacyMedia::load($snapshot);
        $errors = [];
        $tables = [];
        $missingMedia = [];
        $records = 0;
        $referenced = [];
        $lifecycle = app(MediaLifecycle::class);
        $fieldsVerified = 0;
        foreach (ContentRegistry::all() as $source => $module) {
            if (! ($module['legacy_available'] ?? true)) {
                continue;
            }
            $expectedRows = $manager->rows(DB::connection('legacy_snapshot')->getPdo(), $source);
            $expectedHashes = [];
            foreach ($expectedRows as $row) {
                $key = $source === 'paroquia_clero' ? json_encode([$row['id_paroquia'], $row['id_clero']], JSON_THROW_ON_ERROR) : $row['id'];
                $expectedHashes[$key] = hash('sha256', serialize($row));
            }
            $archived = DB::table('legacy_records')->where('source_table', $source)->get();
            $actualCount = DB::table($module['table'])->count();
            $tables[$source] = ['expected' => count($expectedRows), 'archived' => $archived->count(), 'actual' => $actualCount];
            if (count($expectedRows) !== $actualCount || count($expectedRows) !== $archived->count()) {
                $errors[] = 'row_count:'.$source;
            }
            foreach ($archived as $record) {
                $payload = base64_decode($record->payload_base64, true);
                if ($payload === false || hash('sha256', $payload) !== $record->source_hash || ($expectedHashes[$record->source_key] ?? null) !== $record->source_hash) {
                    $errors[] = 'source_hash:'.$source.':'.$record->source_key;

                    continue;
                }
                $row = unserialize($payload, ['allowed_classes' => false]);
                $target = DB::table($module['table'])->where('id', $record->target_id)->first();
                if (! $target) {
                    $errors[] = 'missing_target:'.$source.':'.$record->source_key;

                    continue;
                }
                $attributes = (array) $target;
                // Include mapped link columns beyond the usual rich-editor fields.
                foreach ($module['columns'] as $mapped) {
                    $value = $attributes[$mapped['name']] ?? null;
                    if (is_string($value) && str_contains($value, '/storage/')) {
                        foreach ($lifecycle->paths(['body_html' => $value]) as $linked) {
                            $referenced[$linked] = true;
                        }
                    }
                }
                foreach ($lifecycle->paths($attributes) as $linked) {
                    $referenced[$linked] = true;
                }
                $profile = $source === 'usuario' ? DB::table('user_profiles')->where('user_id', $target->id)->first() : null;
                $profileData = $profile ? json_decode($profile->profile_data, true, flags: JSON_THROW_ON_ERROR) : [];
                foreach ($module['columns'] as $column) {
                    $name = $column['name'];
                    if ($source === 'paroquia_clero' && $name === 'id') {
                        continue;
                    }
                    $expected = $media->value($source, $record->source_key, $name, $this->expected($row[$column['source']], $column, $module));
                    $actual = property_exists($target, $name) ? $target->{$name} : (($profile && property_exists($profile, $name)) ? $profile->{$name} : ($profileData[$name] ?? null));
                    if ($expected === null ? $actual !== null : (string) $actual !== (string) $expected) {
                        $errors[] = 'field_mismatch:'.$source.':'.$record->source_key.':'.$name;
                    }
                    if (in_array($name, ['cover_path', 'photo_path', 'file_path'], true) && filled($row[$column['source']]) && $expected === null) {
                        $missingMedia[] = ['source_table' => $source, 'source_key' => $record->source_key, 'field' => $name, 'original_value' => $row[$column['source']], 'path' => 'legacy/uploads/'.$module['media'].'/'.$row[$column['source']], 'classification' => 'missing_in_source_inventory'];
                    }
                    $fieldsVerified++;
                }
                $records++;
            }
            foreach ($module['columns'] as $column) {
                if (! $column['relation']) {
                    continue;
                }
                $parent = ContentRegistry::get($column['relation'])['table'];
                $count = DB::table($module['table'].' as child')->leftJoin($parent.' as parent', 'parent.id', '=', 'child.'.$column['name'])
                    ->whereNotNull('child.'.$column['name'])->whereNull('parent.id')->count();
                if ($count > 0) {
                    $errors[] = 'invalid_fk:'.$source.':'.$column['name'];
                }
            }
        }
        $files = 0;
        $bytes = 0;
        $catalog = DB::table('media_assets')->get()->keyBy('path');
        foreach ($media->destinations as $path => $expected) {
            $original = str_replace('/thumbs/', '/', $path);
            if (! isset($referenced[$path]) && ! isset($referenced[$original])) {
                $errors[] = 'unreferenced_media:'.$path;
            }
            $asset = $catalog->get($path);
            if (! $asset || $asset->legacy_path !== $expected['source'] || $asset->sha256 !== $expected['sha256'] || (int) $asset->bytes !== $expected['bytes']) {
                $errors[] = 'catalog_mismatch:'.$path;
            }
            $destination = storage_path('app/public/'.$path);
            if (! is_file($destination) || filesize($destination) !== $expected['bytes'] || hash_file('sha256', $destination) !== $expected['sha256']) {
                $errors[] = 'file_mismatch:'.$path;
            }
            $bytes += $expected['bytes'];
            $files++;
        }
        foreach (File::allFiles(storage_path('app/public')) as $actualFile) {
            $actualPath = str_replace('\\', '/', $actualFile->getRelativePathname());
            if (! isset($media->destinations[$actualPath])) {
                $errors[] = 'unregistered_public_file:'.$actualPath;
            }
        }
        if ($catalog->count() !== count($media->destinations)) {
            $errors[] = 'catalog_count';
        }
        $report = [
            'snapshot_id' => $snapshot['manifest']['id'], 'passed' => $errors === [],
            'records_verified' => $records, 'fields_verified' => $fieldsVerified, 'files_verified' => $files, 'bytes_verified' => $bytes,
            'tables' => $tables, 'errors' => $errors, 'historical_relational_issues' => DB::table('migration_issues')->count(),
            'historical_missing_media' => $missingMedia,
        ];
        File::put(storage_path('app/private/migration/verification-report.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $report;
    }

    private function expected(?string $value, array $column, array $module): string|int|null
    {
        $name = $column['name'];
        if ($column['relation']) {
            $parent = ContentRegistry::get($column['relation'])['table'];

            return $value !== null && $value !== '' && DB::table($parent)->where('id', $value)->exists() ? (int) $value : null;
        }
        if (str_starts_with($column['type'], 'timestamp') || str_starts_with($column['type'], 'datetime')) {
            if (! $value || str_starts_with($value, '0000-')) {
                return null;
            }

            return CarbonImmutable::parse($value, str_starts_with($column['type'], 'timestamp') ? 'UTC' : 'America/Sao_Paulo')->utc()->format('Y-m-d H:i:s');
        }
        if ($name === 'presentation_date' && $column['type'] === 'date') {
            return PresentationDate::toIso($value);
        }
        if ($column['type'] === 'date' && $value && str_starts_with($value, '0000-')) {
            return null;
        }
        if (preg_match('/int|year/', $column['type'])) {
            return $value === null || $value === '' ? null : (int) $value;
        }

        return $value;
    }
}
