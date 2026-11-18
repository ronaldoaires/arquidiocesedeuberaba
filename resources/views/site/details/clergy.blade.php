<article class="site-container py-12"><div class="mx-auto lg:w-2/3">
    @if($record->image_url)<a class="block text-center" href="{{ $record->image_url }}" data-lightbox><img src="{{ $record->thumbnail_url }}" alt="{{ $title }}" class="mx-auto h-auto max-h-[500px] w-auto max-w-full"></a>@endif
    <h1 class="mb-2 py-4 text-[calc(1.325rem+0.9vw)] leading-[1.2] font-medium xl:text-[32px]">{{ $title }}</h1>
    @php($affiliations = collect([1,2,3])->filter(fn($slot)=>filled($record->getAttribute('parish_'.$slot.'_text'))))
    @if($affiliations->isNotEmpty())<p class="mb-4"><strong>Paróquia em que atua: </strong>@foreach($affiliations as $slot)@if(!$loop->first), @endif@php($parishLink=$record->getAttribute('parish_'.$slot.'_link'))@if($parishLink && Str::startsWith($parishLink,['https://','http://']))<a href="{{ in_array(parse_url($parishLink,PHP_URL_HOST),['arquidiocesedeuberaba.com.br','www.arquidiocesedeuberaba.com.br']) ? url(parse_url($parishLink,PHP_URL_PATH) ?: '/') : $parishLink }}">{{ $record->getAttribute('parish_'.$slot.'_text') }}</a>@else{{ $record->getAttribute('parish_'.$slot.'_text') }}@endif @endforeach</p>
    @elseif(($related['assignments']??collect())->isNotEmpty())<p class="mb-4"><strong>Paróquia em que atua: </strong>@foreach($related['assignments'] as $assignment)@if($assignment->parish?->is_active)<a href="{{ $assignment->parish->public_url }}">{{ $assignment->parish->display_title }}</a>@if(!$loop->last), @endif @endif @endforeach</p>@endif
    @php($roles=collect([1,2,3])->map(fn($slot)=>$record->getAttribute('role_'.$slot.'_text'))->filter())
    @if($roles->isNotEmpty())<p class="mb-4"><strong>Função: </strong>{{ $roles->join(', ') }}</p>@endif
    @foreach(['birthday_text'=>'Data de Aniversário','ordination_date_text'=>'Data de Ordenação:','presentation_date'=>'Data de Apresentação:'] as $field=>$label)@if($record->{$field})<p class="mb-4"><strong>{{ $label }} </strong>{{ $field === 'presentation_date' ? $record->presentation_date->format('d/m/Y') : $record->{$field} }}</p>@endif @endforeach
    <div class="editorial-content">{!! $record->safe_body !!}</div>
</div></article>
