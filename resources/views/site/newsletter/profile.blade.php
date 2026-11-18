@extends('site.layouts.app', ['title' => $confirming ? 'Concluir inscrição' : 'Atualizar meus dados'])
@section('content')
<section class="site-container py-12">
    <div class="mx-auto max-w-xl">
        <h1 class="mb-4 text-3xl font-bold text-brand-red">{{ $confirming ? 'Concluir inscrição no boletim' : 'Atualizar meus dados' }}</h1>
        @if(session('newsletter_status'))<p role="status" class="mb-4 rounded bg-green-50 p-4 text-green-900">{{ session('newsletter_status') }}</p>@endif
        <p class="mb-4 break-words">E-mail da inscrição: <strong>{{ $subscriber->email }}</strong></p>
        <p class="mb-4">Nome, cidade e estado são opcionais.</p>
        <form method="post" action="{{ request()->fullUrl() }}" class="grid gap-4">
            @csrf
            @foreach(['name' => ['Nome', 'name'], 'city' => ['Cidade', 'address-level2']] as $field => [$label, $autocomplete])
                <div>
                    <label for="boletim-{{ $field }}">{{ $label }}</label>
                    <input id="boletim-{{ $field }}" class="form-field" type="text" name="{{ $field }}" autocomplete="{{ $autocomplete }}"
                        maxlength="120" value="{{ is_string(old($field, $subscriber->$field)) ? old($field, $subscriber->$field) : '' }}"
                        @error($field) aria-invalid="true" aria-describedby="erro-{{ $field }}" @enderror>
                    @error($field)<p role="alert" id="erro-{{ $field }}" class="text-brand-red">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div>
                <label for="boletim-state">Estado (UF)</label>
                <select class="form-field" name="state" id="boletim-state" autocomplete="address-level1"
                    @error('state') aria-invalid="true" aria-describedby="erro-state" @enderror>
                    <option value="">Selecione, se desejar</option>
                    @foreach(config('newsletter.states') as $state)
                        <option value="{{ $state }}" @selected(old('state', $subscriber->state) === $state)>{{ $state }}</option>
                    @endforeach
                </select>
                @error('state')<p role="alert" id="erro-state" class="text-brand-red">{{ $message }}</p>@enderror
            </div>
            <button class="button" type="submit">{{ $confirming ? 'Confirmar inscrição' : 'Salvar alterações' }}</button>
        </form>
        @unless($confirming)<a class="mt-4 inline-block py-3 text-brand-red underline" href="{{ $cancelUrl }}">Cancelar inscrição</a>@endunless
    </div>
</section>
@endsection
