@extends('site.layouts.app')
@section('content')
@if(in_array($module['source'], ['noticia','artigo','instituto','galeria','slide']))
    @include('site.details.editorial')
@elseif($module['source']==='pagina')
    @include('site.details.page')
@elseif($module['source']==='clero')
    @include('site.details.clergy')
@elseif($module['source']==='paroquia')
    @include('site.details.parish')
@elseif($module['source']==='radio')
    @include('site.details.radio')
@elseif($module['source']==='pastoral')
    @include('site.details.pastoral')
@elseif($module['source']==='evento')
    @include('site.details.event')
@else
<article class="site-container py-10">
    <div class="mx-auto max-w-4xl">
        @if($record->image_url)<a href="{{ $record->image_url }}" data-lightbox><img src="{{ $record->image_url }}" alt="{{ $title }}" class="mb-8 max-h-[38rem] w-full rounded object-contain" data-fallback-image="{{ asset('assets/site/capa-dom-paulo.png') }}"></a>@endif
        @if($module['source']==='noticia' && $record->created_at)<p class="mb-6 text-sm text-stone-500">{{ $record->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} @if($record->author_name) — {{ $record->author_name }} @endif</p>@endif
        <h1 class="mb-5 text-3xl font-bold leading-tight text-stone-900 md:text-[2.5rem]">{{ $title }}</h1>
        <div class="editorial-content">{!! $record->safe_body !!}</div>
        @if($record->description)<div class="editorial-content mt-5">{!! app(App\Support\HtmlContent::class)->render($record->description) !!}</div>@endif
        @if($module['source']==='radio')<section class="my-8 rounded-lg bg-brand-blue p-6 text-white"><h2 class="mb-4 text-2xl">Ouça a Rádio Metropolitana</h2><iframe src="https://radio.escolaemrede.com.br/public/metropolitana/embed?hide_streams=1" title="Rádio Metropolitana ao vivo" frameborder="0" allowtransparency="true" style="width: 40%; min-height: 100px; height: 100px; border: 0;"></iframe></section>@endif
        @if($record->file_path)
            @if($module['source']==='jornal')
                <div class="my-5 flex flex-wrap gap-3"><a class="button" data-file-preview data-file-title="{{ $record->display_title }}" data-file-type="{{ strtolower(pathinfo($record->file_path, PATHINFO_EXTENSION)) }}" data-file-download="{{ route('site.newspapers.download', $record->slug) }}" href="{{ route('site.newspapers.preview', $record->slug) }}">Abrir arquivo</a><a class="button" download href="{{ route('site.newspapers.download', $record->slug) }}">Baixar arquivo</a></div>
            @else
                <a class="button my-5 inline-block" href="{{ url('storage/'.$record->file_path) }}">Abrir arquivo</a>
            @endif
        @endif
        <dl class="my-8 grid gap-5 sm:grid-cols-2">
            @foreach($module['columns'] as $column)
                @if(in_array($column['name'],['phone','email','address','postal_code','birthday_text','ordination_date_text','presentation_date','founded_on_text','location','contact','service_hours','office_hours'],true) && $record->getAttribute($column['name']))
                    <div><dt class="font-bold text-brand-blue">{{ $column['label'] }}</dt><dd class="mt-1 whitespace-pre-line">{{ $column['name'] === 'presentation_date' ? $record->presentation_date->format('d/m/Y') : strip_tags($record->getAttribute($column['name'])) }}</dd></div>
                @endif
            @endforeach
        </dl>
        @if($record->map_html)<div class="editorial-content my-8">{!! app(App\Support\HtmlContent::class)->render($record->map_html) !!}</div>@endif
        @foreach(['facebook'=>'Facebook','instagram'=>'Instagram','youtube'=>'YouTube','site'=>'Site'] as $key=>$label)@if($record->getAttribute($key))<a class="mr-4 underline" href="{{ $record->getAttribute($key) }}" rel="noopener" target="_blank">{{ $label }}</a>@endif @endforeach
        @foreach($related as $kind=>$items)
            @if($items->isNotEmpty())<section class="mt-10"><h2 class="mb-6 text-2xl text-brand-red">{{ ['sections'=>'Mais informações','photos'=>'Fotos','downloads'=>'Downloads','videos'=>'Vídeos','galleries'=>'Galerias','assignments'=>'Vínculos paroquiais','programs'=>'Programação','tracks'=>'As mais tocadas'][$kind] }}</h2>
            @if($kind==='sections')@foreach($items as $section)<details class="mb-3 rounded border p-4"><summary class="cursor-pointer text-lg font-bold">{{ $section->display_title }}</summary><div class="editorial-content py-5">{!! $section->safe_body !!}</div></details>@endforeach
            @elseif($kind==='photos')<div class="grid gap-4 grid-cols-2 sm:grid-cols-3 md:grid-cols-6">@foreach($items as $photo)<a href="{{ $photo->image_url }}" data-lightbox><img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->title??'Foto da galeria' }}" loading="lazy" class="aspect-square w-full rounded object-cover"></a>@endforeach</div>
            @elseif($kind==='videos')<div class="grid gap-5 md:grid-cols-2">@foreach($items as $video)<iframe class="aspect-video w-full" src="https://www.youtube-nocookie.com/embed/{{ $video->video_id }}" title="{{ $video->title??'Vídeo da pastoral' }}" loading="lazy" allowfullscreen></iframe>@endforeach</div>
            @elseif($kind==='assignments')<div class="grid gap-4 sm:grid-cols-2">@foreach($items as $assignment)@php($person=$module['source']==='paroquia'?$assignment->clergyMember:$assignment->parish) @if($person && $person->is_active)<div class="rounded border p-5"><a class="font-bold text-brand-red" href="{{ $person->public_url }}">{{ $person->display_title }}</a><p>{{ $assignment->role_text }}</p></div>@endif @endforeach</div>
            @elseif($kind==='programs')@foreach($items as $program)<details class="mb-3 rounded border p-4"><summary class="cursor-pointer text-lg font-bold">{{ $program->title }}</summary>@if($program->image_url)<img src="{{ $program->image_url }}" alt="{{ $program->title }}" class="my-4 max-h-72 object-contain">@endif<div class="editorial-content py-4">{!! $program->safe_body !!}</div><p>{{ $program->day }}/{{ $program->month }}/{{ $program->year }}</p></details>@endforeach
            @elseif($kind==='galleries')@foreach($items as $gallery)<section class="my-5"><h3 class="mb-4 text-xl">{{ $gallery->title }}</h3><div class="grid gap-4 sm:grid-cols-3">@foreach($gallery->photos()->published()->get() as $photo)<a href="{{ $photo->image_url }}" data-lightbox><img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->title??'Foto da pastoral' }}" loading="lazy" class="aspect-square w-full object-cover"></a>@endforeach</div></section>@endforeach
            @elseif($kind==='tracks')<ol class="list-inside list-decimal space-y-3">@foreach($items->sortBy('sort_order') as $track)<li>{{ $track->track_name }} — {{ $track->artist }}</li>@endforeach</ol>
            @elseif($kind==='downloads')<div class="grid gap-3">@foreach($items as $download)<a class="rounded border p-4 text-brand-blue underline" href="{{ url('storage/'.$download->file_path) }}">{{ $download->title }}</a>@endforeach</div>
            @else <div class="grid gap-4 md:grid-cols-3">@foreach($items as $item) @include('site.partials.card') @endforeach</div> @endif
            </section>@endif
        @endforeach
        @if($module['source']==='clero')<h2 class="mt-8 text-xl">Referências preservadas do cadastro anterior</h2>@foreach([1,2,3] as $slot)@if($record->getAttribute('parish_'.$slot.'_text'))<p class="mt-3">{{ $record->getAttribute('parish_'.$slot.'_text') }} — {{ $record->getAttribute('role_'.$slot.'_text') }}</p>@endif @endforeach @endif
    </div>
</article>
@endif
@endsection
