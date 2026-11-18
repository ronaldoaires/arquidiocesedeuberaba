<?php

namespace App\Services\Migration;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class LegacyMediaRecovery
{
    public function run(): array
    {
        $urls = [];
        foreach (DB::table('legacy_records')->get() as $record) {
            $row = unserialize(base64_decode($record->payload_base64), ['allowed_classes' => false]);
            foreach ($row as $value) {
                if (! is_string($value)) {
                    continue;
                }
                preg_match_all('~https?://(?:www\\.)?arquidiocesedeuberaba\\.(?:com|org)\\.br/uploads/[^\\s"\'<>]+~i', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches);
                foreach ($matches[0] as $url) {
                    $path = $this->path($url);
                    if ($path && ! is_file(storage_path('app/public/legacy/'.$path))) {
                        $urls[$url] = $path;
                    }
                }
            }
        }
        $verification = json_decode(File::get(storage_path('app/private/migration/verification-report.json')), true);
        foreach ($verification['historical_missing_media'] ?? [] as $missing) {
            $path = substr($missing['path'], strlen('legacy/'));
            $url = 'https://arquidiocesedeuberaba.com.br/'.$path;
            if ($this->path($url)) {
                $urls[$url] = $path;
            }
        }
        $report = ['attempted' => count($urls), 'recovered' => [], 'unresolved' => []];
        $root = storage_path('app/private/migration/recovery');
        File::ensureDirectoryExists($root);
        foreach (array_chunk($urls, 6, true) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch, $root): array {
                $requests = [];
                foreach ($batch as $url => $path) {
                    $key = hash('sha256', $url);
                    $requests[] = $pool->as($key)->connectTimeout(3)->timeout(8)->withOptions([
                        'allow_redirects' => false, 'sink' => $root.'/'.$key,
                        'progress' => function ($downloadTotal, $downloadedBytes): void {
                            if ($downloadTotal > 52428800 || $downloadedBytes > 52428800) {
                                throw new \RuntimeException('Media download exceeds the permitted size.');
                            }
                        },
                    ])->get($url);
                }

                return $requests;
            }, concurrency: 6);
            foreach ($batch as $url => $path) {
                $key = hash('sha256', $url);
                $response = $responses[$key] ?? null;
                $temporary = $root.'/'.$key;
                if ($response instanceof Response && $response->successful() && is_file($temporary) && $this->validFile($temporary, $path)) {
                    $target = storage_path('app/public/legacy/'.$path);
                    if (! is_file($target)) {
                        File::ensureDirectoryExists(dirname($target));
                        rename($temporary, $target);
                        DB::table('media_assets')->updateOrInsert(['path' => 'legacy/'.$path], ['bytes' => filesize($target), 'sha256' => hash_file('sha256', $target), 'state' => 'recovered_from_public_url']);
                        $report['recovered'][] = ['url' => $url, 'path' => $path, 'sha256' => hash_file('sha256', $target)];
                    }
                } else {
                    $report['unresolved'][] = ['url' => $url, 'status' => $response instanceof Response ? $response->status() : null];
                }
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
        File::put(storage_path('app/private/migration/recovery-report.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $report;
    }

    public function path(string $url): ?string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if (! in_array($host, ['arquidiocesedeuberaba.com.br', 'www.arquidiocesedeuberaba.com.br', 'arquidiocesedeuberaba.org.br', 'www.arquidiocesedeuberaba.org.br'], true)) {
            return null;
        }
        $path = rawurldecode(parse_url($url, PHP_URL_PATH) ?? '');
        if (! str_starts_with($path, '/uploads/') || str_contains($path, '\\') || preg_match('~(^|/)\\.[^/]*($|/)~', $path)) {
            return null;
        }

        return ltrim($path, '/');
    }

    public function validFile(string $file, string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);

        return match ($extension) {
            'jpg','jpeg' => $mime === 'image/jpeg','png' => $mime === 'image/png','gif' => $mime === 'image/gif','webp' => $mime === 'image/webp',
            'pdf' => $mime === 'application/pdf','mp3' => in_array($mime, ['audio/mpeg', 'audio/mp3'], true),
            default => false,
        };
    }
}
