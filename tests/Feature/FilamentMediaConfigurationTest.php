<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\SiteSettings\Pages\ManageSiteSettings;
use App\Models\Article;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('uses public media defaults in Filament while keeping application and temporary storage private', function (): void {
    $article = Article::factory()->create(['title' => 'Configuração de mídia']);
    $component = Livewire::test(EditArticle::class, ['record' => $article->id]);
    $schema = $component->instance()->getSchema('form');

    expect(config('filament.default_filesystem_disk'))->toBe('public')
        ->and(config('filesystems.default'))->toBe('local')
        ->and(config('livewire.temporary_file_upload.disk'))->toBe('local')
        ->and(config('filesystems.links'))->toBe([])
        ->and(FileUpload::make('cover')->getDiskName())->toBe('public')
        ->and(FileUpload::make('cover')->getVisibility())->toBe('public')
        ->and(RichEditor::make('body')->container($schema)->getFileAttachmentsDiskName())->toBe('public');
});

it('loads the real administrative cover preview through the media route regardless of APP_URL', function (string $path): void {
    config(['app.url' => 'https://incorrect-origin.example']);
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
    Storage::disk('public')->put($path, $image);
    $article = Article::factory()->create(['title' => 'Prévia no painel', 'cover_path' => $path, 'is_active' => true]);
    $component = Livewire::test(EditArticle::class, ['record' => $article->id]);
    $field = $component->instance()->getSchema('form')->getComponentByStatePath('cover_path');
    expect($field)->toBeInstanceOf(FileUpload::class);
    $preview = collect($field->getUploadedFiles())->first();
    expect($preview['url'])->toBe('/storage/'.str_replace(' ', '%20', $path));
    $editor = $component->instance()->getSchema('form')->getComponentByStatePath('body_html');
    expect($editor->getFileAttachmentUrl($path))->toBe('/storage/'.$path);
    $this->get('https://admin.example.test'.$preview['url'])->assertOk()->assertHeader('Content-Type', 'image/png');
})->with(['legacy/uploads/imagens/imported cover.png', 'content/articles/new-cover.png']);

it('keeps private contact attachments outside the public disk after changing Filament defaults', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('contact-attachments/private.txt', 'private');
    $this->get('/storage/contact-attachments/private.txt')->assertNotFound();
    expect(Storage::disk('public')->exists('contact-attachments/private.txt'))->toBeFalse();
});

it('uploads the configured logo through the real settings form and uses it in the site header and footer', function (): void {
    Storage::fake('local');
    $settings = SiteSetting::factory()->create(['title' => 'Arquidiocese de Uberaba']);
    $component = Livewire::test(ManageSiteSettings::class)->mountAction(TestAction::make('edit')->table($settings));
    $field = $component->instance()->getSchema('mountedActionSchema0')->getComponentByStatePath('cover_path');
    expect($field)->toBeInstanceOf(FileUpload::class)->and($field->getLabel())->toBe('Logo')
        ->and($field->getDiskName())->toBe('public')->and($component->instance()->getTable()->getColumn('cover_path')->getLabel())->toBe('Logo');
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
    $component->fillForm(['cover_path' => UploadedFile::fake()->createWithContent('logo.png', $image)])
        ->callMountedAction()->assertHasNoFormErrors();
    $settings->refresh();
    Storage::disk('public')->assertExists($settings->cover_path);
    expect($settings->cover_path)->toStartWith('content/site_settings/');
    $this->get($settings->logo_url)->assertOk()->assertHeader('Content-Type', 'image/png');
    $html = $this->get('/')->assertOk()->getContent();
    expect(substr_count($html, 'src="'.$settings->logo_url.'"'))->toBe(2);
});

it('retains the original site logo when the configured file is missing', function (): void {
    SiteSetting::factory()->create(['title' => 'Arquidiocese', 'cover_path' => 'legacy/uploads/imagens/missing-logo.png']);
    $html = $this->get('/')->assertOk()->getContent();
    expect(substr_count($html, 'src="'.asset('assets/site/logoVermelho.png').'"'))->toBe(2);
});
