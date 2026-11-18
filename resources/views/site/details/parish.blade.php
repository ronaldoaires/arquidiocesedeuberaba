<article class="site-container py-12"><div class="mx-auto lg:w-2/3">
    @if($record->image_url)<a href="{{ $record->image_url }}" data-lightbox class="block text-center"><img src="{{ $record->thumbnail_url }}" alt="{{ $title }}" class="mx-auto h-auto max-h-[500px] w-auto max-w-full"></a>@endif
    <h1 class="mb-2 py-6 text-center text-[calc(1.325rem+0.9vw)] leading-[1.2] font-medium xl:text-[32px]">{{ $title }}</h1>
    <h2 class="my-4 border-b border-[#dee2e6] py-4 text-center text-xl font-medium">Informações</h2>
    <div class="grid gap-4 md:grid-cols-2"><div>
        @foreach(['founded_on_text'=>['fa-church','Ano da Criação:'],'city'=>['fa-city','Cidade:'],'deanery'=>['fa-building','Forania:'],'email'=>['fa-envelope','E-mail:'],'phone'=>['fa-phone','Telefone:']] as $field=>[$icon,$label])
            @php($value=in_array($field,['city','deanery'])?$record->{$field}?->name:$record->{$field})
            @if($value)<p class="mb-4"><i class="fa-solid {{ $icon }} text-[#6c757d]" aria-hidden="true"></i> <strong class="text-[#212529]">{{ $label }} </strong>{{ $value }}</p>@endif
        @endforeach
        @if(collect(['facebook','instagram','youtube','site'])->contains(fn($field)=>filled($record->{$field})))<p><i class="fa-solid fa-globe" aria-hidden="true"></i> <strong class="text-[#212529]">Redes Sociais:</strong></p><div class="mt-4 flex gap-2">
            @foreach(['facebook'=>['fa-brands fa-facebook-f','Facebook'],'instagram'=>['fa-brands fa-instagram','Instagram'],'youtube'=>['fa-brands fa-youtube','YouTube'],'site'=>['fa-solid fa-link','Site']] as $field=>[$icon,$label])@if($record->{$field})<a class="rounded bg-[#0d6efd] px-3 py-2 text-white" href="{{ $record->{$field} }}" target="_blank" rel="noopener" aria-label="{{ $label }} da paróquia"><i class="{{ $icon }}" aria-hidden="true"></i></a>@endif @endforeach
        </div>@endif
    </div><div>
        @if($record->service_hours)<div class="mb-4"><strong class="text-[#212529]"><i class="fa-solid fa-clock-rotate-left text-[#6c757d]" aria-hidden="true"></i> Expediente da Secretaria: </strong><div class="editorial-content">{!! app(App\Support\HtmlContent::class)->render($record->service_hours) !!}</div></div>@endif
        @if($record->address)<p class="mb-4"><i class="fa-solid fa-location-dot text-[#6c757d]" aria-hidden="true"></i> <strong class="text-[#212529]">Endereço: </strong>{{ $record->address }}</p>@endif
        @if($record->map_html)<strong class="text-[#212529]"><i class="fa-solid fa-map-location-dot text-[#6c757d]" aria-hidden="true"></i> Como Chegar: </strong><div class="editorial-content">{!! app(App\Support\HtmlContent::class)->render($record->map_html) !!}</div>@endif
    </div></div>
    @if(($related['assignments']??collect())->isNotEmpty())<h2 class="my-4 border-b border-[#dee2e6] py-4 text-center text-xl font-medium">Clero</h2><div class="my-8 flex flex-wrap justify-center gap-6">
        @foreach($related['assignments'] as $assignment)
            @php($member = $assignment->clergyMember)
            @if($member?->is_active)
                <article class="w-full overflow-hidden rounded-md bg-white text-center shadow-sm md:w-[calc(33.333%-16px)]">
                    @if($member->image_url)
                        @if($member->is_active)<a href="{{ $member->public_url }}">@endif
                        <img src="{{ $member->thumbnail_url }}" alt="{{ $member->display_title }}" class="h-auto max-h-[500px] w-full">
                        @if($member->is_active)</a>@endif
                    @endif
                    <div class="p-4">
                        <p class="mb-2 font-bold text-[#333]">
                            @if($member->is_active)<a href="{{ $member->public_url }}">{{ $member->display_title }}</a>@else{{ $member->display_title }}@endif
                        </p>
                        <p>{{ $assignment->role_text ?: implode(', ', array_filter([$member->role_1_text, $member->role_2_text, $member->role_3_text])) }}</p>
                    </div>
                </article>
            @endif
        @endforeach
    </div>@endif
    @if($record->office_hours)<h2 class="my-4 border-b border-[#dee2e6] py-4 text-center text-xl font-medium">Expediente</h2><div class="editorial-content text-center">{!! app(App\Support\HtmlContent::class)->render($record->office_hours) !!}</div>@endif
</div></article>
