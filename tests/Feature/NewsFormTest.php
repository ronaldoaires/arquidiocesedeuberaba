<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Filament\Resources\NewsItems\NewsItemResource;
use App\Filament\Resources\NewsItems\Pages\CreateNewsItem;
use App\Filament\Resources\NewsItems\Pages\EditNewsItem;
use App\Filament\Resources\NewsItems\Pages\ManageNewsItems;
use App\Models\Article;
use App\Models\NewsCategory;
use App\Models\NewsItem;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    Storage::fake('local');
});

it('lists article views as read-only sortable counts without changing the stored values', function (): void {
    $popular = Article::factory()->create(['title' => 'Artigo mais lido', 'visits' => 1234]);
    $new = Article::factory()->create(['title' => 'Artigo novo', 'visits' => 0]);
    Livewire::test(ManageArticles::class)->assertTableColumnExists('visits')->assertTableColumnStateSet('visits', 1234, $popular)->assertTableColumnStateSet('visits', 0, $new)
        ->assertSee('1.234')->sortTable('visits', 'desc')->assertCanSeeTableRecords([$popular, $new], inOrder: true);
    Livewire::test(EditArticle::class, ['record' => $popular->id])->assertFormFieldDoesNotExist('visits')->fillForm(['title' => 'Título corrigido'])->call('save')->assertHasNoFormErrors();
    expect($popular->fresh()->visits)->toBe(1234)->and($new->fresh()->visits)->toBe(0);
});

it('lists news categories and views and can search by category', function (): void {
    $category = NewsCategory::factory()->create(['title' => 'Categoria de destaque']);
    $otherCategory = NewsCategory::factory()->create(['title' => 'Outra categoria']);
    $news = NewsItem::factory()->create(['title' => 'Notícia principal', 'category_id' => $category->id, 'visits' => 2345]);
    $other = NewsItem::factory()->create(['title' => 'Outra notícia', 'category_id' => $otherCategory->id, 'visits' => 1]);
    $component = Livewire::test(ManageNewsItems::class)->assertTableColumnExists('category.title')->assertTableColumnExists('visits')
        ->assertTableColumnStateSet('category.title', 'Categoria de destaque', $news)->assertTableColumnStateSet('visits', 2345, $news)->assertSee('2.345')
        ->sortTable('visits', 'desc')->assertCanSeeTableRecords([$news, $other], inOrder: true);
    foreach ($component->instance()->getTableRecords() as $record) {
        expect($record->relationLoaded('category'))->toBeTrue();
    }
    $component->searchTable('Categoria de destaque')->assertCanSeeTableRecords([$news])->assertCanNotSeeTableRecords([$other]);
    expect($news->fresh()->visits)->toBe(2345);
});

it('handles uncategorized news and null historical counters without rewriting them', function (): void {
    $news = NewsItem::factory()->create(['title' => 'Notícia sem categoria', 'category_id' => null]);
    $news->update(['visits' => null]);
    Livewire::test(ManageNewsItems::class)->assertTableColumnStateSet('visits', 0, $news)->assertSee('Sem categoria')->assertCanSeeTableRecords([$news]);
    expect($news->fresh()->visits)->toBeNull()->and($news->fresh()->category_id)->toBeNull();
});

it('opens news create and edit in normal resource pages', function (): void {
    $record = NewsItem::factory()->create(['title' => 'Notícia existente']);
    $component = Livewire::test(ManageNewsItems::class);
    expect($component->instance()->getAction('create')->getUrl())->toBe(NewsItemResource::getUrl('create'))
        ->and($component->instance()->getTable()->getAction('edit')->record($record)->getUrl())->toBe(NewsItemResource::getUrl('edit', ['record' => $record]));
    $this->get(NewsItemResource::getUrl('create'))->assertOk()->assertSeeLivewire(CreateNewsItem::class);
    $this->get(NewsItemResource::getUrl('edit', ['record' => $record]))->assertOk()->assertSeeLivewire(EditNewsItem::class);
});

it('keeps only the legacy fields with activity last inside the complete form card', function (string $operation): void {
    $page = $operation === 'create' ? CreateNewsItem::class : EditNewsItem::class;
    $parameters = $operation === 'create' ? [] : ['record' => NewsItem::factory()->create(['title' => 'Notícia'])->id];
    $component = Livewire::test($page, $parameters)->assertFormFieldDoesNotExist('slug')->assertFormFieldDoesNotExist('created_by')->assertFormFieldDoesNotExist('visits');
    $schema = $component->instance()->getSchema('form');
    $fields = collect($schema->getComponents())->filter(fn ($field): bool => $field instanceof Field)->map(fn ($field): string => $field->getName())->values()->all();
    expect($fields)->toBe(['cover_path', 'title', 'body_html', 'category_id', 'created_at', 'is_active'])
        ->and($schema->getComponentByStatePath('created_at')->getLabel())->toBe('Data de Cadastro')
        ->and($schema->getComponentByStatePath('body_html')->getLabel())->toBe('Texto')
        ->and($component->instance()->getFormContentComponent())->toBeInstanceOf(Section::class);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
    expect((new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-section ")]//form[@id="form"]//button[@type="submit"]')->length)->toBeGreaterThan(0);
})->with(['create', 'edit']);

it('validates news and saves category content and authorship while retaining counters and URLs on edit', function (): void {
    Livewire::test(CreateNewsItem::class)->fillForm(['title' => ''])->call('create')->assertHasFormErrors(['title' => 'required']);
    $category = NewsCategory::factory()->create(['title' => 'Categoria selecionada']);
    Livewire::test(CreateNewsItem::class)->fillForm([
        'title' => 'Notícia de teste', 'category_id' => $category->id,
        'body_html' => '<p>Texto com <strong>formatação</strong></p>',
        'created_at' => '2026-11-10 10:30:00', 'is_active' => true,
    ])->call('create')->assertHasNoFormErrors()->assertRedirect(NewsItemResource::getUrl('index'));
    $record = NewsItem::sole();
    expect($record->category_id)->toBe($category->id)->and($record->visits)->toBe(0)
        ->and($record->created_by)->toBe(auth()->id())->and($record->body_html)->toContain('<strong>', 'formatação')
        ->and($record->created_at->timezone('America/Sao_Paulo')->format('Y-m-d H:i'))->toBe('2026-11-10 10:30');
    $record->update(['visits' => 876]);
    $html = $record->body_html;
    $url = $record->public_url;
    Livewire::test(EditNewsItem::class, ['record' => $record->id])->fillForm(['title' => 'Título da notícia corrigido'])->call('save')
        ->assertHasNoFormErrors()->assertRedirect(NewsItemResource::getUrl('index'));
    expect($record->fresh()->visits)->toBe(876)->and($record->fresh()->body_html)->toBe($html)->and($record->fresh()->public_url)->toBe($url);
});
