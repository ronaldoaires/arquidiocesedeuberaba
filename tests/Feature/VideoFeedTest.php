<?php

use App\Services\VideoFeed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('loads the configured channel with cached metadata', function (): void {
    config(['media.youtube_key' => 'test-key', 'media.youtube_channel' => 'test-channel']);
    Cache::flush();
    Http::fake([
        '*channels*' => Http::response(['items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'test-playlist']]]]]),
        '*playlistItems*' => Http::response(['items' => [['snippet' => ['title' => 'Vídeo de teste', 'resourceId' => ['videoId' => 'abcdefghijk'], 'publishedAt' => '2026-11-01T10:00:00Z', 'thumbnails' => ['medium' => ['url' => 'https://example.test/image.jpg']]]]]]),
        '*videos*' => Http::response(['items' => [['id' => 'abcdefghijk', 'statistics' => ['viewCount' => 10]]]]),
    ]);
    $feed = app(VideoFeed::class)->get();
    expect($feed->first()->video_id)->toBe('abcdefghijk')->and($feed->first()->title)->toBe('Vídeo de teste');
    app(VideoFeed::class)->get();
    Http::assertSentCount(3);
});

it('returns a safe fallback when the external API fails', function (): void {
    config(['media.youtube_key' => 'test-key']);
    Cache::flush();
    Http::fake(['*' => Http::response([], 503)]);
    expect(app(VideoFeed::class)->get())->toBeInstanceOf(Collection::class);
});
