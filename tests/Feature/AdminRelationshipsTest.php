<?php

use App\Filament\Resources\ClergyMembers\ClergyMemberResource;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Parishes\Pages\EditParish;
use App\Filament\Resources\Parishes\ParishResource;
use App\Filament\Resources\Parishes\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\PastoralGalleries\PastoralGalleryResource;
use App\Filament\Resources\PastoralGroups\PastoralGroupResource;
use App\Filament\Resources\RadioStations\RadioStationResource;
use App\Models\ClergyMember;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Parish;
use App\Models\PastoralGallery;
use App\Models\PastoralGroup;
use App\Models\RadioStation;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders dedicated edit pages with relationship managers', function (string $model, string $resource): void {
    $record = $model::factory()->create(['is_active' => true]);
    $this->get($resource::getUrl('edit', ['record' => $record]))->assertOk();
})->with([
    [Parish::class, ParishResource::class],
    [ClergyMember::class, ClergyMemberResource::class],
    [Page::class, PageResource::class],
    [Gallery::class, GalleryResource::class],
    [PastoralGroup::class, PastoralGroupResource::class],
    [PastoralGallery::class, PastoralGalleryResource::class],
    [RadioStation::class, RadioStationResource::class],
]);

it('creates a real parish clergy assignment from its owner relation manager', function (): void {
    $parish = Parish::factory()->create(['name' => 'Paróquia de teste', 'is_active' => true]);
    $clergy = ClergyMember::factory()->create(['name' => 'Clérigo de teste', 'is_active' => true]);
    Livewire::test(AssignmentsRelationManager::class, [
        'ownerRecord' => $parish,
        'pageClass' => EditParish::class,
    ])->callAction(TestAction::make('create')->table(), data: ['clergy_member_id' => $clergy->id, 'role_text' => 'Pároco'])->assertHasNoFormErrors();
    $this->assertDatabaseHas('parish_clergy_assignments', ['parish_id' => $parish->id, 'clergy_member_id' => $clergy->id, 'role_text' => 'Pároco']);
});
