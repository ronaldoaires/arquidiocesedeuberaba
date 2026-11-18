<?php

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Filament\Support\PreservingRichEditor;
use App\Models\Article;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Section;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    Storage::fake('local');
});

it('opens article create and edit through normal page links', function (): void {
    $record = Article::factory()->create(['title' => 'Artigo existente']);
    $list = Livewire::test(ManageArticles::class);
    expect($list->instance()->getAction('create')->getUrl())->toBe(ArticleResource::getUrl('create'))
        ->and($list->instance()->getTable()->getAction('edit')->record($record)->getUrl())->toBe(ArticleResource::getUrl('edit', ['record' => $record]));
    $this->get(ArticleResource::getUrl('create'))->assertOk()->assertSeeLivewire(CreateArticle::class);
    $this->get(ArticleResource::getUrl('edit', ['record' => $record]))->assertOk()->assertSeeLivewire(EditArticle::class);
});

it('keeps only the legacy fields with activity last and the full form inside a card', function (string $operation): void {
    $page = $operation === 'create' ? CreateArticle::class : EditArticle::class;
    $parameters = $operation === 'create' ? [] : ['record' => Article::factory()->create(['title' => 'Artigo'])->id];
    $component = Livewire::test($page, $parameters)->assertFormFieldDoesNotExist('slug')->assertFormFieldDoesNotExist('created_by');
    $schema = $component->instance()->getSchema('form');
    $fields = collect($schema->getComponents())->filter(fn ($field): bool => $field instanceof Field)->map(fn ($field): string => $field->getName())->values()->all();
    expect($fields)->toBe(['cover_path', 'title', 'created_at', 'body_html', 'is_active'])
        ->and($schema->getComponentByStatePath('created_at')->getLabel())->toBe('Data de Cadastro')
        ->and($schema->getComponentByStatePath('body_html'))->toBeInstanceOf(PreservingRichEditor::class)
        ->and($component->instance()->getFormContentComponent())->toBeInstanceOf(Section::class);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
    expect((new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-section ")]//form[@id="form"]//button[@type="submit"]')->length)->toBeGreaterThan(0);
})->with(['create', 'edit']);

it('validates the title and creates an article with an automatic URL and returns to the index', function (): void {
    Livewire::test(CreateArticle::class)->fillForm(['title' => ''])->call('create')->assertHasFormErrors(['title' => 'required']);
    expect(Article::count())->toBe(0);
    Livewire::test(CreateArticle::class)->fillForm([
        'title' => 'Artigo de revisão',
        'body_html' => '<p>Texto com <strong>formatação</strong></p>',
        'created_at' => '2026-11-10 12:30:00',
        'is_active' => true,
    ])->call('create')->assertHasNoFormErrors()->assertRedirect(ArticleResource::getUrl('index'));
    $record = Article::sole();
    expect($record->slug)->toBe('artigo-de-revisao')->and($record->created_by)->toBe(auth()->id())
        ->and($record->body_html)->toContain('<strong>', 'formatação')
        ->and($record->created_at->timezone('America/Sao_Paulo')->format('Y-m-d H:i'))->toBe('2026-11-10 12:30');
});

it('preserves legacy HTML and public URLs on the full edit page', function (): void {
    $html = '<p style="text-align:center">Texto antigo <strong>preservado</strong></p><p><a href="/uploads/summernote/document.pdf">Documento</a></p>';
    $record = Article::factory()->create(['title' => 'Artigo antigo', 'slug' => 'endereco-historico', 'body_html' => $html]);
    $url = $record->public_url;
    Livewire::test(EditArticle::class, ['record' => $record->id])->fillForm(['title' => 'Artigo corrigido'])->call('save')->assertHasNoFormErrors()->assertRedirect(ArticleResource::getUrl('index'));
    expect($record->fresh()->title)->toBe('Artigo corrigido')->and($record->fresh()->body_html)->toBe($html)->and($record->fresh()->public_url)->toBe($url);
});

it('cleans a discarded editor upload after creating through the full page', function (): void {
    $component = Livewire::test(CreateArticle::class);
    $editor = $component->instance()->getSchema('form')->getComponentByStatePath('body_html');
    Storage::disk('local')->put('livewire-tmp/discarded.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true));
    $path = $editor->saveUploadedFileAttachment(new TemporaryUploadedFile('discarded.png', 'local'));
    Storage::disk('public')->assertExists($path);
    $component->fillForm(['title' => 'Artigo sem a imagem', 'body_html' => '<p>Imagem descartada</p>'])->call('create')->assertHasNoFormErrors()->assertRedirect(ArticleResource::getUrl('index'));
    Storage::disk('public')->assertMissing($path);
});
