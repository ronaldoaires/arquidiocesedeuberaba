<?php

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Parishes\Pages\CreateParish;
use App\Filament\Support\ContentResource;
use App\Models\Article;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use mdobes\RichEditorFullscreen\FullscreenRichContentPlugin;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    Storage::fake('local');
});

it('adds the fullscreen tool to article create and edit without removing document tools', function (string $operation): void {
    $page = $operation === 'create' ? CreateArticle::class : EditArticle::class;
    $parameters = $operation === 'create' ? [] : ['record' => Article::factory()->create(['title' => 'Artigo de tela cheia'])->id];
    $component = Livewire::test($page, $parameters)->assertSee('Tela cheia');
    $editor = $component->instance()->getSchema('form')->getComponentByStatePath('body_html');
    $buttons = Arr::flatten($editor->getToolbarButtons());
    expect(count(array_keys($buttons, 'fullscreen', true)))->toBe(1)
        ->and($buttons)->toContain('insertDocument', 'attachFiles')
        ->and(collect($editor->getPlugins())->contains(fn ($plugin): bool => $plugin instanceof FullscreenRichContentPlugin))->toBeTrue()
        ->and($editor->getTools()['fullscreen']->getLabel())->toBe('Tela cheia');
    expect($component->html())->toContain('Entrar em tela cheia', 'Sair da tela cheia', 'fullscreen.js');
})->with(['create', 'edit']);

it('keeps the existing simple editor tools and adds the fullscreen control only once', function (): void {
    $component = Livewire::test(CreateParish::class);
    foreach (['service_hours', 'office_hours'] as $name) {
        $editor = $component->instance()->getSchema('form')->getComponentByStatePath($name);
        $buttons = Arr::flatten($editor->getToolbarButtons());
        expect($buttons)->toContain('bold', 'italic', 'underline', 'bulletList', 'orderedList', 'textColor', 'highlight', 'link', 'fullscreen')
            ->and(count(array_keys($buttons, 'fullscreen', true)))->toBe(1);
    }
});

it('publishes the registered local assets and loads them on the admin page', function (): void {
    $assets = [
        FilamentAsset::getScriptSrc('rich-content-plugins/fullscreen') => base_path('vendor/mdobes/rich-editor-fullscreen/resources/dist/rich-content-plugins/fullscreen.js'),
        FilamentAsset::getStyleHref('rich-editor-fullscreen-styles') => base_path('vendor/mdobes/rich-editor-fullscreen/resources/dist/rich-editor-fullscreen.css'),
    ];
    foreach ($assets as $url => $source) {
        $path = public_path(ltrim(parse_url($url, PHP_URL_PATH), '/'));
        expect(is_file($path))->toBeTrue()->and(hash_file('sha256', $path))->toBe(hash_file('sha256', $source));
    }
    $this->get(ArticleResource::getUrl('create'))->assertOk()->assertSee('rich-editor-fullscreen-styles.css', false)->assertSee('fullscreen.js', false);
});

it('preserves existing HTML and the public URL when saving with fullscreen enabled', function (): void {
    $original = '<p style="text-align:center"><strong>Texto preservado</strong></p><p><a href="/uploads/summernote/file.pdf">Documento</a></p>';
    $record = Article::factory()->create(['title' => 'Artigo existente', 'body_html' => $original, 'slug' => 'url-existente']);
    Livewire::test(EditArticle::class, ['record' => $record->id])->call('save')->assertHasNoFormErrors()->assertRedirect(ArticleResource::getUrl('index'));
    expect($record->fresh()->body_html)->toBe($original)->and($record->fresh()->slug)->toBe('url-existente');
});

it('exposes the fullscreen option in every current administrative rich editor', function (string $source): void {
    $resource = collect(Filament::getPanel('admin')->getResources())->first(fn (string $class): bool => is_subclass_of($class, ContentResource::class) && $class::$sourceTable === $source);
    $component = $resource::hasPage('create') ? Livewire::test($resource::getPages()['create']->getPage()) : Livewire::test($resource::getPages()['index']->getPage())->mountAction('create');
    $schema = $component->instance()->getSchema($resource::hasPage('create') ? 'form' : 'mountedActionSchema0');
    $editors = collect($schema->getFlatFields())->filter(fn ($field): bool => $field instanceof RichEditor);
    expect($editors)->not->toBeEmpty();
    foreach ($editors as $editor) {
        $buttons = Arr::flatten($editor->getToolbarButtons());
        expect(count(array_keys($buttons, 'fullscreen', true)))->toBe(1)
            ->and($editor->getTools()['fullscreen']->getLabel())->toBe('Tela cheia')
            ->and(collect($editor->getPlugins())->contains(fn ($plugin): bool => $plugin instanceof FullscreenRichContentPlugin))->toBeTrue();
    }
})->with(array_keys(array_filter(require __DIR__.'/../../config/admin.php', fn (array $fields): bool => array_intersect(['body_html', 'service_hours', 'office_hours'], $fields) !== [])));
