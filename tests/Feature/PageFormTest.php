<?php

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Filament\Support\PreservingRichEditor;
use App\Models\Page;
use App\Models\PageCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    Storage::fake('local');
});

it('opens page create and edit through normal page links', function (): void {
    $record = Page::factory()->create();
    $list = Livewire::test(ManagePages::class);
    expect($list->instance()->getAction('create')->getUrl())->toBe(PageResource::getUrl('create'))
        ->and($list->instance()->getTable()->getAction('edit')->record($record)->getUrl())->toBe(PageResource::getUrl('edit', ['record' => $record]));
    $this->get(PageResource::getUrl('create'))->assertOk()->assertSeeLivewire(CreatePage::class);
    $this->get(PageResource::getUrl('edit', ['record' => $record]))->assertOk()->assertSeeLivewire(EditPage::class);
});

it('shows searchable menu and eagerly loaded category names in the page listing', function (): void {
    $category = PageCategory::factory()->create(['title' => 'Categoria de teste']);
    $record = Page::factory()->create(['title' => 'Primeira página', 'menu_group' => 'clero', 'category_id' => $category->id]);
    $other = Page::factory()->create(['title' => 'Outra página', 'menu_group' => 'pastoral', 'category_id' => null]);
    $component = Livewire::test(ManagePages::class)->assertTableColumnStateSet('menu_group', 'clero', $record)
        ->assertTableColumnStateSet('category.title', 'Categoria de teste', $record)->assertSee('Clero')->assertSee('Pastoral Arquidiocesana')->assertSee('Sem categoria')->assertDontSee('Sem menu');
    $table = $component->instance()->getTable();
    expect($table->getColumn('menu_group')->getPlaceholder())->toBeNull();
    expect($table->getColumn('menu_group')->isSortable())->toBeTrue()->and($table->getColumn('category.title')->isSortable())->toBeTrue();
    foreach ($component->instance()->getTableRecords() as $row) {
        expect($row->relationLoaded('category'))->toBeTrue();
    }
    $component->searchTable('Categoria de teste')->assertCanSeeTableRecords([$record])->assertCanNotSeeTableRecords([$other])
        ->searchTable('pastoral')->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$record]);
});

it('groups menu sub page and a narrow integer position while retaining the exact page field contract', function (string $operation): void {
    $component = Livewire::test($operation === 'create' ? CreatePage::class : EditPage::class, $operation === 'create' ? [] : ['record' => Page::factory()->create()->id])
        ->assertFormFieldDoesNotExist('slug')->assertFormFieldDoesNotExist('created_by')->assertFormFieldDoesNotExist('visits');
    $schema = $component->instance()->getSchema('form');
    expect(array_keys($schema->getFlatFields()))->toHaveCount(7);
    expect(array_map(fn ($field): string => $field->getName(), array_values($schema->getFlatFields())))
        ->toBe(['cover_path', 'title', 'body_html', 'menu_group', 'category_id', 'sort_order', 'is_active']);
    $grid = collect($schema->getComponents())->first(fn ($field): bool => $field instanceof Grid);
    expect($grid)->not->toBeNull()->and($grid->getColumns('default'))->toBe(1)->and($grid->getColumns('md'))->toBe(7)
        ->and($schema->getComponentByStatePath('menu_group')->getColumnSpan('md'))->toBe(3)
        ->and($schema->getComponentByStatePath('category_id')->getColumnSpan('md'))->toBe(3)
        ->and($schema->getComponentByStatePath('category_id')->getLabel())->toBe('Sub Página')
        ->and($schema->getComponentByStatePath('sort_order')->getColumnSpan('md'))->toBe(1)
        ->and($schema->getComponentByStatePath('body_html'))->toBeInstanceOf(PreservingRichEditor::class)
        ->and($component->instance()->getFormContentComponent())->toBeInstanceOf(Section::class);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
    expect((new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-section ")]//form[@id="form"]//button[@type="submit"]')->length)->toBeGreaterThan(0);
})->with(['create', 'edit']);

it('validates and creates pages with menu category position and an automatic URL', function (): void {
    Livewire::test(CreatePage::class)->fillForm(['title' => ''])->call('create')->assertHasFormErrors(['title' => 'required']);
    Livewire::test(CreatePage::class)->fillForm(['title' => 'Posição inválida', 'sort_order' => 1.5])->call('create')->assertHasFormErrors(['sort_order' => 'integer']);
    expect(Page::count())->toBe(0);
    $category = PageCategory::factory()->create();
    Livewire::test(CreatePage::class)->fillForm(['title' => 'Nova página', 'body_html' => '<p>Texto</p>', 'menu_group' => 'clero', 'category_id' => $category->id, 'sort_order' => 3, 'is_active' => true])
        ->call('create')->assertHasNoFormErrors()->assertRedirect(PageResource::getUrl('index'));
    $record = Page::sole();
    expect($record->title)->toBe('Nova página')->and($record->slug)->toBe('nova-pagina')->and($record->created_by)->toBe(auth()->id())
        ->and($record->menu_group)->toBe('clero')->and($record->category_id)->toBe($category->id)->and($record->sort_order)->toBe(3);
});

it('preserves existing HTML public URLs counters and unedited page values after editing', function (): void {
    $html = '<p style="text-align:center">Texto <strong>preservado</strong></p><p><a href="/uploads/summernote/document.pdf">Documento</a></p>';
    $record = Page::factory()->create(['title' => 'Página antiga', 'slug' => 'endereco-historico', 'body_html' => $html, 'visits' => 42, 'menu_group' => 'arquidiocese', 'sort_order' => 5]);
    $url = $record->public_url;
    Livewire::test(EditPage::class, ['record' => $record->id])->fillForm(['title' => 'Página corrigida'])->call('save')->assertHasNoFormErrors()->assertRedirect(PageResource::getUrl('index'));
    expect($record->fresh()->title)->toBe('Página corrigida')->and($record->fresh()->body_html)->toBe($html)->and($record->fresh()->public_url)->toBe($url)
        ->and($record->fresh()->visits)->toBe(42)->and($record->fresh()->menu_group)->toBe('arquidiocese')->and($record->fresh()->sort_order)->toBe(5);
});

it('cleans a discarded editor upload after creating through the full page', function (): void {
    $component = Livewire::test(CreatePage::class);
    $editor = $component->instance()->getSchema('form')->getComponentByStatePath('body_html');
    Storage::disk('local')->put('livewire-tmp/discarded.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true));
    $path = $editor->saveUploadedFileAttachment(new TemporaryUploadedFile('discarded.png', 'local'));
    Storage::disk('public')->assertExists($path);
    $component->fillForm(['title' => 'Página sem a imagem', 'body_html' => '<p>Imagem descartada</p>'])->call('create')->assertHasNoFormErrors()->assertRedirect(PageResource::getUrl('index'));
    Storage::disk('public')->assertMissing($path);
});
