<?php

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['name' => 'Administrador', 'email' => 'admin@example.test', 'is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('shows photo email and the actual access level with a red administrator badge', function (): void {
    $admin = auth()->user();
    $member = User::factory()->create(['email' => 'member@example.test', 'legacy_level' => 1, 'is_active' => true]);
    $component = Livewire::test(ManageUsers::class)->assertTableColumnStateSet('email', 'member@example.test', $member)
        ->assertTableColumnStateSet('legacy_level', 3, $admin)->assertTableColumnStateSet('legacy_level', 1, $member)
        ->assertSee('Administrador')->assertSee('Usuário');
    $table = $component->instance()->getTable();
    expect($table->getColumn('cover_path')->getLabel())->toBe('Foto')
        ->and($table->getColumn('cover_path')->record($member)->isCircular())->toBeTrue()
        ->and($table->getColumn('legacy_level')->record($admin)->getColor(3))->toBe('danger')
        ->and($table->getColumn('legacy_level')->record($member)->getColor(1))->toBe('gray');
    $component->searchTable('member@example.test')->assertCanSeeTableRecords([$member])->assertCanNotSeeTableRecords([$admin]);
});

it('keeps the photo optional and creates a user without an image', function (): void {
    $component = Livewire::test(ManageUsers::class)->mountAction('create');
    $field = $component->instance()->getSchema('mountedActionSchema0')->getComponentByStatePath('cover_path');
    expect($field)->toBeInstanceOf(FileUpload::class)->and($field->getLabel())->toBe('Foto')->and($field->isRequired())->toBeFalse();
    $component->fillForm(['name' => 'Usuário sem foto', 'email' => 'no-photo@example.test', 'password' => 'A secure password 123!', 'legacy_level' => 1, 'is_active' => true])
        ->callMountedAction()->assertHasNoFormErrors();
    $user = User::where('email', 'no-photo@example.test')->firstOrFail();
    expect($user->cover_path)->toBeNull()->and($user->legacy_level)->toBe(1);
});

it('uploads a photo through the real user edit action without altering the password or level', function (): void {
    $member = User::factory()->create(['email' => 'photo@example.test', 'legacy_level' => 1, 'is_active' => true]);
    $password = $member->password;
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
    Livewire::test(ManageUsers::class)->mountAction(TestAction::make('edit')->table($member))
        ->fillForm(['cover_path' => UploadedFile::fake()->createWithContent('photo.png', $image)])->callMountedAction()->assertHasNoFormErrors();
    $member->refresh();
    expect($member->cover_path)->toStartWith('content/users/')->and($member->password)->toBe($password)->and($member->legacy_level)->toBe(1);
    Storage::disk('public')->assertExists($member->cover_path);
    $this->get(Storage::disk('public')->url($member->cover_path))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->assertDatabaseHas('media_assets', ['path' => $member->cover_path, 'state' => 'available']);
});

it('preserves shared user photos until the final reference is replaced', function (): void {
    Storage::disk('public')->put('content/users/shared.png', 'old photo');
    Storage::disk('public')->put('content/users/new.png', 'new photo');
    $first = User::factory()->create(['cover_path' => 'content/users/shared.png', 'legacy_level' => 1]);
    $second = User::factory()->create(['cover_path' => 'content/users/shared.png', 'legacy_level' => 1]);
    $first->forceFill(['cover_path' => 'content/users/new.png'])->save();
    Storage::disk('public')->assertExists('content/users/shared.png');
    $second->forceFill(['cover_path' => 'content/users/new.png'])->save();
    Storage::disk('public')->assertMissing('content/users/shared.png');
    Storage::disk('public')->assertExists('content/users/new.png');
    $this->assertDatabaseHas('media_assets', ['path' => 'content/users/shared.png', 'state' => 'archived']);
});

it('does not remove the original photo after a rolled back user update', function (): void {
    Storage::disk('public')->put('content/users/original.png', 'original');
    $member = User::factory()->create(['cover_path' => 'content/users/original.png']);
    try {
        DB::transaction(function () use ($member): void {
            $member->forceFill(['cover_path' => null])->save();
            throw new RuntimeException('Cancel photo update.');
        });
    } catch (RuntimeException) {
    }
    expect($member->fresh()->cover_path)->toBe('content/users/original.png');
    Storage::disk('public')->assertExists('content/users/original.png');
});
