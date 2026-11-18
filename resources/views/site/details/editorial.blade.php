<article class="site-container py-12"><div class="mx-auto {{ $module['source']==='instituto'?'lg:w-1/2':'lg:w-2/3' }}">
    @if($record->image_url)<a class="block overflow-hidden text-center" href="{{ $record->image_url }}" data-lightbox><img src="{{ $module['source']==='instituto'?$record->image_url:$record->thumbnail_url }}" alt="{{ $title }}" class="mx-auto h-auto max-h-[500px] w-auto max-w-full"></a>@endif
    @if($record->created_at)<p class="mb-4 pt-4"><i class="fa-solid fa-calendar-days text-[#198754]" aria-hidden="true"></i> {{ $record->created_at->timezone('America/Sao_Paulo')->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y') }}</p>@endif
    <h1 class="mb-2 pb-4 text-[calc(1.375rem+1.5vw)] leading-[1.2] font-medium text-[#646464] xl:text-[40px]">{{ $title }}</h1>
    <div class="editorial-content">{!! $record->safe_body !!}</div>
    @if($module['source'] === 'galeria')
        @php($photos = $related['photos'])
        <section id="fotos" class="scroll-mt-6 pt-4" aria-label="Fotos da galeria">
            @if($photos->isEmpty())
                <p class="text-center">Nenhuma foto publicada nesta galeria.</p>
            @else
                <p class="text-center text-sm">Fotos {{ $photos->firstItem() }} a {{ $photos->lastItem() }} de {{ number_format($photos->total(), 0, ',', '.') }}</p>
                <div class="gallery-photos">
                    @foreach($photos as $photo)
                        <a href="{{ $photo->image_url }}" data-lightbox class="bg-stone-100">
                            <img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->title ?: 'Foto '.($photos->firstItem() + $loop->index).' da galeria' }}"
                                 width="300" height="300" loading="lazy" decoding="async">
                        </a>
                    @endforeach
                </div>
                @include('site.partials.pagination', ['records' => $photos, 'paginationUrl' => fn (int $page) => $photos->url($page)])
            @endif
        </section>
    @endif
</div></article>
