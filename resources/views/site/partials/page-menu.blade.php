@php($groupPages = $menus['pagina']->where('menu_group', $group))
@foreach($groupPages->whereNull('category_id') as $menuPage)
    <a href="{{ url('pagina/'.$menuPage->slug) }}">{{ $menuPage->display_title }}</a>
@endforeach
@foreach($menus['pagina_categoria'] as $category)
    @if($groupPages->where('category_id', $category->id)->isNotEmpty())
        <a href="{{ url('pagina/categoria/'.$category->slug) }}">{{ $category->title }}</a>
    @endif
@endforeach
