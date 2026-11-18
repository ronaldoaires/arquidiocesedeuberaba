<?php

use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\ContentController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\NewspaperFileController;
use App\Http\Controllers\Site\PastoralDownloadController;
use App\Http\Controllers\Site\SearchController;
use App\Http\Controllers\Site\SitemapController;
use App\Support\ContentRegistry;
use Illuminate\Support\Facades\Route;

Route::name('site.')->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/index.php', fn () => redirect()->route('site.home', status: 301))->name('legacy-index');
    Route::get('/404', fn () => abort(404))->name('not-found');
    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/contato', [ContactController::class, 'show'])->defaults('kind', 'contact')->name('contact');
    Route::post('/contato', [ContactController::class, 'store'])->defaults('kind', 'contact')->middleware('throttle:5,1')->name('contact.store');
    Route::get('/solicitar', [ContactController::class, 'show'])->defaults('kind', 'support')->name('support');
    Route::post('/solicitar', [ContactController::class, 'store'])->defaults('kind', 'support')->middleware('throttle:5,1')->name('support.store');
    foreach (['liturgia-diaria' => 'liturgy', 'central-de-midias' => 'media', 'videos' => 'videos'] as $path => $special) {
        Route::get('/'.$path, [ContentController::class, 'special'])->defaults('special', $special)->name($special);
    }
    Route::get('/agenda/{month}/{year}', [ContentController::class, 'agenda'])->whereNumber(['month', 'year'])->name('agenda.month');
    Route::get('/agenda/{year?}', [ContentController::class, 'agenda'])->whereNumber('year')->name('agenda');
    foreach ([
        'pagina/categoria' => ['pagina', 'pagina_categoria', 'category_id'],
        'categoria/noticias' => ['noticia', 'noticia_categoria', 'category_id'],
        'institutos' => ['instituto', 'instituto_categoria', 'category_id'],
        'cleros' => ['clero', 'clero_categoria', 'category_id'],
        'paroquias/cidade' => ['paroquia', 'paroquia_cidade', 'city_id'],
        'foranias' => ['paroquia', 'paroquia_forania', 'deanery_id'],
    ] as $path => [$module, $parent, $key]) {
        Route::get('/'.$path.'/{slug}/{page?}', [ContentController::class, 'index'])
            ->defaults('module', $module)->defaults('parent_module', $parent)->defaults('parent_key', $key)
            ->whereNumber('page')->name('categories.'.ContentRegistry::get($module)['table'].'.'.$key);
    }
    foreach (['noticias' => 'noticia', 'artigos-dom-paulo' => 'artigo', 'paroquias' => 'paroquia', 'galerias' => 'galeria'] as $path => $module) {
        Route::get('/'.$path.'/{page?}', [ContentController::class, 'index'])->defaults('module', $module)->whereNumber('page')->name('list.'.ContentRegistry::get($module)['table']);
    }
    Route::get('/jornais/{page?}', [ContentController::class, 'index'])->defaults('module', 'jornal')->whereNumber('page')->name('newspapers.archive');
    Route::get('/revistas/{page?}', [ContentController::class, 'index'])->defaults('module', 'revista')->whereNumber('page')->name('magazines');
    Route::get('/pastoral/arquivos/{download}/ler', [PastoralDownloadController::class, 'preview'])->whereNumber('download')->name('pastoral-files.preview');
    Route::get('/pastoral/arquivos/{download}/baixar', [PastoralDownloadController::class, 'download'])->whereNumber('download')->name('pastoral-files.download');
    Route::get('/jornal/{slug}/ler', [NewspaperFileController::class, 'preview'])->name('newspapers.preview');
    Route::get('/jornal/{slug}/baixar', [NewspaperFileController::class, 'download'])->name('newspapers.download');
    Route::get('/jornal/{slug}', [ContentController::class, 'show'])->defaults('module', 'jornal')->name('show.newspapers');
    Route::get('/revista/{slug}', [ContentController::class, 'show'])->defaults('module', 'revista')->name('show.magazines');
    Route::get('/correio-catolico', [ContentController::class, 'index'])->defaults('module', 'jornal')->name('newspapers');
    foreach (['destaque' => 'slide', 'radio' => 'radio', 'pastoral' => 'pastoral', 'evento' => 'evento', 'galeria' => 'galeria', 'pagina' => 'pagina', 'instituto' => 'instituto', 'artigo' => 'artigo', 'noticia' => 'noticia', 'clero' => 'clero', 'paroquia' => 'paroquia'] as $path => $module) {
        Route::get('/'.$path.'/{slug}', [ContentController::class, 'show'])->defaults('module', $module)->name('show.'.ContentRegistry::get($module)['table']);
    }
});
