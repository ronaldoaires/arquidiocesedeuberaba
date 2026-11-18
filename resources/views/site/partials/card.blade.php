@if($item instanceof \App\Models\NewsItem)
<article>
    <a href="{{ $item->public_url }}" class="relative block overflow-hidden rounded-md"><img src="{{ $item->thumbnail_url }}" alt="{{ $item->display_title }}" loading="lazy" class="h-[200px] w-full object-cover"><span class="absolute inset-0 bg-black/25" aria-hidden="true"></span><span class="absolute right-2 bottom-2 rounded bg-brand-red/90 px-3 py-1 text-sm text-white">{{ $item->category?->title }}</span></a>
    <h2 class="mt-4 text-xl font-bold leading-normal text-[#212529]"><a href="{{ $item->public_url }}">{{ $item->display_title }}</a></h2>
</article>
@else
<article class="overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm">
    @if($item->image_url)
        <a href="{{ $item->public_url ?? $item->image_url }}"><img src="{{ $item->thumbnail_url }}" alt="{{ $item->display_title }}" loading="lazy" class="aspect-[4/3] w-full object-cover" data-fallback-image="{{ asset('assets/site/capa-dom-paulo.png') }}"></a>
    @endif
    <div class="p-5">
        @if($item->created_at)<p class="mb-2 text-xs text-stone-500">{{ $item->created_at->timezone('America/Sao_Paulo')->format('d/m/Y') }}</p>@endif
        <h2 class="text-xl leading-snug text-brand-red"><a href="{{ $item->public_url ?? ($item->file_path ? url('storage/'.$item->file_path) : '#') }}">{{ $item->display_title }}</a></h2>
        @if($item->starts_on)<p class="mt-3">{{ $item->starts_on->format('d/m/Y') }} @if($item->ends_on) a {{ $item->ends_on->format('d/m/Y') }} @endif</p>@endif
        @if($item->file_path)<a class="button mt-4 inline-block" href="{{ url('storage/'.$item->file_path) }}">Ler ou baixar arquivo</a>@endif
    </div>
</article>

@endif
