<section class="site-container py-12"><div class="mx-auto lg:w-3/4">
    <h1 class="mb-12 text-center text-[calc(1.375rem+1.5vw)] leading-[1.2] font-medium xl:text-[40px]">Requisição de Apoio em Articulação e Divulgação</h1>
    @if(session('status'))<p role="status" class="mb-4 rounded bg-[#d1e7dd] p-4 text-[#0f5132]">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="mb-4 rounded bg-[#f8d7da] p-4 text-[#842029]">Confira os campos abaixo.<ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="post" enctype="multipart/form-data" class="support-form">@csrf<div class="hidden" aria-hidden="true"><label>Site<input name="website" tabindex="-1" autocomplete="off"></label></div>
    @foreach([
        ['Dados da Paróquia/Órgão Solicitante',['parish_name','parish_address','parish_phone','parish_email','contact_name','contact_phone','contact_email']],
        ['Dados da Ação/Evento',['event_title','event_date','event_time','event_location','event_description','event_type','other_event_type','has_speaker','speaker_name','speaker_description','has_cost','event_cost']],
        ['Materiais de Divulgação',['materials_text','materials_photos','materials_videos','materials_posters','materials_other_enabled','materials_other']],
        ['Recursos de Comunicação Desejados',['resource_website','resource_social','resource_radio','resource_newspaper','resource_artwork','resource_coverage','resource_other_enabled','resource_other']],
        ['Prazos',['promotion_start','materials_deadline']],
        ['Informações Adicionais',['notes']],
    ] as [$heading,$fields])<section class="support-section"><h2>{{ $heading }}</h2><div class="grid gap-4 md:grid-cols-12">@foreach($fields as $field) @include('site.partials.support-field') @endforeach</div></section>@endforeach
    <section class="support-section"><h2>Anexar Arquivos</h2><label for="support-attachments" class="mb-4 flex items-center"><span class="rounded-l border border-[#dee2e6] bg-[#e9ecef] px-3 py-1.5">Anexos</span><input type="file" id="support-attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip,.rar,.mp3,.mp4,.mov" class="form-field rounded-l-none"></label><p class="text-sm text-[#6c757d]">Até 10 arquivos, 50 MB por arquivo.</p></section>
    <section class="support-section"><h2>Termo de Responsabilidade</h2><p class="mb-4">Declaro que as informações fornecidas neste formulário são verdadeiras e que possuo autorização para solicitar a divulgação deste evento em nome da paróquia/órgão solicitante.</p><label class="mb-4 flex items-start gap-2 text-[#dc3545]"><input type="checkbox" name="responsibility_accepted" value="1" required @checked(old('responsibility_accepted')) class="mt-1">Concordo com os termos e condições estabelecidos pelo setor de comunicação da diocese para a divulgação de eventos. *</label></section>
    <button class="mt-6 w-full cursor-pointer rounded-lg border border-[#198754] bg-[#198754] px-4 py-2 text-xl text-white">Enviar Solicitação</button>
    </form>
</div></section>
