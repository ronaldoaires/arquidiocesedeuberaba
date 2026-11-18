<?php

use App\Filament\Resources\ClergyMembers\ClergyMemberResource;
use App\Filament\Resources\ClergyMembers\Pages\CreateClergyMember;
use App\Filament\Resources\ClergyMembers\Pages\EditClergyMember;
use App\Filament\Resources\ClergyMembers\Pages\ManageClergyMembers;
use App\Filament\Resources\ClergyMembers\RelationManagers\AssignmentsRelationManager;
use App\Models\ClergyCategory;
use App\Models\ClergyMember;
use App\Models\Parish;
use App\Models\ParishClergyAssignment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    Storage::fake('local');
});

it('shows a circular thumbnail with category views and discrete functions below the clergy name', function (): void {
    Storage::disk('public')->put('legacy/uploads/imagens/portrait.png', 'original');
    Storage::disk('public')->put('legacy/uploads/imagens/thumbs/portrait.png', 'thumbnail');
    $category = ClergyCategory::factory()->create(['title' => 'Padres Diocesanos']);
    $record = ClergyMember::factory()->create([
        'name' => 'Padre da listagem', 'category_id' => $category->id, 'visits' => 3456,
        'cover_path' => 'legacy/uploads/imagens/portrait.png', 'role_1_text' => 'Pároco', 'role_2_text' => '', 'role_3_text' => 'Capelão',
    ]);
    $component = Livewire::test(ManageClergyMembers::class)->assertTableColumnStateSet('category.title', 'Padres Diocesanos', $record)
        ->assertTableColumnStateSet('visits', 3456, $record)->assertSee('3.456')->assertSee('Pároco · Capelão');
    $table = $component->instance()->getTable();
    $photo = $table->getColumn('cover_path')->record($record);
    expect($photo->isCircular())->toBeTrue()->and($photo->getLabel())->toBe('Foto')->and($photo->getState())->toBe('legacy/uploads/imagens/thumbs/portrait.png')
        ->and($photo->getImageUrl($photo->getState(), $record))->toBe('/storage/legacy/uploads/imagens/thumbs/portrait.png')
        ->and($table->getColumn('name')->record($record)->getDescriptionBelow())->toBe('Pároco · Capelão');
    $component->assertSeeHtml('fi-circular')->assertSeeHtml('fi-ta-text-description');
    foreach ($component->instance()->getTableRecords() as $row) {
        expect($row->relationLoaded('category'))->toBeTrue();
    }
    expect(Storage::disk('public')->get('legacy/uploads/imagens/portrait.png'))->toBe('original');
});

it('omits empty functions and avoids repeated labels without changing stored function slots', function (): void {
    $record = ClergyMember::factory()->create(['name' => 'Padre com funções repetidas', 'role_1_text' => 'Pároco', 'role_2_text' => 'Pároco', 'role_3_text' => ' ']);
    $empty = ClergyMember::factory()->create(['name' => 'Padre sem função', 'role_1_text' => '', 'role_2_text' => null, 'role_3_text' => ' ']);
    $column = Livewire::test(ManageClergyMembers::class)->instance()->getTable()->getColumn('name');
    expect($column->record($record)->getDescriptionBelow())->toBe('Pároco')
        ->and($column->record($empty)->getDescriptionBelow())->toBeNull()
        ->and($record->fresh()->role_2_text)->toBe('Pároco')->and($record->fresh()->role_3_text)->toBe(' ');
});

it('searches clergy by category and sorts existing view counters', function (): void {
    $category = ClergyCategory::factory()->create(['title' => 'Categoria pesquisável']);
    $first = ClergyMember::factory()->create(['name' => 'Primeiro padre', 'category_id' => $category->id, 'visits' => 200]);
    $second = ClergyMember::factory()->create(['name' => 'Segundo padre', 'category_id' => null, 'visits' => 3]);
    Livewire::test(ManageClergyMembers::class)->sortTable('visits', 'desc')->assertCanSeeTableRecords([$first, $second], inOrder: true)
        ->assertSee('Sem categoria')->searchTable('Categoria pesquisável')->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second]);
    expect($first->fresh()->visits)->toBe(200)->and($second->fresh()->visits)->toBe(3);
});

it('opens clergy create and edit as normal resource pages', function (): void {
    $record = ClergyMember::factory()->create(['name' => 'Padre existente']);
    $component = Livewire::test(ManageClergyMembers::class);
    expect($component->instance()->getAction('create')->getUrl())->toBe(ClergyMemberResource::getUrl('create'))
        ->and($component->instance()->getTable()->getAction('edit')->record($record)->getUrl())->toBe(ClergyMemberResource::getUrl('edit', ['record' => $record]));
    $this->get(ClergyMemberResource::getUrl('create'))->assertOk()->assertSeeLivewire(CreateClergyMember::class);
    $this->get(ClergyMemberResource::getUrl('edit', ['record' => $record]))->assertOk()->assertSeeLivewire(EditClergyMember::class);
});

it('organizes the same editable fields into exactly three cards with activity last', function (string $operation): void {
    $page = $operation === 'create' ? CreateClergyMember::class : EditClergyMember::class;
    $parameters = $operation === 'create' ? [] : ['record' => ClergyMember::factory()->create(['name' => 'Padre'])->id];
    $component = Livewire::test($page, $parameters)->assertFormFieldDoesNotExist('slug')->assertFormFieldDoesNotExist('created_by')->assertFormFieldDoesNotExist('visits');
    $schema = $component->instance()->getSchema('form');
    $cards = array_values($schema->getComponents());
    expect($cards)->toHaveCount(3);
    foreach ($cards as $card) {
        expect($card)->toBeInstanceOf(Section::class);
    }
    expect(array_map(fn (Section $card): string => $card->getHeading(), $cards))->toBe(['Conteúdo', 'Paróquias', 'Funções']);
    $names = fn (Section $card): array => array_values(array_map(fn ($field): string => $field->getName(), $card->getChildSchema()->getFlatFields()));
    expect($names($cards[0]))->toBe(['cover_path', 'name', 'category_id', 'birthday_text', 'ordination_date_text', 'presentation_date', 'body_html'])
        ->and($names($cards[1]))->toBe(['parish_1_text', 'parish_1_link', 'parish_2_text', 'parish_2_link', 'parish_3_text', 'parish_3_link'])
        ->and($names($cards[2]))->toBe(['role_1_text', 'role_2_text', 'role_3_text', 'is_active']);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
    expect((new DOMXPath($document))->query('//form[@id="form"]//*[contains(concat(" ", normalize-space(@class), " "), " fi-section ")]')->length)->toBe(3);
})->with(['create', 'edit']);

it('saves parish text links and functions through the grouped form and returns to the list', function (): void {
    $category = ClergyCategory::factory()->create(['title' => 'Padres']);
    $data = [
        'name' => 'Padre da revisão', 'category_id' => $category->id, 'is_active' => true,
        'body_html' => '<p><strong>Biografia</strong></p>',
        'birthday_text' => '16/08', 'ordination_date_text' => '00/00/0000', 'presentation_date' => '2000-06-15',
        'parish_1_text' => 'Primeira paróquia', 'parish_1_link' => 'https://example.org/paroquia/primeira',
        'parish_2_text' => 'Segunda paróquia', 'parish_2_link' => 'https://example.org/segunda',
        'parish_3_text' => 'Terceira paróquia', 'parish_3_link' => 'https://example.org/paroquia/terceira',
        'role_1_text' => 'Pároco', 'role_2_text' => 'Uso de Ordem', 'role_3_text' => 'Capelão',
    ];
    Livewire::test(CreateClergyMember::class)->fillForm($data)->call('create')->assertHasNoFormErrors()->assertRedirect(ClergyMemberResource::getUrl('index'));
    $record = ClergyMember::sole();
    foreach (array_diff(array_keys($data), ['body_html']) as $field) {
        expect($field === 'presentation_date' ? $record->presentation_date->format('Y-m-d') : $record->getAttribute($field))->toBe($data[$field]);
    }
    expect($record->slug)->toBe('padre-da-revisao')->and($record->visits)->toBe(0)->and($record->created_by)->toBe(auth()->id());
    Livewire::test(CreateClergyMember::class)->fillForm(['name' => ''])->call('create')->assertHasFormErrors(['name' => 'required']);
});

it('preserves existing assignments counters URLs and legacy HTML when editing grouped clergy fields', function (): void {
    $html = '<p style="text-align:center"><strong>Biografia antiga</strong></p>';
    $record = ClergyMember::factory()->create(['name' => 'Padre original', 'slug' => 'padre-historico', 'visits' => 987, 'role_1_text' => 'Função histórica', 'body_html' => $html]);
    $parish = Parish::factory()->create(['name' => 'Paróquia vinculada']);
    $assignment = ParishClergyAssignment::factory()->create(['clergy_member_id' => $record->id, 'parish_id' => $parish->id, 'role_text' => 'Vigário Paroquial']);
    $url = $record->public_url;
    Livewire::test(EditClergyMember::class, ['record' => $record->id])->fillForm([
        'name' => 'Padre com nome corrigido', 'parish_1_text' => 'Referência ajustada', 'parish_1_link' => 'https://example.org/paroquia/referencia', 'role_2_text' => 'Capelão',
    ])->call('save')->assertHasNoFormErrors()->assertRedirect(ClergyMemberResource::getUrl('index'));
    expect($record->fresh()->visits)->toBe(987)->and($record->fresh()->public_url)->toBe($url)
        ->and($record->fresh()->body_html)->toBe($html)->and($record->fresh()->role_1_text)->toBe('Função histórica')
        ->and($record->fresh()->role_2_text)->toBe('Capelão')->and($record->fresh()->parish_1_link)->toBe('https://example.org/paroquia/referencia')
        ->and($assignment->fresh()->role_text)->toBe('Vigário Paroquial')->and($record->assignments()->count())->toBe(1)
        ->and(ClergyMemberResource::getRelations())->toContain(AssignmentsRelationManager::class);
});
