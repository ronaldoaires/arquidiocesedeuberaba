<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Filament\Resources\MediaAssets\Pages\ManageMediaAssets;
use App\Filament\Support\ContentResource;
use App\Models\Article;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\ContentRegistry;
use App\Support\SlugGenerator;
use Filament\Facades\Filament;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
});

it('generates unique slugs without showing a slug input or changing existing public URLs', function (): void {
    Article::factory()->create(['title' => 'São José e a missão', 'slug' => 'sao-jose-e-a-missao'])->delete();
    $page = Livewire::test(CreateArticle::class)->assertFormFieldDoesNotExist('slug')
        ->fillForm(['title' => 'São José e a missão', 'is_active' => true])->call('create')->assertHasNoFormErrors();
    $record = Article::firstOrFail();
    expect($record->slug)->toBe('sao-jose-e-a-missao-1');
    $url = $record->public_url;
    Livewire::test(EditArticle::class, ['record' => $record->id])->assertFormFieldDoesNotExist('slug')
        ->fillForm(['title' => 'Título corrigido'])->call('save')->assertHasNoFormErrors();
    expect($record->fresh()->slug)->toBe('sao-jose-e-a-missao-1')->and($record->fresh()->public_url)->toBe($url);
});

it('bounds generated slugs and provides a valid fallback for punctuation-only titles', function (): void {
    $long = Article::factory()->create(['title' => 'Long title', 'slug' => str_repeat('a', 255)]);
    $slug = SlugGenerator::unique(Article::class, str_repeat('a', 300));
    expect(strlen($slug))->toBe(255)->and($slug)->toEndWith('-1')
        ->and(SlugGenerator::unique(Article::class, '!!!'))->toBe('record')
        ->and(SlugGenerator::unique(Article::class, str_repeat('a', 255), $long))->toBe($long->slug);
});

it('renders a real thumbnail column on every image-bearing resource', function (string $source, string $field): void {
    $module = ContentRegistry::get($source);
    $model = ContentRegistry::model($source);
    $path = 'legacy/uploads/imagens/'.$module['table'].'.png';
    $thumbnail = dirname($path).'/thumbs/'.basename($path);
    Storage::disk('public')->put($path, 'image');
    Storage::disk('public')->put($thumbnail, 'thumbnail');
    $data = [$field => $path];
    if ($module['title'] !== 'id') {
        $data[$module['title']] = 'Registro com foto';
    }
    $record = $model::factory()->create($data);
    $resource = collect(Filament::getPanel('admin')->getResources())->first(fn (string $class): bool => is_subclass_of($class, ContentResource::class) && $class::$sourceTable === $source);
    $component = Livewire::test($resource::getPages()['index']->getPage())->assertCanSeeTableRecords([$record]);
    if ($source === 'instituto_categoria') {
        $component->assertTableColumnDoesNotExist($field);

        return;
    }
    $component->assertTableColumnExists($field, fn ($column): bool => $column instanceof ImageColumn);
    $column = $component->instance()->getTable()->getColumn($field)->record($record);
    expect($column->getState())->toBe($thumbnail)->and($column->getImageUrl($column->getState(), $record))->toBe('/storage/'.$thumbnail);
    $component->assertSeeHtml('/storage/'.$thumbnail);
})->with(function (): array {
    $cases = [];
    foreach (require __DIR__.'/../../config/content.php' as $source => $module) {
        foreach ($module['columns'] as $column) {
            if (in_array($column['name'], ['cover_path', 'photo_path'], true)) {
                $cases[$source.'.'.$column['name']] = [$source, $column['name']];
            }
        }
    }

    return $cases;
});

it('uses the original image when no thumbnail exists and never treats a document as a photo', function (): void {
    Storage::disk('public')->put('content/cover.png', 'image');
    $article = Article::factory()->create(['cover_path' => 'content/cover.png']);
    $component = Livewire::test(ManageArticles::class);
    $column = $component->instance()->getTable()->getColumn('cover_path')->record($article);
    expect($column->getImageUrl($column->getState(), $article))->toBe('/storage/content/cover.png');
    $asset = MediaAsset::create(['path' => 'editor/document.pdf', 'bytes' => 10, 'sha256' => str_repeat('0', 64), 'state' => 'available']);
    $library = Livewire::test(ManageMediaAssets::class)->assertTableColumnExists('preview', fn ($column): bool => $column instanceof ImageColumn);
    $preview = $library->instance()->getTable()->getColumn('preview')->record($asset);
    expect($preview->getState())->toBeNull();
});
