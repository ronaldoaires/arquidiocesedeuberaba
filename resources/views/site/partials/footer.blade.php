<footer class="site-footer bg-brand-red text-white">
    @unless(request()->routeIs('site.newsletter.*'))
        @include('site.newsletter.footer')
    @endunless
    <div class="site-container footer-content">
        <section class="footer-recommendations"><h2><i class="fa-solid fa-thumbs-up" aria-hidden="true"></i> Indicamos</h2>
            <ul><li><a href="https://www.cnbb.org.br/" target="_blank" rel="noopener">CNBB</a></li><li><a href="https://www.vaticannews.va/pt.html" target="_blank" rel="noopener">Vatican News</a></li><li><a href="https://www.redevida.com.br/" target="_blank" rel="noopener">Rede Vida</a></li></ul>
        </section>
        <section class="footer-institutional"><h2><i class="fa-solid fa-church" aria-hidden="true"></i> Institucional</h2><ul>
            @foreach([['pagina/historia','Arquidiocese'],['cleros/bispos-e-arcebispos','Arcebispo'],['solicitar','Requisição de Apoio'],['correio-catolico','Correio Católico'],['pagina/curia-metropolitana','Cúria Metropolitana'],['pagina/chancelaria','Chancelaria'],['pagina/tribunal-eclesiatico','Tribunal Eclesiástico'],['pagina/categoria/comissoes-pastorais','Pastoral Arquidiocesana'],['categoria/noticias/noticias-arquidiocesanas','Notícias da Arquidiocese'],['videos','Vídeos']] as [$path,$label])<li><a href="{{ url($path) }}">{{ $label }}</a></li>@endforeach
        </ul></section>
        <section class="footer-contact"><h2><i class="fa-solid fa-message" aria-hidden="true"></i> Fale com a Arquidiocese</h2>
            @if($settings?->phone)<p><i class="fa-solid fa-phone text-white" aria-hidden="true"></i> {{ $settings->phone }}</p>@endif
            @if($settings?->whatsapp)
                @php($phoneDigits = preg_replace('/\D/', '', $settings->whatsapp))
                @php($phoneDisplay = preg_replace('/^(\d{2})(\d{4,5})(\d{4})$/', '($1) $2-$3', $phoneDigits))
                <p><a href="https://api.whatsapp.com/send?1=pt_BR&amp;phone=55{{ $phoneDigits }}" title="WhatsApp"><i class="fa-brands fa-whatsapp text-white" aria-hidden="true"></i> {{ $phoneDisplay }}</a></p>
            @endif
            @if($settings?->email)<h2><i class="fa-solid fa-envelope mr-2" aria-hidden="true"></i> E-mail</h2><p class="break-words"><a href="mailto:{{ $settings->email }}">{{ $settings->email }}</a></p>@endif
            @if($settings?->address)<h2><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Endereço</h2><p>{{ $settings->address }}</p>@endif
        </section>
    </div>
    <div class="bg-brand-dark py-4 text-center"><div class="site-container">
        <a href="{{ route('site.home') }}"><img class="mx-auto mb-2 w-[200px]" src="{{ $settings?->logo_url ?? asset('assets/site/logoVermelho.png') }}" alt="Arquidiocese de Uberaba"></a>
        <small class="text-[0.8rem] text-white/75">Copyright © {{ now('America/Sao_Paulo')->year }} {{ $settings?->title ?? 'Arquidiocese de Uberaba' }}. Todos os Direitos Reservados</small>
    </div></div>
</footer>
