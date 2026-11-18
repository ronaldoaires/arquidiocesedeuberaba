@if($records->hasPages())
@php
    $paginationUrl ??= function (int $number): string {
        $parameters = Arr::only(request()->route()->parameters(), request()->route()->parameterNames());
        $parameters['page'] = $number;
        return route(request()->route()->getName(), $parameters);
    };
@endphp
<nav aria-label="Paginação" class="listing-pagination">
    @if($records->currentPage()>2)<a href="{{ $paginationUrl(1) }}" aria-label="Primeira página">«</a>@endif
    @if($records->onFirstPage())<span aria-disabled="true">‹</span>@else<a href="{{ $paginationUrl($records->currentPage()-1) }}" rel="prev" aria-label="Página anterior">‹</a>@endif
    @for($pageNumber=max(1,$records->currentPage()-3);$pageNumber<=min($records->lastPage(),$records->currentPage()+3);$pageNumber++)
        @if($pageNumber===$records->currentPage())<span aria-current="page">{{ $pageNumber }}</span>@else<a href="{{ $paginationUrl($pageNumber) }}" aria-label="Página {{ $pageNumber }}">{{ $pageNumber }}</a>@endif
    @endfor
    @if($records->hasMorePages())<a href="{{ $paginationUrl($records->currentPage()+1) }}" rel="next" aria-label="Próxima página">›</a><a href="{{ $paginationUrl($records->lastPage()) }}" aria-label="Última página">»</a>@endif
</nav>
@endif
