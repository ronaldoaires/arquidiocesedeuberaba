@if($video->video_embed_url)
    <iframe class="aspect-video w-full border-0" src="{{ $video->video_embed_url }}" title="{{ $video->video_title }}" referrerpolicy="strict-origin-when-cross-origin" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
    <p class="mt-3"><a class="underline" href="https://www.youtube.com/watch?v={{ $video->video_id }}" target="_blank" rel="noopener">Assistir no YouTube</a></p>
@else
    <p>O ID deste vídeo é inválido.</p>
@endif
