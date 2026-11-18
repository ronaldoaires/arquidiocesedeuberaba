@extends('site.layouts.app')
@section('content')
@php($monthNames = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'])
<div class="bg-[#f8f9fa]"><section class="site-container p-6 md:p-12">
    <div class="mb-12 flex flex-wrap items-center justify-between gap-6"><div><h1 class="text-[calc(1.425rem+2.1vw)] leading-[1.2] font-bold text-[#00b73e] xl:text-5xl">Agenda Pastoral</h1><h2 class="mt-2 text-[calc(1.325rem+0.9vw)] leading-[1.2] text-[#6c757d] xl:text-[32px]">{{ $month ? $monthNames[(int)$month].' de ' : '' }}{{ $year }}</h2></div>
        <div class="flex gap-4" aria-label="Filtros da agenda">
            <details class="agenda-filter" data-hover-menu><summary><i class="fa-regular fa-calendar-days mr-2" aria-hidden="true"></i>{{ $month ? $monthNames[(int)$month] : 'Mês' }} ▾</summary><div class="utility-panel">
                @foreach($monthNames as $monthNumber=>$monthName)<a href="{{ route('site.agenda.month',['month'=>$monthNumber,'year'=>$year]) }}" @if((int)$month===$monthNumber) aria-current="page" @endif>{{ $monthName }}</a>@endforeach
            </div></details>
            @if($years->isNotEmpty())<details class="agenda-filter year-filter" data-hover-menu><summary><i class="fa-solid fa-calendar mr-2" aria-hidden="true"></i>{{ $year }} ▾</summary><div class="utility-panel">@foreach($years as $availableYear)<a href="{{ route('site.agenda',['year'=>$availableYear]) }}">{{ $availableYear }}</a>@endforeach</div></details>@endif
        </div>
    </div>
    @forelse($records->groupBy(fn($event)=>$event->starts_on->month) as $eventMonth=>$events)
        @if(!$month)<h2 class="my-6 border-b border-[#dee2e6] py-2 text-center text-[40px] font-bold text-brand-blue">{{ $monthNames[$eventMonth] }}</h2>@endif
        @foreach($events as $event)<article class="mb-6 grid items-center overflow-hidden rounded-2xl bg-white shadow-md md:grid-cols-12">
            <div class="md:col-span-3">@if($event->image_url)<a href="{{ $event->public_url }}"><img src="{{ $event->thumbnail_url }}" alt="{{ $event->title }}" class="parish-cover"></a>@endif</div>
            <div class="p-4 md:col-span-5"><h3 class="mb-2 py-4 text-2xl leading-[1.2] font-semibold text-[#333]"><a href="{{ $event->public_url }}">{{ $event->title }}</a></h3><p class="mb-1 text-[#6c757d]"><i class="fa-solid fa-location-dot mr-2 text-[#0d6efd]" aria-hidden="true"></i>{{ $event->location }}</p><p class="mb-1 text-[#6c757d]"><i class="fa-solid fa-phone mr-2 text-[#198754]" aria-hidden="true"></i>{{ $event->contact }}</p></div>
            <div class="p-4 md:col-span-4 md:text-right"><p class="mb-2 px-2 py-1 font-bold text-[#00b73e]"><i class="fa-regular fa-calendar mr-2" aria-hidden="true"></i>{{ $event->starts_on->format('d/m/Y') }}@if($event->ends_on) até {{ $event->ends_on->format('d/m/Y') }}@endif</p><a href="{{ $event->public_url }}" class="mt-4 inline-block rounded-full border border-[#6c757d] px-3 py-1 text-sm text-[#6c757d]"><i class="fa-solid fa-circle-info mr-1" aria-hidden="true"></i>Mais detalhes</a></div>
        </article>@endforeach
    @empty<p class="legacy-warning">Nenhum evento cadastrado para exibir!</p>@endforelse
</section></div>
@endsection
