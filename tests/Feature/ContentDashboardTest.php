<?php

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\Magazines\MagazineResource;
use App\Filament\Support\ContentResource;
use App\Filament\Widgets\SiteOverview;
use App\Models\Article;
use App\Models\ClergyMember;
use App\Models\GalleryPhoto;
use App\Models\MediaAsset;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Parish;
use App\Models\User;
use App\Services\ContentDashboard;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::withoutLazyLoading();
});

it('counts the six selected modules in the requested order and preserves records and counters', function (): void {
    $active = Article::factory()->create(['title' => 'Artigo ativo', 'is_active' => true, 'visits' => 1200]);
    Article::factory()->create(['title' => 'Artigo inativo', 'is_active' => false, 'visits' => 50]);
    $deleted = Article::factory()->create(['title' => 'Artigo excluído', 'is_active' => true, 'visits' => 9000]);
    $deleted->delete();
    NewsItem::factory()->create(['title' => 'Notícia publicada', 'is_active' => true, 'visits' => 100, 'created_at' => now()->subDay()]);
    NewsItem::factory()->create(['title' => 'Notícia agendada', 'is_active' => true, 'visits' => 20, 'created_at' => now()->addDay()]);
    NewsItem::factory()->create(['title' => 'Notícia inativa', 'is_active' => false, 'visits' => 5, 'created_at' => now()->addDay()]);
    $page = Page::factory()->create(['title' => 'Página antiga', 'is_active' => false]);
    DB::table('pages')->where('id', $page->id)->update(['is_active' => 2, 'visits' => null]);
    Parish::factory()->create(['name' => 'Paróquia incluída', 'is_active' => true, 'visits' => 200]);
    ClergyMember::factory()->create(['name' => 'Clero fora do grupo', 'visits' => 7000]);
    GalleryPhoto::factory()->create(['photo_path' => null]);
    MediaAsset::create(['path' => 'editor/archive.pdf', 'bytes' => 100, 'sha256' => str_repeat('a', 64), 'state' => 'available']);
    $report = app(ContentDashboard::class)->report();
    $modules = collect($report['modules'])->keyBy('source');
    expect($modules->keys()->all())->toBe(['noticia', 'artigo', 'evento', 'pagina', 'paroquia', 'slide'])
        ->and($modules->has('galeria_foto'))->toBeFalse()->and($modules->has('usuario'))->toBeFalse()->and($modules->has('clero'))->toBeFalse();
    expect(BannerResource::shouldRegisterNavigation())->toBeFalse()->and(MagazineResource::shouldRegisterNavigation())->toBeFalse();
    expect($modules['paroquia'])->toMatchArray(['total' => 1, 'active' => 1, 'inactive' => 0, 'visits' => 200]);
    expect($modules['artigo'])->toMatchArray(['total' => 2, 'active' => 1, 'inactive' => 1, 'visits' => 1250]);
    expect($modules['noticia'])->toMatchArray(['total' => 3, 'active' => 2, 'inactive' => 1, 'visits' => 125, 'scheduled' => 1]);
    expect($modules['pagina'])->toMatchArray(['total' => 1, 'active' => 0, 'inactive' => 0, 'other_status' => 1, 'visits' => 0]);
    expect($report['totals'])->toMatchArray(['total' => 7, 'active' => 4, 'inactive' => 2, 'other_status' => 1, 'visits' => 1575]);
    expect($active->fresh()->visits)->toBe(1200)->and(Article::withTrashed()->find($deleted->id)->visits)->toBe(9000);
});

it('renders useful module cards and removes the welcome and media inventory widgets', function (): void {
    Article::factory()->create(['title' => 'Texto', 'is_active' => true, 'visits' => 1234]);
    $component = Livewire::test(SiteOverview::class)->assertSee('Acesso rápido')->assertSee('Cadastrar')->assertSee('Listagem')->assertSee('1.234')
        ->assertDontSee('Fotos nas galerias')->assertDontSee('Arquivos de mídia')->assertDontSee('Acervo de mídia')->assertDontSee('Bem-vindo')->assertDontSee('Visão geral dos conteúdos')->assertDontSee('Conteúdos cadastrados')->assertDontSee('Banners')->assertDontSee('Revistas');
    expect(substr_count($component->html(), 'data-content-module='))->toBe(6);
    $component->assertSee(ArticleResource::getUrl('index'), false)->assertSee(ArticleResource::getUrl('create'), false);
    expect(Filament::getPanel('admin')->getWidgets())->not->toContain(AccountWidget::class);
    $this->get('/admin')->assertOk()->assertDontSee('Bem-vindo')->assertSee('Acesso rápido');
});

it('uses real registered create pages or native create-modal links for every shortcut', function (): void {
    $report = app(ContentDashboard::class)->report();
    foreach ($report['modules'] as $module) {
        $resource = collect(Filament::getPanel('admin')->getResources())->first(fn (string $class): bool => is_subclass_of($class, ContentResource::class) && $class::$sourceTable === $module['source']);
        $this->get($module['index_url'])->assertOk();
        $this->get($module['create_url'])->assertOk();
        if ($resource::hasPage('create')) {
            expect($module['create_url'])->toBe($resource::getUrl('create'));
        } else {
            expect($module['create_url'])->toBe($resource::getUrl('index', ['action' => 'create']));
            Livewire::withQueryParams(['action' => 'create'])->test($resource::getPages()['index']->getPage())
                ->call('mountDefaultActions')->assertSet('mountedActions.0.name', 'create')->assertSet('mountedActions.0.context.mountedFromUrl', true);
        }
    }
});

it('queries each module once per report and never queries the media inventory', function (): void {
    DB::enableQueryLog();
    DB::flushQueryLog();
    $service = app(ContentDashboard::class);
    $report = $service->report();
    $queries = DB::getQueryLog();
    expect($queries)->toHaveCount(count($report['modules']));
    foreach ($queries as $query) {
        expect(strtolower($query['query']))->toContain('count(*)')->not->toContain('media_assets')->not->toContain('gallery_photos');
    }
    DB::flushQueryLog();
    $service->report();
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
});

it('refreshes statistics on demand without polling or changing content', function (): void {
    $component = Livewire::test(SiteOverview::class);
    Article::factory()->create(['title' => 'Artigo recente', 'is_active' => true, 'visits' => 2468]);
    $component->call('refreshStatistics')->assertSee('2.468')->assertDontSee('wire:poll', false);
    expect(app(ContentDashboard::class)->report()['totals']['total'])->toBe(1);
});

it('blocks statistics and shortcuts for inactive or non-administrator accounts', function (bool $active, int $level): void {
    $this->actingAs(User::factory()->create(['is_active' => $active, 'legacy_level' => $level]));
    Livewire::test(SiteOverview::class)->assertForbidden();
    $this->get('/admin')->assertForbidden();
})->with([[true, 1], [false, 3]]);

it('orders the visible groups of the admin sidebar', function (): void {
    $groups = collect(Filament::getNavigation())->map(fn ($group) => $group->getLabel())->filter()->values()->all();

    expect($groups)->toBe(['Conteúdo', 'Institucional', 'Mídias', 'Administração']);
});
