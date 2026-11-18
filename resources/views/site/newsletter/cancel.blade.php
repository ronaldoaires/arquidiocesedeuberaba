@extends('site.layouts.app', ['title' => 'Cancelar inscrição'])
@section('content')
<section class="site-container py-12"><div class="mx-auto max-w-xl">
    <h1 class="mb-4 text-3xl font-bold text-brand-red">Cancelar inscrição</h1>
    <p class="mb-4 break-words">Deseja deixar de receber o boletim em <strong>{{ $subscriber->email }}</strong>?</p>
    <form method="post" action="{{ request()->fullUrl() }}">
        @csrf
        <button class="button" type="submit">Confirmar cancelamento</button>
        <a class="inline-block px-3 py-3 text-brand-red underline" href="{{ $profileUrl }}">Manter minha inscrição</a>
    </form>
</div></section>
@endsection
