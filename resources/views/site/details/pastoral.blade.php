<article class="site-container py-12"><div class="mx-auto lg:w-5/6">
    <div class="mb-6 rounded-md bg-white p-6 shadow-sm"><div class="editorial-content mt-4 text-[#6c757d]">{!! $record->safe_body !!}</div></div>
    @if(($related['downloads']??collect())->isNotEmpty())
        <section class="mb-12"><h2 class="pastoral-heading"><i class="fa-solid fa-cloud-arrow-down mr-2" aria-hidden="true"></i>Downloads</h2>
            <div class="overflow-hidden rounded-md shadow-sm">
                @foreach($related['downloads'] as $download)
                    @php($extension=strtolower(pathinfo($download->file_path,PATHINFO_EXTENSION)))
                    <div class="flex flex-wrap items-center gap-3 border border-[#dee2e6] p-4">
                        <i class="fa-solid {{ in_array($extension,['mp3','wav'])?'fa-music':'fa-file' }} w-10 text-center text-2xl text-[#0d6efd]" aria-hidden="true"></i>
                        <div class="min-w-0 flex-1"><h3>{{ $download->title }}</h3><small class="text-[0.7rem] text-[#6c757d] uppercase">{{ $extension }}</small></div>
                        <a class="rounded border px-3 py-2 text-brand-blue" data-file-preview data-file-type="{{ $extension }}" data-file-title="{{ $download->title }}" data-file-download="{{ route('site.pastoral-files.download', $download->id) }}" href="{{ route('site.pastoral-files.preview', $download->id) }}">Abrir</a>
                        <a class="rounded border px-3 py-2 text-brand-blue" download href="{{ route('site.pastoral-files.download', $download->id) }}">Baixar</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
    @if(($related['videos']??collect())->isNotEmpty())
        <section class="mb-12"><h2 class="pastoral-heading"><i class="fa-solid fa-video mr-2" aria-hidden="true"></i>Vídeos</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">@foreach($related['videos'] as $video)<x-site.youtube-video :video="$video" />@endforeach</div>
        </section>
    @endif
    @if(($related['galleries']??collect())->isNotEmpty())<section class="mb-12"><h2 class="pastoral-heading"><i class="fa-solid fa-images mr-2" aria-hidden="true"></i>Galeria de Fotos</h2><div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">@foreach($related['galleries'] as $gallery)<div><p class="mb-4">{{ $gallery->title }}</p><a href="{{ $gallery->image_url }}" data-lightbox data-gallery="pastoral-{{ $gallery->id }}"><img src="{{ $gallery->thumbnail_url }}" alt="{{ $gallery->title??'Galeria' }}" loading="lazy" class="h-[200px] w-full rounded object-cover shadow-sm"></a>@foreach($gallery->photos()->published()->get() as $photo)<a href="{{ $photo->image_url }}" data-lightbox data-gallery="pastoral-{{ $gallery->id }}" class="hidden"><img src="{{ $photo->thumbnail_url }}" alt="{{ $photo->title??'Foto da pastoral' }}" loading="lazy"></a>@endforeach</div>@endforeach</div></section>@endif
    <section class="mt-12 rounded-md bg-[#f8f9fa] p-12 text-center"><h2 class="mb-6 text-2xl leading-[1.2]">Fale Conosco</h2><p class="mb-4 text-xl"><i class="fa-solid fa-location-dot mr-2 text-[#dc3545]" aria-hidden="true"></i>{{ $record->address??'Endereço não informado' }}</p>@if($record->email)<p class="mb-6"><i class="fa-solid fa-envelope mr-2 text-[#0d6efd]" aria-hidden="true"></i>{{ $record->email }}</p>@endif<div class="flex justify-center gap-4 text-[1.8rem]">@foreach(['facebook'=>['fa-facebook','text-[#0d6efd]'],'instagram'=>['fa-instagram','text-[#dc3545]'],'youtube'=>['fa-youtube','text-[#dc3545]'],'whatsapp'=>['fa-whatsapp','text-[#198754]']] as $field=>[$icon,$color])@if($record->{$field})<a href="{{ $field==='whatsapp'?'https://wa.me/'.preg_replace('/\D/','',$record->{$field}):$record->{$field} }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($field) }} da pastoral" class="{{ $color }}"><i class="fa-brands {{ $icon }}" aria-hidden="true"></i></a>@endif @endforeach</div></section>
    @if($record->image_url)<img class="w-full" src="{{ $record->image_url }}" alt="{{ $title }}">@endif
</div></article>
