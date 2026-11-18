<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CanonicalLegacyMedia
{
    private array $fields = [];

    public function __construct(public readonly array $destinations, array $references)
    {
        foreach ($references as $ref) {
            $this->fields[$ref['table']][$ref['id']][$ref['target_field']][] = $ref;
        }
    }

    public static function load(array $snapshot): self
    {
        $file = $snapshot['path'].'/canonical-media.json';
        if (! is_file($file)) {
            throw new RuntimeException('Execute legacy:plan-media --snapshot antes da importação.');
        }
        $plan = File::json($file);
        if ($plan['snapshot_id'] !== $snapshot['manifest']['id']) {
            throw new RuntimeException('Plano de mídia de outra captura.');
        }

        return new self($plan['destinations'], $plan['references']);
    }

    public static function includeExistingMedia(array $report): array
    {
        $missing = array_filter($report['references'], fn (array $ref): bool => $ref['status'] === 'missing');
        if (! $missing || ! Schema::hasColumn('media_assets', 'legacy_path')) {
            return $report;
        }
        $disk = Storage::disk('public');
        $matches = [];
        foreach (array_chunk(array_unique(array_column($missing, 'source')), 300) as $paths) {
            $assets = DB::table('media_assets')->whereIn('legacy_path', $paths)->where('state', 'available')->orderBy('path')->get(['legacy_path', 'path', 'sha256']);
            foreach ($assets as $asset) {
                $path = $asset->path;
                if (! preg_match('~^(content|editor)/~', $path) || str_contains($path, '..') || str_contains($path, chr(92)) || ! $disk->exists($path)) {
                    continue;
                }
                if (hash_file('sha256', $disk->path($path)) !== $asset->sha256) {
                    throw new RuntimeException('Mídia cadastrada com conteúdo divergente: '.$path);
                }
                $matches[$asset->legacy_path] ??= $path;
            }
        }
        foreach ($missing as $key => $ref) {
            if (isset($matches[$ref['source']])) {
                $report['references'][$key]['status'] = 'available_in_canonical_media';
                $report['references'][$key]['recovery_path'] = $matches[$ref['source']];
            }
        }
        $report['summary']['reference_statuses'] = array_count_values(array_column($report['references'], 'status'));

        return $report;
    }

    public static function freeze(array $snapshot, array $report): void
    {
        $file = $snapshot['path'].'/canonical-media.json';
        if (is_file($file)) {
            self::load($snapshot);

            return;
        }
        $destinations = [];
        foreach ($report['destinations'] as $destination => $info) {
            $path = $info['source'];
            $destinations[$destination] = ['source' => $path] + $snapshot['manifest']['files'][$path];
        }
        foreach ($report['references'] as &$ref) {
            if (in_array($ref['status'], ['available_in_laravel_legacy', 'available_in_canonical_media'], true)) {
                $path = $ref['source'];
                $recovery = Storage::disk('public')->path($ref['recovery_path'] ?? 'legacy/'.$path);
                $hash = hash_file('sha256', $recovery);
                $directory = $ref['directory'];
                $destination = $directory.'/'.basename($path);
                if ((isset($destinations[$destination]) && $destinations[$destination]['source'] !== $path)
                    || (is_file(storage_path('app/public/'.$destination)) && hash_file('sha256', storage_path('app/public/'.$destination)) !== $hash)) {
                    throw new RuntimeException('Conflito na recuperação de '.$path);
                }
                $archive = $snapshot['path'].'/canonical-recovery/'.$path;
                File::ensureDirectoryExists(dirname($archive));
                if (! copy($recovery, $archive) || hash_file('sha256', $archive) !== $hash) {
                    throw new RuntimeException('Falha ao preservar '.$path);
                }
                $ref['destination'] = $destination;
                $destinations[$destination] = ['source' => $path, 'bytes' => filesize($archive), 'sha256' => $hash, 'recovered' => true];
            } elseif (! in_array($ref['status'], ['found', 'found_case_variant', 'missing', 'review_wordpress_url'], true)) {
                throw new RuntimeException('Referência ambígua: '.$ref['table'].' #'.$ref['id'].' '.$ref['status']);
            }
        }
        unset($ref);
        File::replace($file, json_encode(['snapshot_id' => $snapshot['manifest']['id'], 'destinations' => $destinations, 'references' => $report['references']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function value(string $source, string $key, string $field, mixed $value): mixed
    {
        $refs = $this->fields[$source][$key][$field] ?? [];
        if (in_array($field, ['cover_path', 'photo_path', 'file_path'], true)) {
            if (blank($value)) {
                return null;
            }
            if (count($refs) !== 1) {
                throw new RuntimeException("Incomplete media plan for $source/$key/$field");
            }

            return $refs[0]['destination'] ?? null;
        }
        if (! $refs || ! is_string($value)) {
            return $value;
        }
        $urls = [];
        foreach ($refs as $ref) {
            if (! isset($ref['destination'], $ref['url'])) {
                continue;
            }
            $parts = parse_url($ref['url']);
            $path = implode('/', array_map('rawurlencode', explode('/', $ref['destination'])));
            $urls[$ref['url']] = Storage::disk('public')->url($path)
                .(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        }
        if (! $urls) {
            return $value;
        }
        if (! str_contains($value, '<')) {
            return $urls[html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')] ?? $value;
        }
        $seen = [];
        $rewrite = function (string $text, string $kind) use ($urls, &$seen): string {
            $replace = function (string $url) use ($urls, &$seen): string {
                if (! isset($urls[$url])) {
                    return $url;
                }
                $seen[$url] = true;

                return $urls[$url];
            };

            return match ($kind) {
                'style' => preg_replace_callback('~(url\(\s*([\'"]?))(.*?)\2(\s*\))~is', fn ($m) => $m[1].$replace($m[3]).$m[2].$m[4], $text),
                'srcset' => preg_replace_callback('~(^|,\s*)([^\s,]+)~', fn ($m) => $m[1].$replace($m[2]), $text),
                default => $replace(trim($text)),
            };
        };
        $result = preg_replace_callback('~<!--.*?-->|<style\b[^>]*>.*?</style\s*>|<[a-z][\w:-]*(?:[^\'">]|"[^"]*"|\'[^\']*\')*>~is', function ($tag) use ($rewrite) {
            if (str_starts_with($tag[0], '<!--')) {
                return $tag[0];
            }
            if (preg_match('~^(<style\b[^>]*>)(.*)(</style\s*>)$~is', $tag[0], $style)) {
                return $style[1].$rewrite($style[2], 'style').$style[3];
            }

            // Consume all attributes, including unrelated quoted values, to keep their contents intact.
            return preg_replace_callback('~(\s+)([\w:-]+)(\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~s', function ($attr) use ($rewrite) {
                $kind = strtolower($attr[2]);
                if (! in_array($kind, ['src', 'href', 'poster', 'data-id', 'data-src', 'data', 'srcset', 'style'], true)) {
                    return $attr[0];
                }
                $raw = end($attr);
                $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $updated = $rewrite($decoded, $kind);
                if ($updated === $decoded) {
                    return $attr[0];
                }

                return $attr[1].$attr[2].$attr[3].'"'.htmlspecialchars($updated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
            }, $tag[0]);
        }, $value);
        if ($result === null || array_diff_key($urls, $seen)) {
            throw new RuntimeException("Unresolved editorial URL in $source/$key/$field");
        }

        return $result;
    }
}
