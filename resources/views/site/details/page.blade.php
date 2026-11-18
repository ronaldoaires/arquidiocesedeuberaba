<article class="site-container py-12"><h1 class="legacy-badge">{{ $title }}</h1><div class="mx-auto lg:w-2/3 xl:w-3/4">
    @if(($related['sections']??collect())->isNotEmpty())
        <div class="my-12 page-accordion"><details open><summary>{{ $title }}</summary><div class="p-5">
            @if($record->image_url)<a href="{{ $record->image_url }}" data-lightbox class="mb-4 block text-center"><img src="{{ $record->image_url }}" alt="{{ $title }}" class="mx-auto h-auto max-h-[500px] w-auto max-w-full"></a>@endif
            <div class="editorial-content">{!! $record->safe_body !!}</div>
        </div></details>
        @foreach($related['sections'] as $section)<details><summary>{{ $section->display_title }}</summary><div class="editorial-content p-5">{!! $section->safe_body !!}</div></details>@endforeach</div>
    @else
        @if($record->image_url)<a href="{{ $record->image_url }}" data-lightbox class="block text-center"><img src="{{ $record->image_url }}" alt="{{ $title }}" class="mx-auto h-auto max-h-[500px] w-auto max-w-full"></a>@endif
        <h2 class="mt-6 mb-2 pb-4 text-[calc(1.375rem+1.5vw)] leading-[1.2] font-medium xl:text-[40px]">{{ $title }}</h2><div class="editorial-content">{!! $record->safe_body !!}</div>
    @endif
</div></article>
