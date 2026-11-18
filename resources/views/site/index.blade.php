@extends('site.layouts.app')
@section('content')
@php($paddedListing = in_array($module['source'], ['clero','galeria']))
@php($citySidebar = $module['source']==='paroquia' && request()->route('parent_module')!=='paroquia_forania')
<div class="{{ in_array($module['source'], ['clero','galeria','jornal'])?'bg-[#f8f9fa]':'' }}">
<section class="site-container {{ $paddedListing?'p-12':'py-12' }}">
    <h1 class="{{ in_array($module['source'],['clero','galeria','jornal'])?'normal-case':'' }} {{ $module['source']==='noticia'?'inline-block rounded bg-brand-red px-2 py-2 font-bold text-white':'legacy-badge' }} {{ $module['source']==='jornal'?'mx-auto block w-fit rounded-full px-6 py-2 text-center text-xl':'' }}">{{ $title }}</h1>
    <div class="{{ $citySidebar?'grid gap-x-6 xl:grid-cols-4':'' }}">
        @if($citySidebar)<aside class="city-filter my-12" aria-label="Filtrar paróquias por cidade"><h2 class="mb-4 font-bold text-brand-blue">CIDADES</h2>
            @foreach($cities as $city)
                <a href="{{ url('paroquias/cidade/'.$city->slug) }}" @if(request()->route('slug')===$city->slug) aria-current="page" @endif>{{ $city->name }}</a>
            @endforeach
        </aside>@endif
        <div class="{{ $citySidebar?'xl:col-span-3':'' }}">
            @if($records->isNotEmpty())<div class="listing-grid {{ $module['source']==='artigo'?'article-listing':'' }}">
                @foreach($records as $item) @include('site.partials.listing-card') @endforeach
            </div>@else<p class="legacy-warning">{{ match($module['source']) {'clero'=>'Nenhum clero cadastrada para exibir!','paroquia'=>'Nenhuma paróquia cadastrada para exibir!','noticia'=>'Nenhuma notícia cadastrada para exibir!','pagina'=>'Nenhuma página cadastrada para exibir!','instituto'=>'Nenhum instituto cadastrado para exibir!','artigo'=>'Nenhum artigo cadastrado!','galeria'=>'Nenhuma galeria cadastrada para exibir!','jornal'=>'Nenhum jornal cadastrado para exibir!',default=>'Nenhum registro disponível.'} }}</p>@endif
            @include('site.partials.pagination')
        </div>
    </div>
</section>
</div>
@endsection

