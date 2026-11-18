<?php

use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Filament\Resources\GalleryPhotos\Pages\ManageGalleryPhotos;
use App\Filament\Resources\Parishes\Pages\EditParish;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\ContentResource;
use App\Models\Article;
use App\Models\City;
use App\Models\ClergyMember;
use App\Models\Deanery;
use App\Models\Gallery;
use App\Models\GalleryPhoto;
use App\Models\Parish;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    Storage::fake('local');
});

it('matches the audited legacy form contract without technical extra fields', function (string $source): void {
    $contracts = json_decode(file_get_contents(base_path('docs/migration/evidence/admin-form-contract.json')), true, flags: JSON_THROW_ON_ERROR);
    $resource = collect(Filament::getPanel('admin')->getResources())->first(fn (string $class): bool => is_subclass_of($class, ContentResource::class) && $class::$sourceTable === $source);
    $component = $resource::hasPage('create') ? Livewire::test($resource::getPages()['create']->getPage()) : Livewire::test($resource::getPages()['index']->getPage())->mountAction('create');
    $fields = collect($component->instance()->getSchema($resource::hasPage('create') ? 'form' : 'mountedActionSchema0')->getFlatFields())->filter(fn ($component): bool => $component instanceof Field)->map(fn ($component): string => $component->getName())->values()->all();
    $expected = $contracts[$source]['fields'];
    if ($source === 'paroquia') {
        $expected[] = 'assignments';
    }
    if (in_array($source, ['paroquia', 'artigo', 'noticia'], true)) {
        $expected = array_values(array_diff($expected, ['is_active']));
        $expected[] = 'is_active';
    }
    if (in_array($source, ['clero', 'pagina'], true)) {
        expect($fields)->toEqualCanonicalizing($expected);
    } else {
        expect($fields)->toBe($expected);
    }
    $component = Livewire::test($resource::getPages()['index']->getPage());
    $component->assertTableColumnDoesNotExist('id');
    expect($component->instance()->getTable()->getFilter('trashed'))->toBeNull();
    foreach (['edit', 'delete'] as $name) {
        $action = $component->instance()->getTable()->getAction($name);
        if ($action) {
            expect($action->isIconButton())->toBeTrue()->and($action->getSize())->toBe(Size::ExtraLarge);
        }
    }
})->with(array_keys(json_decode(file_get_contents(__DIR__.'/../../docs/migration/evidence/admin-form-contract.json'), true)));

it('toggles records on and off through the actual editable column', function (): void {
    $record = Article::factory()->create(['is_active' => true]);
    $component = Livewire::test(ManageArticles::class)->assertTableColumnExists('is_active', fn ($column): bool => $column instanceof ToggleColumn);
    expect($component->instance()->getTable()->getColumn('is_active')->record($record)->getTooltip(true))->toBe('Desativar');
    $component->call('updateTableColumnState', 'is_active', (string) $record->id, false)->assertHasNoErrors()->assertNotified('Status desativado');
    expect($record->fresh()->is_active)->toBeFalse();
    expect($component->instance()->getTable()->getColumn('is_active')->record($record->fresh())->getTooltip(false))->toBe('Ativar');
    $component->call('updateTableColumnState', 'is_active', (string) $record->id, true)->assertHasNoErrors()->assertNotified('Status ativado');
    expect($record->fresh()->is_active)->toBeTrue();
    expect($component->instance()->getTable()->getColumn('is_active')->record($record->fresh())->getTooltip(true))->toBe('Desativar');
    expect(Notifications::$verticalAlignment)->toBe(VerticalAlignment::Start);
});

it('denies inline state changes after the actor loses administrative permission', function (): void {
    $record = Article::factory()->create(['is_active' => true]);
    $component = Livewire::test(ManageArticles::class);
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 1]));
    $component->call('updateTableColumnState', 'is_active', (string) $record->id, false)->assertNotNotified();
    expect($record->fresh()->is_active)->toBeTrue();
});

it('accepts multiple gallery photos through one create action', function (): void {
    $gallery = Gallery::factory()->create();
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
    Livewire::test(ManageGalleryPhotos::class)->callAction('create', data: ['gallery_id' => $gallery->id, 'author_name' => 'Autor', 'is_active' => true, 'photo_path' => [UploadedFile::fake()->createWithContent('one.png', $bytes), UploadedFile::fake()->createWithContent('two.png', $bytes)]])->assertHasNoFormErrors();
    expect(GalleryPhoto::where('gallery_id', $gallery->id)->count())->toBe(2);
    foreach (GalleryPhoto::all() as $photo) {
        Storage::disk('public')->assertExists($photo->photo_path);
    }
});

it('edits parish clergy assignments inside the original parish form', function (): void {
    $parish = Parish::factory()->create(['name' => 'Paróquia de teste', 'city_id' => City::factory()->create(['name' => 'Uberaba'])->id, 'deanery_id' => Deanery::factory()->create(['name' => 'Forania de teste'])->id]);
    $clergy = ClergyMember::factory()->create(['name' => 'Padre de teste']);
    Livewire::test(EditParish::class, ['record' => $parish->id])->fillForm(['assignments' => [$clergy->id => ['clergy_member_id' => $clergy->id, 'selected' => true, 'role_text' => 'Pároco']]])->call('save')->assertHasNoFormErrors();
    expect($parish->assignments()->first()?->clergy_member_id)->toBe($clergy->id)->and($parish->assignments()->first()?->role_text)->toBe('Pároco');
});

it('toggles user activity without changing the access level', function (): void {
    $record = User::factory()->create(['is_active' => true, 'legacy_level' => 1]);
    $component = Livewire::test(ManageUsers::class);
    $component->call('updateTableColumnState', 'is_active', (string) $record->id, false)->assertHasNoErrors();
    expect($record->fresh()->is_active)->toBeFalse()->and($record->fresh()->legacy_level)->toBe(1);
    $component->call('updateTableColumnState', 'is_active', (string) $record->id, true)->assertHasNoErrors()->assertNotified('Status ativado');
    expect($record->fresh()->is_active)->toBeTrue();
    expect($component->instance()->getTable()->getColumn('is_active')->record($record->fresh())->getTooltip(true))->toBe('Desativar');
    expect(Notifications::$verticalAlignment)->toBe(VerticalAlignment::Start);
});

it('keeps deleted records out of the normal listing after removing the deleted-record filter', function (): void {
    $visible = Article::factory()->create(['title' => 'Artigo disponível', 'is_active' => true]);
    $deleted = Article::factory()->create(['title' => 'Artigo excluído', 'is_active' => true]);
    $deleted->delete();
    $component = Livewire::test(ManageArticles::class)->assertCanSeeTableRecords([$visible])->assertCanNotSeeTableRecords([$deleted]);
    expect($component->instance()->getTable()->getFilter('trashed'))->toBeNull()
        ->and(Article::withTrashed()->find($deleted->id)?->trashed())->toBeTrue();
});
