@if($module['source']==='noticia')
    @include('site.partials.card')
@elseif($module['source']==='paroquia')
    <article>
        @if($item->image_url)<a href="{{ $item->public_url }}"><img src="{{ $item->thumbnail_url }}" alt="{{ $item->display_title }}" class="parish-cover" loading="lazy"></a>@endif
        <h2 class="mt-4 text-2xl leading-[1.2] font-bold text-[#333]"><a href="{{ $item->public_url }}">{{ $item->display_title }}</a></h2>
    </article>
@elseif($module['source']==='artigo')
    <article class="my-4 overflow-hidden rounded-md border border-black/15 bg-white shadow">
        @if($item->image_url)<a href="{{ $item->public_url }}"><img src="{{ $item->thumbnail_url }}" alt="{{ $item->display_title }}" class="parish-cover" loading="lazy"></a>@endif
        <h2 class="p-4 text-xl leading-[1.2] font-bold text-[#333]"><a href="{{ $item->public_url }}">{{ $item->display_title }}</a></h2>
    </article>
@elseif($module['source']==='jornal')
    <article class="profile-card">
        @if($item->image_url)<a href="{{ filled($item->file_path) ? route('site.newspapers.preview', $item->slug) : $item->public_url }}" @if(filled($item->file_path)) data-file-preview data-file-title="{{ $item->display_title }}" data-file-type="{{ strtolower(pathinfo($item->file_path, PATHINFO_EXTENSION)) }}" data-file-download="{{ route('site.newspapers.download', $item->slug) }}" @endif><img src="{{ $item->thumbnail_url }}" alt="{{ $item->display_title }}" loading="lazy"></a>@endif
        <div class="profile-card-body"><h2 class="text-base"><a href="{{ $item->public_url }}">{{ $item->display_title }}</a></h2>
        <p class="mb-4 text-sm text-[#6c757d]"><i class="fa-regular fa-newspaper" aria-hidden="true"></i> Edição: {{ $item->edition }}<br><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ $item->publication_period }}</p>
        @if(filled($item->file_path))<div class="mt-auto flex gap-2 text-sm"><a class="flex-1 rounded border border-[#198754] px-2 py-1 text-center text-[#198754]" data-file-preview data-file-title="{{ $item->display_title }}" data-file-type="{{ strtolower(pathinfo($item->file_path, PATHINFO_EXTENSION)) }}" data-file-download="{{ route('site.newspapers.download', $item->slug) }}" href="{{ route('site.newspapers.preview', $item->slug) }}"><i class="fa-solid fa-eye" aria-hidden="true"></i> Ler</a><a class="flex-1 rounded border border-[#6c757d] px-2 py-1 text-center text-[#6c757d]" download href="{{ route('site.newspapers.download', $item->slug) }}"><i class="fa-solid fa-download" aria-hidden="true"></i> Baixar</a></div>@endif</div>
    </article>
@else
    <article class="profile-card {{ $module['source']==='clero'?'self-start h-auto':'' }}">
        @if($item->image_url)<a href="{{ $item->public_url }}"><img src="{{ $item->thumbnail_url }}" alt="{{ $item->display_title }}" loading="lazy"></a>@endif
        <div class="profile-card-body"><h2><a href="{{ $item->public_url }}">{{ $item->display_title }}</a></h2><a class="read-more" href="{{ $item->public_url }}">{{ $module['source']==='galeria'?'Ver mais':'Ler mais' }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
    </article>
@endif
