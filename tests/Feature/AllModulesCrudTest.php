<?php

use App\Filament\Support\ContentResource;
use App\Models\User;
use App\Support\ContentRegistry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
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

it('creates and updates each module using only the legacy form fields and redirects to its index', function (string $source): void {
    $module = ContentRegistry::get($source);
    $model = ContentRegistry::model($source);
    $contract = config('admin.'.$source);
    $resource = collect(Filament::getPanel('admin')->getResources())->first(fn (string $class): bool => is_subclass_of($class, ContentResource::class) && $class::$sourceTable === $source);
    $data = [];
    foreach ($module['columns'] as $column) {
        if (! in_array($column['name'], $contract, true) || ! $column['relation']) {
            continue;
        }
        $parent = ContentRegistry::get($column['relation']);
        $parentClass = ContentRegistry::model($column['relation']);
        $data[$column['name']] = $parentClass::factory()->create([$parent['title'] => 'Relacionamento de teste', 'is_active' => true])->id;
    }
    $editField = collect(['title', 'name', 'track_name', 'role_text', 'author_name', 'video_id', 'is_active'])->first(fn (string $field): bool => in_array($field, $contract, true));
    $initial = $editField === 'is_active' ? true : 'Registro de teste';
    $updated = $editField === 'is_active' ? false : 'Registro atualizado';
    if ($editField === 'video_id') {
        $initial = 'abcdefghijk';
        $updated = 'lmnopqrstuv';
    }
    if ($editField === 'role_text') {
        $initial = 'Pároco';
        $updated = 'Capelão';
    }
    $data[$editField] = $initial;
    if (in_array('is_active', $contract, true)) {
        $data['is_active'] = true;
    }
    if (in_array('created_at', $contract, true)) {
        $data['created_at'] = now()->toDateTimeString();
    }
    if ($source === 'usuario') {
        $data += ['email' => 'new-admin@example.test', 'password' => 'A secure password 123!', 'legacy_level' => 3];
    }
    if ($resource::isPhotoResource()) {
        $data['photo_path'] = [UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true))];
    }
    $page = $resource::getPages()['index']->getPage();
    if ($resource::hasPage('create')) {
        Livewire::test($resource::getPages()['create']->getPage())->assertFormFieldDoesNotExist('slug')->assertFormFieldDoesNotExist('created_by')
            ->fillForm($data)->call('create')->assertHasNoFormErrors()->assertNotified()->assertRedirect($resource::getUrl('index'));
    } else {
        Livewire::test($page)->mountAction('create')->assertFormFieldDoesNotExist('slug')->assertFormFieldDoesNotExist('created_by')
            ->fillForm($data)->callMountedAction()->assertHasNoFormErrors()->assertNotified()->assertRedirect($resource::getUrl('index'));
    }
    $record = $model::orderByDesc('id')->firstOrFail();
    expect($record->getAttribute($editField))->toBe($initial);
    if ($source === 'usuario') {
        expect($record->legacy_level)->toBe(3);
    }
    if (in_array('created_by', array_column($module['columns'], 'name'), true) && $source !== 'usuario') {
        expect($record->created_by)->toBe(auth()->id());
    }
    $originalSlug = $record->getAttribute('slug');
    if (in_array('slug', array_column($module['columns'], 'name'), true)) {
        expect($originalSlug)->not->toBeEmpty();
    }
    if ($resource::hasPage('edit')) {
        Livewire::test($resource::getPages()['edit']->getPage(), ['record' => $record->id])->assertFormFieldDoesNotExist('slug')->assertFormFieldDoesNotExist('created_by')
            ->fillForm([$editField => $updated])->call('save')->assertHasNoFormErrors()->assertRedirect($resource::getUrl('index'));
    } else {
        Livewire::test($page)->mountAction(TestAction::make('edit')->table($record))->assertFormFieldDoesNotExist('slug')->assertFormFieldDoesNotExist('created_by')
            ->fillForm([$editField => $updated])->callMountedAction()->assertHasNoErrors()->assertRedirect($resource::getUrl('index'));
    }
    expect($record->fresh()->getAttribute($editField))->toBe($updated)->and($record->fresh()->getAttribute('slug'))->toBe($originalSlug);
})->with(array_keys(require __DIR__.'/../../config/content.php'));
