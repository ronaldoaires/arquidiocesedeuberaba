<?php

use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Filament\Resources\ClergyMembers\Pages\CreateClergyMember;
use App\Filament\Resources\ClergyMembers\Pages\EditClergyMember;
use App\Filament\Resources\ClergyMembers\RelationManagers\AssignmentsRelationManager;
use App\Filament\Support\ContentResource;
use App\Filament\Support\RelatedContentManager;
use App\Models\Article;
use App\Models\ClergyMember;
use App\Models\Parish;
use App\Models\User;
use App\Support\ContentRegistry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    Storage::fake('local');
});

function reviewedResource(string $source): string
{
    return collect(Filament::getPanel('admin')->getResources())->first(fn (string $class): bool => is_subclass_of($class, ContentResource::class) && $class::$sourceTable === $source);
}

it('gives every module with a views counter a matching column and range filter', function (string $source): void {
    $resource = reviewedResource($source);
    $class = ContentRegistry::model($source);
    $low = $class::factory()->create(['visits' => 3]);
    $middle = $class::factory()->create(['visits' => 10]);
    $high = $class::factory()->create(['visits' => 25]);
    $component = Livewire::test($resource::getPages()['index']->getPage())->assertTableColumnExists('visits');
    expect($component->instance()->getTable()->getFilter('visits')->getLabel())->toBe('Visualizações');
    $component->filterTable('visits', ['minimum' => 10, 'maximum' => 25])->assertCanSeeTableRecords([$middle, $high])->assertCanNotSeeTableRecords([$low]);
    $component->filterTable('visits', ['minimum' => null, 'maximum' => 3])->assertCanSeeTableRecords([$low])->assertCanNotSeeTableRecords([$middle, $high]);
})->with(array_keys(array_filter(require __DIR__.'/../../config/content.php', fn (array $module): bool => in_array('visits', array_column($module['columns'], 'name'), true))));

it('treats null views as zero without changing stored counters and safely ignores invalid filter bounds', function (): void {
    $null = Article::factory()->create(['title' => 'Contador nulo']);
    $null->update(['visits' => null]);
    $zero = Article::factory()->create(['title' => 'Sem visualizações', 'visits' => 0]);
    $many = Article::factory()->create(['title' => 'Com visualizações', 'visits' => 10]);
    Livewire::test(ManageArticles::class)->filterTable('visits', ['minimum' => 0, 'maximum' => 0])->assertCanSeeTableRecords([$null, $zero])->assertCanNotSeeTableRecords([$many])
        ->filterTable('visits', ['minimum' => 'invalid', 'maximum' => -1])->assertCanSeeTableRecords([$null, $zero, $many]);
    expect($null->fresh()->visits)->toBeNull()->and($many->fresh()->visits)->toBe(10);
});

it('validates every dedicated HTTP link field across the administrative forms', function (string $source, string $name): void {
    $resource = reviewedResource($source);
    $component = $resource::hasPage('create') ? Livewire::test($resource::getPages()['create']->getPage()) : Livewire::test($resource::getPages()['index']->getPage())->mountAction('create');
    $schema = $component->instance()->getSchema($resource::hasPage('create') ? 'form' : 'mountedActionSchema0');
    $field = $schema->getComponentByStatePath($name);
    $rules = $field->getValidationRules();
    expect($rules)->toContain('url:http,https');
    expect(Validator::make([$name => 'xxxxxx'], [$name => $rules])->fails())->toBeTrue()
        ->and(Validator::make([$name => 'javascript:alert(1)'], [$name => $rules])->fails())->toBeTrue()
        ->and(Validator::make([$name => 'https://example.org/path?x=1'], [$name => $rules])->passes())->toBeTrue();
})->with(function (): array {
    $cases = [];
    foreach (require __DIR__.'/../../config/admin.php' as $source => $fields) {
        foreach ($fields as $name) {
            if (in_array($name, ['site', 'facebook', 'instagram', 'youtube', 'x', 'link'], true) || str_ends_with($name, '_link')) {
                $cases[$source.'.'.$name] = [$source, $name];
            }
        }
    }

    return $cases;
});

it('rejects invalid clergy links on create and edit and keeps valid links unchanged', function (): void {
    foreach (['parish_1_link', 'parish_2_link', 'parish_3_link'] as $name) {
        Livewire::test(CreateClergyMember::class)->fillForm(['name' => 'Padre de validação', $name => 'xxxxx'])->call('create')->assertHasFormErrors([$name => 'url']);
    }
    expect(ClergyMember::count())->toBe(0);
    $record = ClergyMember::factory()->create(['name' => 'Padre existente', 'parish_1_link' => 'https://example.org/parish']);
    Livewire::test(EditClergyMember::class, ['record' => $record->id])->fillForm(['parish_1_link' => 'texto inválido'])->call('save')->assertHasFormErrors(['parish_1_link' => 'url']);
    expect($record->fresh()->parish_1_link)->toBe('https://example.org/parish');
});

it('shows Portuguese labels and modal titles for the real clergy parish assignment action', function (): void {
    $clergy = ClergyMember::factory()->create(['name' => 'Padre do vínculo']);
    $parish = Parish::factory()->create(['name' => 'Paróquia do vínculo']);
    $component = Livewire::test(AssignmentsRelationManager::class, ['ownerRecord' => $clergy, 'pageClass' => EditClergyMember::class]);
    $action = $component->instance()->getTable()->getAction('create');
    expect($action->getLabel())->toBe('Adicionar vínculo')->and($action->getModalHeading())->toBe('Adicionar vínculo com paróquia')
        ->and($action->getModalSubmitActionLabel())->toBe('Salvar vínculo')->and($action->getModelLabel())->toBe('Vínculo paroquial');
    $component->callAction(TestAction::make('create')->table(), data: ['parish_id' => $parish->id, 'role_text' => 'Pároco'])->assertHasNoFormErrors();
    expect($clergy->assignments()->sole()->parish_id)->toBe($parish->id);
    $component->assertTableColumnDoesNotExist('id')->assertTableColumnExists('parish.name')->assertTableColumnExists('role_text');
});

it('supplies localized model labels to every existing relationship-manager table and create action', function (): void {
    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        if (! is_subclass_of($resource, ContentResource::class) || $resource::getRelations() === []) {
            continue;
        }
        $model = $resource::getModel();
        $owner = $model::factory()->create();
        foreach ($resource::getRelations() as $manager) {
            if (! is_subclass_of($manager, RelatedContentManager::class)) {
                continue;
            }
            $component = Livewire::test($manager, ['ownerRecord' => $owner, 'pageClass' => $resource::getPages()['edit']->getPage()]);
            $label = ContentRegistry::get($manager::$relatedSource)['singular'];
            expect($component->instance()->getTable()->getModelLabel())->toBe($label)
                ->and($component->instance()->getTable()->getAction('create')->getModelLabel())->toBe($label)
                ->and($component->instance()->getTable()->getAction('create')->getUrl())->toBeNull();
            $edit = $component->instance()->getTable()->getAction('edit');
            $relatedModel = ContentRegistry::model($manager::$relatedSource);
            $record = $relatedModel::factory()->create([$manager::$parentKey => $owner->id]);
            expect($edit->record($record)->getUrl())->toBeNull();
        }
    }
});

it('keeps assignment mutations on the current page and preserves unsaved clergy fields', function (): void {
    $clergy = ClergyMember::factory()->create(['name' => 'Nome salvo', 'body_html' => '<p>Texto salvo</p>']);
    $parish = Parish::factory()->create();
    $parent = Livewire::test(EditClergyMember::class, ['record' => $clergy->id])
        ->fillForm(['name' => 'Nome ainda não salvo', 'body_html' => '<p>Texto ainda não salvo</p>']);
    $component = Livewire::test(AssignmentsRelationManager::class, ['ownerRecord' => $clergy, 'pageClass' => EditClergyMember::class]);
    $component->callAction(TestAction::make('create')->table(), data: ['parish_id' => $parish->id, 'role_text' => 'Pároco'])
        ->assertHasNoFormErrors()->assertNoRedirect()->assertActionNotMounted();
    $assignment = $clergy->assignments()->sole();
    $component->assertCanSeeTableRecords([$assignment])
        ->callAction(TestAction::make('edit')->table($assignment), data: ['parish_id' => $parish->id, 'role_text' => 'Vigário Paroquial'])
        ->assertHasNoFormErrors()->assertNoRedirect()->assertActionNotMounted();
    expect($assignment->fresh()->role_text)->toBe('Vigário Paroquial');
    $component->callAction(TestAction::make('delete')->table($assignment))->assertNoRedirect()->assertActionNotMounted();
    expect($clergy->assignments()->count())->toBe(0)->and($clergy->fresh()->name)->toBe('Nome salvo');
    $parent->assertFormSet(['name' => 'Nome ainda não salvo', 'body_html' => '<p>Texto ainda não salvo</p>'])->assertNoRedirect()
        ->call('save')->assertHasNoFormErrors();
    expect($clergy->fresh()->name)->toBe('Nome ainda não salvo')->and($clergy->fresh()->body_html)->toBe('<p>Texto ainda não salvo</p>');
});
