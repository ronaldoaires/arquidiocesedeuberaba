<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class YouTubeVideoMetadata
{
    private array $resolved = [];

    public function validId(?string $id): bool
    {
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) === 1;
    }

    public function get(?string $id): array
    {
        if (! $this->validId($id)) {
            return ['title' => null, 'thumbnail' => null];
        }
        $this->prime([$id]);

        return $this->resolved[$id]['data'] ?? Cache::get($this->key($id), $this->fallback($id));
    }

    public function prime(array $ids): void
    {
        if (! config('media.youtube_metadata_enabled', true)) {
            return;
        }
        $ids = array_values(array_unique(array_filter($ids, fn ($id): bool => $this->validId($id))));
        $ids = array_values(array_filter($ids, fn (string $id): bool => ($this->resolved[$id]['expires'] ?? 0) <= now()->getTimestamp()));
        if ($ids === []) {
            return;
        }
        $cached = Cache::many(array_map($this->key(...), $ids));
        foreach ($ids as $id) {
            if (($data = $cached[$this->key($id)] ?? null) !== null) {
                $this->resolved[$id] = ['data' => $data, 'expires' => now()->getTimestamp() + 30];
            }
        }
        $missing = array_filter($ids, fn (string $id): bool => ($cached[$this->key($id)] ?? null) === null);
        foreach (array_chunk($missing, 10) as $chunk) {
            try {
                $responses = Http::pool(fn (Pool $pool): array => array_map(fn (string $id) => $pool->as($id)->connectTimeout(2)->timeout(4)->withOptions(['allow_redirects' => false])
                    ->get('https://www.youtube.com/oembed', ['url' => 'https://www.youtube.com/watch?v='.$id, 'format' => 'json']), $chunk));
            } catch (Throwable) {
                $responses = [];
            }
            foreach ($chunk as $id) {
                $response = $responses[$id] ?? null;
                $title = $response instanceof Response && $response->successful() ? $response->json('title') : null;
                $successful = is_string($title) && trim($title) !== '';
                $data = $successful ? ['title' => Str::limit(trim(strip_tags($title)), 500), 'thumbnail' => $this->fallback($id)['thumbnail']]
                    : Cache::get($this->key($id).'.last-good', $this->fallback($id));
                $ttl = $successful ? 86400 : 900;
                $this->resolved[$id] = ['data' => $data, 'expires' => now()->getTimestamp() + $ttl];
                Cache::put($this->key($id), $data, $ttl);
                if ($successful) {
                    Cache::put($this->key($id).'.last-good', $data, 604800);
                }
            }
        }
    }

    private function fallback(string $id): array
    {
        return ['title' => null, 'thumbnail' => 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg'];
    }

    private function key(string $id): string
    {
        return 'youtube.video-metadata.v1.'.$id;
    }
}
