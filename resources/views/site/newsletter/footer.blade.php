<section id="boletim" aria-labelledby="boletim-titulo" class="border-b border-white/30">
    <div class="site-container grid items-center gap-4 py-6 md:grid-cols-2">
        <div>
            <h2 id="boletim-titulo">Receba o boletim da Arquidiocese</h2>
            <p>Notícias, artigos, eventos e muito mais no seu e-mail.</p>
        </div>
        <form action="{{ route('site.newsletter.subscribe') }}" method="post">
            @csrf
            @if(session('newsletter_requested'))
                <div role="alert" class="mb-4 flex items-start gap-3 rounded-lg border-l-4 border-amber-500 bg-amber-50 p-4 text-stone-900">
                    <i class="fa-solid fa-envelope-open-text mt-1 shrink-0 text-brand-dark" aria-hidden="true"></i>
                    <div>
                        <strong class="block text-lg">Confira seu e-mail</strong>
                        <span class="block">Use o link da mensagem para concluir ou gerenciar sua inscrição.</span>
                        <span class="mt-1 block">Se precisar, reenvie o link após um minuto.</span>
                    </div>
                </div>
            @elseif(session('newsletter_status'))
                <p role="status">{{ session('newsletter_status') }}</p>
            @endif
            <label for="boletim-email">Seu e-mail</label>
            <div class="mt-1 grid gap-2 sm:flex">
                <input class="form-field min-w-0 flex-1 text-stone-900" id="boletim-email" type="email" name="newsletter_email"
                    value="{{ old('newsletter_email') }}" maxlength="254" autocomplete="email" required
                    @if($errors->newsletter->has('newsletter_email')) aria-invalid="true" aria-describedby="boletim-erro" @endif>
                <button class="min-h-11 cursor-pointer rounded bg-white px-5 py-2 font-bold text-brand-dark" type="submit">
                    {{ session('newsletter_requested') ? 'Reenviar link' : 'Quero receber' }}
                </button>
            </div>
            @error('newsletter_email', 'newsletter')<p id="boletim-erro" role="alert">{{ $message }}</p>@enderror
        </form>
    </div>
</section>
