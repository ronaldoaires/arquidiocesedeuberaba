<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' - ' : '' }}Arquidiocese de Uberaba</title>
    <meta name="description" content="{{ isset($record) ? Str::limit(strip_tags($record->body_html ?? ''), 160) : strip_tags($settings?->description ?? 'Arquidiocese de Uberaba') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title ?? 'Arquidiocese de Uberaba' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if(isset($record) && $record->image_url)<meta property="og:image" content="{{ $record->image_url }}">@endif
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.5.0/css/all.css">
    <link rel="icon" href="{{ asset('assets/shared/brasao32x32.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alata&family=Cinzel:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-[#646464] antialiased" style="--hero-image:url('{{ asset('assets/site/bg-slide.jpg') }}');--quick-image:url('{{ asset('assets/site/bg-acesso-rapido.webp') }}');--radio-image:url('{{ asset('assets/site/bg-radios.webp') }}');--media-image:url('{{ asset('assets/site/bg-midias.jpg') }}')">
<a href="#main-content" class="sr-only focus:not-sr-only focus:block focus:bg-white focus:p-3">Ir para o conteúdo</a>
@include('site.partials.header')
<main id="main-content">@yield('content')</main>
@include('site.partials.footer')
<dialog id="media-dialog" aria-label="Imagem ampliada" class="m-auto max-h-[95vh] max-w-[95vw] rounded bg-white p-3 backdrop:bg-black/80">
    <button type="button" data-close-dialog class="mb-2 block min-h-11 px-3" autofocus>Fechar imagem</button>
    <div class="relative grid place-items-center">
        <button type="button" data-gallery-direction="-1" aria-label="Foto anterior" hidden class="absolute top-1/2 left-0 z-10 min-h-11 min-w-11 -translate-y-1/2 rounded bg-black/60 text-3xl text-white">&lsaquo;</button>
        <img alt="" class="max-h-[80vh] max-w-full object-contain" id="dialog-image" hidden>
        <p data-image-status role="status" class="px-12 py-14 text-center empty:hidden"></p>
        <button type="button" data-gallery-direction="1" aria-label="Próxima foto" hidden class="absolute top-1/2 right-0 z-10 min-h-11 min-w-11 -translate-y-1/2 rounded bg-black/60 text-3xl text-white">&rsaquo;</button>
    </div>
</dialog>
@include('site.partials.video-dialog')
<x-site.file-dialog />
</body>
</html>

