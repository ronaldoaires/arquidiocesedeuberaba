<?php

use App\Filament\Resources\Parishes\Pages\CreateParish;
use App\Filament\Resources\Parishes\Pages\EditParish;
use App\Models\City;
use App\Models\ClergyMember;
use App\Models\Deanery;
use App\Models\Parish;
use App\Models\ParishClergyAssignment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->data = [
        'name' => 'Paróquia de validação',
        'city_id' => City::factory()->create(['name' => 'Uberaba'])->id,
        'deanery_id' => Deanery::factory()->create(['name' => 'Forania'])->id,
        'is_active' => true,
    ];
});

it('rejects invalid websites and social links on create', function (string $field, string $invalid): void {
    Livewire::test(CreateParish::class)->fillForm($this->data + [$field => $invalid])->call('create')->assertHasFormErrors([$field => 'url']);
    expect(Parish::count())->toBe(0);
})->with(['site', 'facebook', 'instagram', 'youtube'])->with(['xxxxxx', 'javascript:alert(1)', 'ftp://example.org/file', 'https://']);

it('accepts valid optional HTTP links and a valid email', function (): void {
    $links = ['site' => 'https://example.org/parish?source=site#contact', 'facebook' => 'https://www.facebook.com/example', 'instagram' => 'https://www.instagram.com/example/', 'youtube' => 'https://www.youtube.com/@example', 'email' => 'paroquia@example.org'];
    Livewire::test(CreateParish::class)->fillForm($this->data + $links)->call('create')->assertHasNoFormErrors();
    expect(Parish::sole()->only(array_keys($links)))->toBe($links);
});

it('keeps optional links blank and rejects invalid changes without altering the saved parish', function (): void {
    Livewire::test(CreateParish::class)->fillForm($this->data)->call('create')->assertHasNoFormErrors();
    $parish = Parish::sole();
    Livewire::test(EditParish::class, ['record' => $parish->id])->fillForm(['name' => 'Alteração inválida', 'site' => 'xxxxxx', 'email' => 'email-invalido'])->call('save')
        ->assertHasFormErrors(['site' => 'url', 'email' => 'email']);
    expect($parish->fresh()->name)->toBe($this->data['name'])->and($parish->fresh()->site)->toBeNull();
});

it('wraps the full form and its submit actions in a card and places activity after clergy', function (string $operation): void {
    $page = $operation === 'create' ? CreateParish::class : EditParish::class;
    $parameters = $page === EditParish::class ? ['record' => Parish::factory()->create($this->data)->id] : [];
    $component = Livewire::test($page, $parameters);
    expect($component->instance()->getFormContentComponent())->toBeInstanceOf(Section::class);
    $fields = collect($component->instance()->getSchema('form')->getComponents())->filter(fn ($field): bool => $field instanceof Field)->map(fn ($field): string => $field->getName())->values()->all();
    expect(array_slice($fields, -2))->toBe(['assignments', 'is_active']);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-section ")]//form[@id="form"]//button[@type="submit"]')->length)->toBeGreaterThan(0);
})->with(['create', 'edit']);

it('preserves all parish assignments while publishing only active clergy', function (): void {
    $first = ClergyMember::factory()->create(['name' => 'Primeiro clérigo', 'is_active' => true]);
    $second = ClergyMember::factory()->create(['name' => 'Segundo clérigo', 'is_active' => true]);
    $third = ClergyMember::factory()->create(['name' => 'Terceiro clérigo inativo', 'is_active' => false]);
    $state = [];
    foreach ([$first, $second, $third] as $member) {
        $state[$member->id] = ['clergy_member_id' => $member->id, 'selected' => true, 'role_text' => 'Diácono Permanente'];
    }
    Livewire::test(CreateParish::class)->fillForm($this->data + ['assignments' => $state])->call('create')->assertHasNoFormErrors();
    $parish = Parish::sole();
    expect($parish->assignments()->count())->toBe(3)->and($third->fresh()->is_active)->toBeFalse();
    $this->get('/paroquia/'.$parish->slug)->assertOk()->assertSee('Primeiro clérigo')->assertSee('Segundo clérigo')->assertDontSee('Terceiro clérigo inativo')
        ->assertSee('href="'.$first->public_url.'"', false)->assertDontSee('href="'.$third->public_url.'"', false);
    $this->get('/clero/'.$third->slug)->assertNotFound();
});

it('preserves legacy role priority and fallback and omits removed clergy and removed associations', function (): void {
    $parish = Parish::factory()->create($this->data);
    $deacon = ClergyMember::factory()->create(['name' => 'Diácono do teste', 'is_active' => true]);
    $pastor = ClergyMember::factory()->create(['name' => 'Pároco do teste', 'is_active' => true]);
    $fallback = ClergyMember::factory()->create(['name' => 'Clérigo sem função no vínculo', 'is_active' => true, 'role_1_text' => 'Uso de Ordem']);
    foreach ([[$deacon, 'Diácono Permanente'], [$pastor, 'Pároco'], [$fallback, null]] as [$member, $role]) {
        ParishClergyAssignment::factory()->create(['parish_id' => $parish->id, 'clergy_member_id' => $member->id, 'role_text' => $role]);
    }
    $removed = ClergyMember::factory()->create(['name' => 'Clérigo excluído', 'is_active' => true]);
    ParishClergyAssignment::factory()->create(['parish_id' => $parish->id, 'clergy_member_id' => $removed->id]);
    $removed->delete();
    $unlinked = ClergyMember::factory()->create(['name' => 'Vínculo removido', 'is_active' => true]);
    ParishClergyAssignment::factory()->create(['parish_id' => $parish->id, 'clergy_member_id' => $unlinked->id])->delete();
    $this->get('/paroquia/'.$parish->slug)->assertOk()->assertSeeInOrder(['Pároco do teste', 'Diácono do teste', 'Clérigo sem função no vínculo'])
        ->assertSee('Uso de Ordem')->assertDontSee('Clérigo excluído')->assertDontSee('Vínculo removido');
});

it('omits an empty public clergy block and follows activity changes without removing the assignment', function (): void {
    $parish = Parish::factory()->create($this->data);
    $member = ClergyMember::factory()->create(['name' => 'Clérigo com publicação controlada', 'is_active' => false]);
    ParishClergyAssignment::factory()->create(['parish_id' => $parish->id, 'clergy_member_id' => $member->id, 'role_text' => 'Pároco']);
    $path = '/paroquia/'.$parish->slug;
    $this->get($path)->assertOk()->assertDontSee('Clérigo com publicação controlada')->assertViewHas('related', fn (array $related): bool => $related['assignments']->isEmpty());
    $member->update(['is_active' => true]);
    $this->get($path)->assertOk()->assertSee('Clérigo com publicação controlada');
    $member->update(['is_active' => false]);
    $this->get($path)->assertOk()->assertDontSee('Clérigo com publicação controlada');
    expect($parish->assignments()->count())->toBe(1);
});
