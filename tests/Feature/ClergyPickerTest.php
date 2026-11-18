<?php

use App\Filament\Resources\Parishes\Pages\CreateParish;
use App\Filament\Resources\Parishes\Pages\ManageParishes;
use App\Filament\Resources\Parishes\ParishResource;
use App\Livewire\Admin\ClergyPicker;
use App\Models\ClergyMember;
use App\Models\Parish;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('opens parish create and edit through normal page links with larger icons', function (): void {
    $record = Parish::factory()->create(['name' => 'Paróquia existente']);
    $list = Livewire::test(ManageParishes::class);
    $create = $list->instance()->getAction('create');
    $edit = $list->instance()->getTable()->getAction('edit')->record($record);
    $delete = $list->instance()->getTable()->getAction('delete')->record($record);
    expect($create->getUrl())->toBe(ParishResource::getUrl('create'))
        ->and($edit->getUrl())->toBe(ParishResource::getUrl('edit', ['record' => $record]))
        ->and($edit->getSize())->toBe(Size::ExtraLarge)->and($delete->getSize())->toBe(Size::ExtraLarge)
        ->and($edit->getIconSize())->toBe(IconSize::ExtraLarge)->and($delete->getIconSize())->toBe(IconSize::ExtraLarge);
    $this->get(ParishResource::getUrl('create'))->assertOk()->assertSeeLivewire(CreateParish::class);
    $this->get(ParishResource::getUrl('edit', ['record' => $record]))->assertOk();
});

it('does not load the entire clergy list before opening and returns only ten rows per page', function (): void {
    foreach (range(1, 25) as $number) {
        ClergyMember::factory()->create(['name' => sprintf('Padre %03d', $number)]);
    }
    DB::enableQueryLog();
    $component = Livewire::test(ClergyPicker::class)->assertSee('Adicionar clero')->assertDontSee('Padre 001');
    expect(collect(DB::getQueryLog())->filter(fn (array $entry): bool => str_contains($entry['query'], 'clergy_members')))->toBeEmpty();
    DB::disableQueryLog();
    $component->call('open')->assertSee('Padre 001')->assertSee('Padre 010')->assertDontSee('Padre 011');
    $component->call('gotoPage', 2, 'clergyPage')->assertSee('Padre 011')->assertSee('Padre 020')->assertDontSee('Padre 001')->assertDontSee('Padre 021');
    $component->call('gotoPage', 3, 'clergyPage')->assertSee('Padre 025')->assertDontSee('Padre 020');
});

it('retains selections and functions while searching and changing pages and confirms without saving records', function (): void {
    foreach (range(1, 21) as $number) {
        ClergyMember::factory()->create(['name' => sprintf('Padre %03d', $number)]);
    }
    $first = ClergyMember::orderBy('name')->first();
    $last = ClergyMember::orderByDesc('name')->first();
    $component = Livewire::test(ClergyPicker::class)->call('open')->call('toggleMember', $first->id)->set('draft.'.$first->id.'.role_text', 'Pároco')
        ->call('gotoPage', 3, 'clergyPage')->call('toggleMember', $last->id)->set('draft.'.$last->id.'.role_text', 'Capelão')
        ->set('search', 'Padre 001')->assertSee('Padre 001')->assertDontSee('Padre 021')->assertSet('paginators.clergyPage', 1)
        ->assertSet('draft.'.$first->id.'.role_text', 'Pároco')->assertSet('draft.'.$last->id.'.role_text', 'Capelão')
        ->call('confirm')->assertSet('isOpen', false)->assertSet('value.'.$first->id.'.role_text', 'Pároco')->assertSet('value.'.$last->id.'.role_text', 'Capelão');
    expect(DB::table('parish_clergy_assignments')->count())->toBe(0);
    $component->assertSee('Padre 001')->assertSee('Padre 021');
});

it('enables a role only after selection and discards a canceled draft', function (): void {
    $member = ClergyMember::factory()->create(['name' => 'Padre Teste']);
    $component = Livewire::test(ClergyPicker::class)->call('open');
    $html = $component->html();
    expect($html)->toMatch('/<select[^>]*disabled/s');
    $component->call('toggleMember', $member->id)->set('draft.'.$member->id.'.role_text', 'Vigário Paroquial');
    expect($component->html())->not->toMatch('/<select[^>]*disabled/s');
    $component->call('cancel')->assertSet('value', [])->assertSet('draft', [])->assertSet('isOpen', false);
});

it('hydrates existing roles and removes a selection without deleting a clergy record', function (): void {
    $member = ClergyMember::factory()->create(['name' => 'Padre Existente']);
    $state = [$member->id => ['clergy_member_id' => $member->id, 'selected' => true, 'role_text' => 'Função histórica']];
    Livewire::test(ClergyPicker::class, ['value' => $state, 'existingRoles' => [$member->id => 'Função histórica']])
        ->assertSee('Padre Existente')->assertSee('Função histórica')->call('open')->assertSet('draft', $state)
        ->call('confirm')->assertHasNoErrors()->call('removeMember', $member->id)->assertSet('value', []);
    expect($member->fresh()->trashed())->toBeFalse();
});

it('rejects invalid functions and denies unauthorized access', function (): void {
    $member = ClergyMember::factory()->create(['name' => 'Padre Teste']);
    Livewire::test(ClergyPicker::class)->call('open')->call('toggleMember', $member->id)->set('draft.'.$member->id.'.role_text', 'Função inventada')->call('confirm')
        ->assertHasErrors(['draft.'.$member->id.'.role_text'])->assertSet('value', []);
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 1]));
    Livewire::test(ClergyPicker::class)->assertForbidden();
});

it('opens the selected clergy function directly and renders an empty search result safely', function (): void {
    $member = ClergyMember::factory()->create(['name' => 'Padre Selecionado']);
    $state = [$member->id => ['clergy_member_id' => $member->id, 'selected' => true, 'role_text' => 'Pároco']];
    Livewire::test(ClergyPicker::class, ['value' => $state])->call('open', $member->id)->assertSet('search', 'Padre Selecionado')->assertSee('Padre Selecionado')
        ->set('search', 'Nome inexistente')->assertSee('Nenhum clero encontrado.')->assertSet('draft.'.$member->id.'.role_text', 'Pároco');
});

it('highlights inactive clergy with danger badges in both the selected summary and the picker', function (): void {
    $member = ClergyMember::factory()->create(['name' => 'Clérigo inativo em destaque', 'is_active' => false]);
    $state = [$member->id => ['clergy_member_id' => $member->id, 'selected' => true, 'role_text' => 'Pároco']];
    $component = Livewire::test(ClergyPicker::class, ['value' => $state])->assertSee('Clérigo inativo em destaque')->assertSee('Inativo');
    foreach ([false, true] as $open) {
        if ($open) {
            $component->call('open');
        }
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
        $xpath = new DOMXPath($document);
        expect($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-color-danger ") and normalize-space(.)="Inativo"]')->length)->toBe($open ? 2 : 1);
    }
    expect($member->fresh()->is_active)->toBeFalse();
});
