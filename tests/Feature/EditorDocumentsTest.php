<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    Storage::fake('local');
});

it('uploads a document from the editor toolbar and dispatches an insertion command', function (): void {
    $article = Article::factory()->create(['title' => 'Documento no texto', 'body_html' => '<p>Original</p>', 'is_active' => true]);
    $file = UploadedFile::fake()->create('document.pdf', 10, 'application/pdf');
    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->mountAction(TestAction::make('insertDocument')->schemaComponent('body_html', 'form'))
        ->fillForm(['label' => 'Documento de teste', 'file' => [$file]])
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertDispatched('run-rich-editor-commands');
    expect(Storage::disk('public')->allFiles('editor/documents'))->toHaveCount(1);
});

it('tracks a newly saved media reference without deleting shared files', function (): void {
    Storage::disk('public')->put('editor/shared.pdf', '%PDF-1.4 sample');
    $article = Article::factory()->create(['title' => 'Arquivo', 'body_html' => '<a href="/storage/editor/shared.pdf">Documento</a>']);
    $this->assertDatabaseHas('media_assets', ['path' => 'editor/shared.pdf']);
    $other = Article::factory()->create(['title' => 'Outro conteúdo', 'body_html' => '<a href="/storage/editor/shared.pdf">Documento</a>']);
    $article->delete();
    Storage::disk('public')->assertExists('editor/shared.pdf');
    $other->delete();
    Storage::disk('public')->assertMissing('editor/shared.pdf');
});
