<?php

use App\Filament\Resources\ClergyMembers\Pages\CreateClergyMember;
use App\Filament\Resources\Events\Pages\ManageEvents;
use App\Filament\Resources\Parishes\Pages\CreateParish;
use App\Filament\Resources\Parishes\Pages\EditParish;
use App\Filament\Support\ParishClergySelection;
use App\Filament\Support\PreservingRichEditor;
use App\Models\City;
use App\Models\ClergyMember;
use App\Models\Deanery;
use App\Models\Parish;
use App\Models\ParishClergyAssignment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->parish = Parish::factory()->create([
        'name' => 'Paróquia de teste',
        'city_id' => City::factory()->create(['name' => 'Uberaba'])->id,
        'deanery_id' => Deanery::factory()->create(['name' => 'Forania de teste'])->id,
        'service_hours' => '<p><strong>Secretaria:</strong> segunda a sexta</p>',
        'office_hours' => '<p><em>Missas:</em> domingo às 9h</p>',
    ]);
});

it('renders searchable clergy checkboxes and the exact legacy role choices with saved selections', function (): void {
    $selected = ClergyMember::factory()->create(['name' => 'Padre João']);
    $other = ClergyMember::factory()->create(['name' => 'Padre Antônio']);
    ParishClergyAssignment::factory()->create(['parish_id' => $this->parish->id, 'clergy_member_id' => $selected->id, 'role_text' => 'Vigário Paroquial']);
    $component = Livewire::test(EditParish::class, ['record' => $this->parish->id])->assertSee('Adicionar clero')->assertSee('Padre João')->assertDontSee('Padre Antônio');
    $field = $component->instance()->getSchema('form')->getComponentByStatePath('assignments');
    expect($field)->toBeInstanceOf(ParishClergySelection::class)
        ->and(array_keys($field::roles()))->toBe(['Pároco', 'Administrador Paroquial', 'Capelão', 'Vigário Paroquial', 'Diácono Permanente'])
        ->and($field->getState()[$selected->id]['selected'])->toBeTrue()
        ->and($field->getState()[$selected->id]['role_text'])->toBe('Vigário Paroquial')
        ->and($field->getState())->not->toHaveKey($other->id);
    expect($component->html())->toContain('Adicionar clero', 'Vigário Paroquial', 'x-modelable="$wire.value"', '$parent.data.assignments');
});

it('saves checked clergy roles removes unchecked associations and restores a reselected association', function (): void {
    $first = ClergyMember::factory()->create(['name' => 'Padre Primeiro']);
    $second = ClergyMember::factory()->create(['name' => 'Padre Segundo']);
    $old = ParishClergyAssignment::factory()->create(['parish_id' => $this->parish->id, 'clergy_member_id' => $first->id, 'role_text' => 'Pároco']);
    $state = [
        $first->id => ['clergy_member_id' => $first->id, 'selected' => false, 'role_text' => ''],
        $second->id => ['clergy_member_id' => $second->id, 'selected' => true, 'role_text' => 'Diácono Permanente'],
    ];
    Livewire::test(EditParish::class, ['record' => $this->parish->id])->fillForm(['assignments' => $state])->call('save')->assertHasNoFormErrors();
    expect($old->fresh()->trashed())->toBeTrue()->and($this->parish->assignments()->sole()->role_text)->toBe('Diácono Permanente');
    $state[$first->id]['selected'] = true;
    $state[$first->id]['role_text'] = 'Administrador Paroquial';
    Livewire::test(EditParish::class, ['record' => $this->parish->id])->fillForm(['assignments' => $state])->call('save')->assertHasNoFormErrors();
    expect($old->fresh()->trashed())->toBeFalse()->and($old->fresh()->role_text)->toBe('Administrador Paroquial');
});

it('uses simple rich editors for both schedules and preserves unchanged legacy HTML', function (): void {
    $original = $this->parish->only(['service_hours', 'office_hours']);
    $component = Livewire::test(EditParish::class, ['record' => $this->parish->id]);
    foreach (['service_hours', 'office_hours'] as $name) {
        $field = $component->instance()->getSchema('form')->getComponentByStatePath($name);
        expect($field)->toBeInstanceOf(PreservingRichEditor::class)
            ->and($field->getToolbarButtons())->toBe([['bold', 'italic', 'underline'], ['bulletList', 'orderedList'], ['textColor', 'highlight'], ['link'], ['fullscreen']]);
    }
    $component->assertSee('Horários de Atendimento da Secretaria Paroquial')->assertSee('Horário de Missa')->call('save')->assertHasNoFormErrors();
    expect($this->parish->fresh()->only(array_keys($original)))->toBe($original);
    Livewire::test(EditParish::class, ['record' => $this->parish->id])->fillForm([
        'service_hours' => '<p><strong>Secretaria:</strong> das 8h às 17h</p>',
        'office_hours' => '<p><em>Missas:</em> sábado às 19h</p>',
    ])->call('save')->assertHasNoFormErrors();
    expect($this->parish->fresh()->service_hours)->toContain('<strong>', '8h às 17h')
        ->and($this->parish->fresh()->office_hours)->toContain('<em>', 'sábado às 19h');
});

it('retains a historical role that is outside the new selection choices on an unchanged save', function (): void {
    $clergy = ClergyMember::factory()->create(['name' => 'Padre Histórico']);
    $assignment = ParishClergyAssignment::factory()->create(['parish_id' => $this->parish->id, 'clergy_member_id' => $clergy->id, 'role_text' => 'Função histórica']);
    Livewire::test(EditParish::class, ['record' => $this->parish->id])->assertSee('Função histórica')->call('save')->assertHasNoFormErrors();
    expect($assignment->fresh()->role_text)->toBe('Função histórica');
});

it('creates a parish with checked clergy and permits the original no-role option', function (): void {
    $clergy = ClergyMember::factory()->create(['name' => 'Padre Selecionado']);
    Livewire::test(CreateParish::class)->fillForm([
        'name' => 'Nova paróquia',
        'city_id' => $this->parish->city_id,
        'deanery_id' => $this->parish->deanery_id,
        'assignments' => [$clergy->id => ['clergy_member_id' => $clergy->id, 'selected' => true, 'role_text' => '']],
    ])->call('create')->assertHasNoFormErrors();
    $created = Parish::where('name', 'Nova paróquia')->sole();
    expect($created->assignments()->sole()->clergy_member_id)->toBe($clergy->id)
        ->and($created->assignments()->sole()->role_text)->toBeNull();
});

it('rejects an invented role and rolls back the parish edit', function (): void {
    $clergy = ClergyMember::factory()->create(['name' => 'Padre Selecionado']);
    Livewire::test(EditParish::class, ['record' => $this->parish->id])->fillForm([
        'name' => 'Alteração que deve falhar',
        'assignments' => [$clergy->id => ['clergy_member_id' => $clergy->id, 'selected' => true, 'role_text' => 'Função inventada']],
    ])->call('save')->assertHasErrors(['data.assignments']);
    expect($this->parish->fresh()->name)->toBe('Paróquia de teste')->and($this->parish->assignments()->count())->toBe(0);
});

it('uses the full event editor and preserves the original clergy role radio choices', function (): void {
    $event = Livewire::test(ManageEvents::class)->mountAction('create');
    $editor = $event->instance()->getSchema('mountedActionSchema0')->getComponentByStatePath('body_html');
    expect($editor)->toBeInstanceOf(PreservingRichEditor::class)
        ->and(Arr::flatten($editor->getToolbarButtons()))->toContain('bold', 'italic', 'underline', 'h2', 'h3', 'table', 'attachFiles', 'insertDocument', 'fullscreen')
        ->and($editor->getFileAttachmentsDiskName())->toBe('public');
    $clergy = Livewire::test(CreateClergyMember::class);
    foreach (['role_1_text', 'role_2_text', 'role_3_text'] as $name) {
        $field = $clergy->instance()->getSchema('form')->getComponentByStatePath($name);
        expect($field)->toBeInstanceOf(Radio::class)
            ->and($field->getOptions())->toBe(['' => 'Não tem', 'Pároco' => 'Pároco', 'Vigário Paroquial' => 'Vigário Paroquial', 'Uso de Ordem' => 'Uso de Ordem', 'Diácono Permanente' => 'Diácono Permanente', 'Administrador Paroquial' => 'Administrador Paroquial', 'Capelão' => 'Capelão']);
    }
});
