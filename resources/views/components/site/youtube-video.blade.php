@props(['video'])
<article class="overflow-hidden rounded-md bg-white shadow-sm">
    @if($video->video_embed_url)
        <a class="relative block aspect-video bg-black" href="https://www.youtube.com/watch?v={{ $video->video_id }}" data-inline-video data-video-id="{{ $video->video_id }}" data-video-title="{{ $video->video_title }}" aria-label="Assistir {{ $video->video_title }}">
            <img src="{{ $video->video_thumbnail }}" alt="{{ $video->video_title }}" class="h-full w-full object-cover" loading="lazy">
            <span class="absolute inset-0 flex items-center justify-center bg-black/15 text-5xl text-white" aria-hidden="true"><i class="fa-solid fa-circle-play"></i></span>
        </a>
    @else
        <p class="p-4">Vídeo indisponível.</p>
    @endif
    <h3 class="p-3 text-sm font-bold">{{ $video->video_title }}</h3>
    @if($video->video_embed_url)<a class="block px-3 pb-3 text-sm text-brand-blue underline" href="https://www.youtube.com/watch?v={{ $video->video_id }}" target="_blank" rel="noopener">Assistir no YouTube</a>@endif
</article>
