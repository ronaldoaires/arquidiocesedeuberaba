<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class VideoFeed
{
    public function get(): Collection
    {
        return collect(Cache::remember('site.youtube.feed.v3', 86400, function (): array {
            try {
                $key = config('media.youtube_key');
                if (! $key) {
                    return $this->fallback();
                }
                $channel = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'contentDetails', 'id' => config('media.youtube_channel'), 'key' => $key])->throw()->json();
                $playlist = data_get($channel, 'items.0.contentDetails.relatedPlaylists.uploads');
                if (! $playlist) {
                    return $this->fallback();
                }
                $items = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/playlistItems', ['part' => 'snippet', 'playlistId' => $playlist, 'maxResults' => 20, 'key' => $key])->throw()->json('items', []);
                $ids = array_filter(array_map(fn (array $item): ?string => data_get($item, 'snippet.resourceId.videoId'), $items));
                $statistics = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/videos', ['part' => 'statistics,contentDetails', 'id' => implode(',', $ids), 'key' => $key])->throw()->json('items', []);
                $statistics = collect($statistics)->keyBy('id');
                $result = [];
                foreach ($items as $item) {
                    $snippet = $item['snippet'];
                    $id = $snippet['resourceId']['videoId'] ?? null;
                    if (! $id) {
                        continue;
                    }
                    $result[] = ['video_id' => $id, 'title' => $snippet['title'], 'thumbnail' => data_get($snippet, 'thumbnails.medium.url'), 'published_at' => $snippet['publishedAt'], 'description' => $snippet['description'] ?? '', 'likes' => data_get($statistics->get($id), 'statistics.likeCount', 0), 'duration' => (new \DateInterval(data_get($statistics->get($id), 'contentDetails.duration', 'PT0S')))->format('%h:%I:%S'), 'views' => data_get($statistics->get($id), 'statistics.viewCount', 0)];
                }

                return $result ?: $this->fallback();
            } catch (\Throwable) {
                return $this->fallback();
            }
        }))->map(fn (array $video): object => (object) $video);
    }

    private function fallback(): array
    {
        $path = storage_path('app/private/migration/legacy-cache/videos_youtube.json');
        if (! File::exists($path)) {
            return [];
        }
        $items = json_decode(File::get($path), true) ?: [];

        return array_map(fn (array $item): array => [
            'video_id' => $item['id'], 'title' => $item['titulo'] ?? '', 'thumbnail' => $item['thumbnail'] ?? null,
            'published_at' => $item['publicado_em'] ?? null, 'description' => $item['descricao'] ?? '', 'likes' => $item['likes'] ?? 0, 'duration' => $item['duracao'] ?? '00:00', 'views' => $item['views'] ?? 0,
        ], $items);
    }
}
