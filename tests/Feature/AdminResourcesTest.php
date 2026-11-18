<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\NewsItems\Pages\CreateNewsItem;
use App\Filament\Resources\NewsItems\Pages\EditNewsItem;
use App\Filament\Support\ContentResource;
use App\Models\Article;
use App\Models\NewsItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $admin = User::factory()->create(['is_active' => true, 'legacy_level' => 3]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function contentResourceFor(string $source): string
{
    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        if (is_subclass_of($resource, ContentResource::class) && $resource::$sourceTable === $source) {
            return $resource;
        }
    }
    throw new RuntimeException('Resource not registered.');
}

it('renders every migrated administrative resource', function (string $source): void {
    $resource = contentResourceFor($source);
    $this->get($resource::getUrl('index'))->assertOk();
})->with(array_keys(require __DIR__.'/../../config/content.php'));

it('creates edits and soft deletes news through the reviewed admin workflow', function (): void {
    $resource = contentResourceFor('noticia');
    $page = $resource::getPages()['index']->getPage();
    Livewire::test(CreateNewsItem::class)->fillForm(['title' => 'Título inicial', 'body_html' => '<p>Texto</p>', 'is_active' => true, 'created_at' => now()->toDateTimeString()])->call('create')->assertHasNoFormErrors();
    $record = NewsItem::firstOrFail();
    Livewire::test($page)->assertCanSeeTableRecords([$record])->assertActionEnabled(TestAction::make('edit')->table($record));
    Livewire::test(EditNewsItem::class, ['record' => $record->id])->fillForm(['title' => 'Título alterado'])->call('save')->assertHasNoErrors()->assertNotified();
    expect($record->fresh()->title)->toBe('Título alterado');
    Livewire::test($page)->callAction(TestAction::make('delete')->table($record));
    expect(NewsItem::withTrashed()->find($record->id)->trashed())->toBeTrue();
});

it('retains legacy rich content after opening and saving without edits', function (): void {
    $record = Article::factory()->create(['title' => 'Artigo com imagem', 'slug' => 'legacy-article', 'body_html' => '<p>Original</p><img src="/uploads/summernote/legacy.jpg" width="320" height="200">', 'is_active' => true, 'created_at' => now()]);
    Livewire::test(EditArticle::class, ['record' => $record->id])->call('save')->assertHasNoErrors()->assertNotified();
    $html = $record->fresh()->body_html;
    expect($html)->toContain('Original')->toContain('/uploads/summernote/legacy.jpg')->toContain('320');
});

it('preserves embeds and exact original markup on an unchanged save', function (): void {
    $original = '<p style="text-align: center">Texto original</p><iframe src="https://www.youtube-nocookie.com/embed/abcdefghijk" width="560" height="315"></iframe>';
    $record = Article::factory()->create(['title' => 'Com vídeo', 'slug' => 'with-embed', 'body_html' => $original, 'is_active' => true]);
    Livewire::test(EditArticle::class, ['record' => $record->id])->call('save')->assertHasNoErrors();
    expect($record->fresh()->body_html)->toBe($original);
    $this->get('/artigo/with-embed')->assertOk()->assertSee('www.youtube-nocookie.com/embed/abcdefghijk', false);
});
it('renders the media library contact inbox and dashboard', function (): void {
    $this->get(MediaAssetResource::getUrl('index'))->assertOk();
    $this->get(ContactSubmissionResource::getUrl('index'))->assertOk();
    $this->get('/admin')->assertOk();
});
