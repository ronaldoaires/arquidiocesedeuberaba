<header class="bg-brand-red">
    <div class="bg-brand-dark text-white">
        <div class="site-container utility-bar">
            <div class="utility-links">
                <a href="https://open.spotify.com/show/7kduSUGP6jlDIPLACcJOSC?si=539257db2d5e404f&amp;nd=1" target="_blank" rel="noopener"><i class="fa-brands fa-spotify text-brand-gold" aria-hidden="true"></i> Acompanhe nosso PodCast</a>
                <details class="utility-dropdown relative" data-hover-menu><summary><i class="fa-solid fa-radio text-brand-gold" aria-hidden="true"></i> Rádios <span aria-hidden="true">▾</span></summary><div class="utility-panel">
                    <a href="{{ url('radio/metropolitana') }}">Rádio Metropolitana</a>
                    <a href="https://www.romafm.com.br" target="_blank" rel="noopener">Rádio Roma FM</a>
                    <a href="http://play.radios.com.br/65809/" target="_blank" rel="noopener">Rádio Beira Rio 87.9</a>
                    <a href="https://radiosacramentofm.com.br" target="_blank" rel="noopener">Rádio Sacramento FM</a>
                </div></details>
                <a href="{{ route('site.contact') }}"><i class="fa-solid fa-envelope text-brand-gold" aria-hidden="true"></i> Contato</a>
                <a href="{{ url('pagina/categoria/voz-da-arquidiocese') }}"><i class="fa-solid fa-comment text-brand-gold" aria-hidden="true"></i> Voz da Arquidiocese</a>
            </div>
            <div class="social-links">
                @foreach(['x'=>'X','youtube'=>'YouTube','instagram'=>'Instagram','facebook'=>'Facebook'] as $network=>$label)
                    @if($settings?->{$network})<a href="{{ $settings->{$network} }}" target="_blank" rel="noopener" aria-label="{{ $label }} da Arquidiocese"><i class="fa-brands fa-{{ $network==='x'?'x-twitter':($network==='facebook'?'facebook-f':$network) }}" aria-hidden="true"></i></a>@endif
                @endforeach
                @if($settings?->whatsapp)<a href="https://api.whatsapp.com/send?1=pt_BR&amp;phone=55{{ preg_replace('/\D/', '', $settings->whatsapp) }}" target="_blank" rel="noopener" aria-label="WhatsApp da Arquidiocese"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></a>@endif
            </div>
        </div>
    </div>
    <div class="site-container brand-row">
        <div class="brand-column"><a href="{{ route('site.home') }}"><img class="w-[306px] max-w-full" src="{{ $settings?->logo_url ?? asset('assets/site/logoVermelho.png') }}" alt="Arquidiocese de Uberaba"></a></div>
        <form action="{{ route('site.search') }}" class="header-search" role="search">
            <label class="sr-only" for="site-search">Buscar</label>
            <input id="site-search" type="search" name="q" minlength="2" maxlength="100" required placeholder="Buscar..." class="form-field">
            <button type="submit" class="search-button">Buscar</button>
        </form>
    </div>
    <nav aria-label="Navegação principal" class="site-container pb-4">
        <button type="button" data-nav-toggle aria-expanded="false" aria-controls="navigation-menu" class="navigation-toggle"><i class="fa-solid fa-bars" aria-hidden="true"></i><span class="sr-only">Abrir menu</span></button>
        <div id="navigation-menu" class="navigation-menu">
            <details class="nav-dropdown" data-menu="archdiocese" data-hover-menu><summary>Arquidiocese</summary><div class="nav-panel">
                @include('site.partials.page-menu', ['group'=>'arquidiocese'])
                @if($menus['pagina']->where('menu_group','arquidiocese')->isNotEmpty())
                    <details class="nav-dropdown nested-menu" data-hover-menu><summary>Ordens e Congregações</summary><div class="nav-panel">
                        @foreach($menus['instituto_categoria'] as $category)<a href="{{ url('institutos/'.$category->slug) }}">{{ $category->title }}</a>@endforeach
                    </div></details>
                @endif
            </div></details>
            <details class="nav-dropdown" data-menu="pastoral" data-hover-menu><summary>Pastoral Arquidiocesana</summary><div class="nav-panel">
                <a href="{{ url('agenda') }}">Agenda Pastoral</a>
                @include('site.partials.page-menu', ['group'=>'pastoral'])
                @foreach($menus['pastoral'] as $pastoral)<a href="{{ url('pastoral/'.$pastoral->slug) }}">{{ $pastoral->title }}</a>@endforeach
            </div></details>
            <details class="nav-dropdown" data-menu="clergy" data-hover-menu><summary>Clero</summary><div class="nav-panel">
                @foreach($menus['clero_categoria'] as $category)<a href="{{ url('cleros/'.$category->slug) }}">{{ $category->title }}</a>@endforeach
                @include('site.partials.page-menu', ['group'=>'clero'])
            </div></details>
            <a href="{{ url('paroquias') }}">Paróquias</a>
            <details class="nav-dropdown" data-menu="deaneries" data-hover-menu><summary>Foranias Pastorais</summary><div class="nav-panel">
                @foreach($menus['paroquia_forania'] as $deanery)<a href="{{ url('foranias/'.$deanery->slug) }}">{{ $deanery->name }}</a>@endforeach
            </div></details>
            <details class="nav-dropdown" data-menu="news" data-hover-menu><summary>Notícias</summary><div class="nav-panel">
                @foreach($menus['noticia_categoria'] as $category)<a href="{{ url('categoria/noticias/'.$category->slug) }}">{{ $category->title }}</a>@endforeach
                @foreach($menus['pagina']->where('menu_group','noticias') as $menuPage)<a href="{{ url('pagina/'.$menuPage->slug) }}">{{ $menuPage->display_title }}</a>@endforeach
            </div></details>
            <a href="{{ url('liturgia-diaria') }}">Liturgia Diária (CNBB)</a>
            <a href="{{ url('correio-catolico') }}">Correio Católico</a>
        </div>
    </nav>
</header>
