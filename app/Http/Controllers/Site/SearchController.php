<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\ContentRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($data['q'] ?? '');
        $records = collect();
        if ($term !== '') {
            foreach (['noticia', 'artigo', 'paroquia', 'clero'] as $source) {
                $class = ContentRegistry::model($source);
                $field = ContentRegistry::get($source)['title'];
                $records = $records->concat($class::published()->where($field, 'like', '%'.$term.'%')->limit(50)->get());
            }
        }

        return view('site.search', ['records' => $records->sortByDesc('created_at'), 'term' => $term, 'title' => 'Resultados da busca']);
    }
}
