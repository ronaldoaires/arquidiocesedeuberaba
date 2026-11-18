@extends('site.layouts.app', ['title' => 'Solicitar novo link'])
@section('content')
<section class="site-container py-12"><div class="mx-auto max-w-xl">
    <h1 class="mb-4 text-3xl font-bold text-brand-red">Este link perdeu a validade</h1>
    <p class="mb-4">Solicite um novo link no rodapé para confirmar ou gerenciar sua inscrição.</p>
    <a class="button inline-block" href="{{ route('site.home') }}#boletim">Solicitar novo link</a>
</div></section>
@endsection
