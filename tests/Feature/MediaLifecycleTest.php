<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\Gallery;
use App\Models\GalleryPhoto;
use App\Models\User;
use App\Services\MediaLifecycle;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

it('removes images dropped from saved rich text including data ids and thumbnails', function (): void {
    Storage::disk('public')->put('editor/articles/image.png', 'original');
    Storage::disk('public')->put('editor/articles/thumbs/image.png', 'thumbnail');
    $record = Article::factory()->create(['body_html' => '<p>Antes</p><img src="/storage/editor/articles/image.png" data-id="editor/articles/image.png">']);
    $record->update(['body_html' => '<p>Depois</p>']);
    Storage::disk('public')->assertMissing(['editor/articles/image.png', 'editor/articles/thumbs/image.png']);
    expect(Storage::disk('local')->allFiles('media-trash/files'))->toHaveCount(2);
});

it('removes a replaced cover only after the record saves and retains the new file', function (): void {
    Storage::disk('public')->put('content/old.png', 'old');
    Storage::disk('public')->put('content/thumbs/old.png', 'old thumbnail');
    Storage::disk('public')->put('content/new.png', 'new');
    $record = Article::factory()->create(['cover_path' => 'content/old.png']);
    $record->update(['cover_path' => 'content/new.png']);
    Storage::disk('public')->assertMissing(['content/old.png', 'content/thumbs/old.png']);
    Storage::disk('public')->assertExists('content/new.png');
});

it('clears every unshared file on deletion and restores it from private recovery storage', function (): void {
    Storage::disk('public')->put('legacy/uploads/imagens/cover.png', 'cover bytes');
    Storage::disk('public')->put('legacy/uploads/summernote/inline.png', 'inline bytes');
    $record = Article::factory()->create(['cover_path' => 'legacy/uploads/imagens/cover.png', 'body_html' => '<img src="https://arquidiocesedeuberaba.com.br/uploads/summernote/inline.png">']);
    $record->delete();
    Storage::disk('public')->assertMissing(['legacy/uploads/imagens/cover.png', 'legacy/uploads/summernote/inline.png']);
    $this->get('/storage/media-trash/files/example')->assertNotFound();
    $record->restore();
    expect(Storage::disk('public')->get('legacy/uploads/imagens/cover.png'))->toBe('cover bytes')
        ->and(Storage::disk('public')->get('legacy/uploads/summernote/inline.png'))->toBe('inline bytes');
});

it('keeps images referenced by another inactive record or through a legacy URL alias', function (): void {
    Storage::disk('public')->put('legacy/uploads/summernote/shared.png', 'shared');
    $first = Article::factory()->create(['body_html' => '<img src="/storage/legacy/uploads/summernote/shared.png">']);
    $second = Article::factory()->create(['is_active' => false, 'body_html' => '<img src="/uploads/summernote/shared.png">']);
    $first->delete();
    Storage::disk('public')->assertExists('legacy/uploads/summernote/shared.png');
    $second->delete();
    Storage::disk('public')->assertMissing('legacy/uploads/summernote/shared.png');
});

it('protects the original and thumbnail when either is still referenced', function (): void {
    Storage::disk('public')->put('content/shared.png', 'original');
    Storage::disk('public')->put('content/thumbs/shared.png', 'thumbnail');
    $cover = Article::factory()->create(['cover_path' => 'content/shared.png']);
    $inline = Article::factory()->create(['body_html' => '<img src="/storage/content/thumbs/shared.png">']);
    $cover->delete();
    Storage::disk('public')->assertExists(['content/shared.png', 'content/thumbs/shared.png']);
    $inline->delete();
    Storage::disk('public')->assertMissing(['content/shared.png', 'content/thumbs/shared.png']);
});

it('does not remove public files when the database change rolls back', function (): void {
    Storage::disk('public')->put('editor/articles/keep.png', 'keep');
    $record = Article::factory()->create(['body_html' => '<img src="/storage/editor/articles/keep.png">']);
    DB::beginTransaction();
    $record->update(['body_html' => 'Removed temporarily']);
    DB::rollBack();
    Storage::disk('public')->assertExists('editor/articles/keep.png');
    expect($record->fresh()->body_html)->toContain('keep.png');
});

it('cleans uploads inserted then removed before the first save without touching another open form', function (): void {
    Storage::disk('public')->put('editor/articles/discard.png', 'discard');
    Storage::disk('public')->put('editor/articles/another-form.png', 'keep');
    app(MediaLifecycle::class)->rememberPending('first-form', 'editor/articles/discard.png');
    app(MediaLifecycle::class)->rememberPending('second-form', 'editor/articles/another-form.png');
    Article::factory()->create(['body_html' => '<p>No images</p>']);
    app(MediaLifecycle::class)->finishForm('first-form');
    Storage::disk('public')->assertMissing('editor/articles/discard.png');
    Storage::disk('public')->assertExists('editor/articles/another-form.png');
});

it('deletes gallery photos with their parent and restores only the children deleted in that operation', function (): void {
    $gallery = Gallery::factory()->create();
    Storage::disk('public')->put('content/gallery/first.png', 'first');
    Storage::disk('public')->put('content/gallery/earlier.png', 'earlier');
    $first = GalleryPhoto::factory()->create(['gallery_id' => $gallery->id, 'photo_path' => 'content/gallery/first.png']);
    $earlier = GalleryPhoto::factory()->create(['gallery_id' => $gallery->id, 'photo_path' => 'content/gallery/earlier.png']);
    $earlier->delete();
    $gallery->delete();
    expect($first->fresh()->trashed())->toBeTrue();
    Storage::disk('public')->assertMissing('content/gallery/first.png');
    $gallery->restore();
    expect($first->fresh()->trashed())->toBeFalse()->and($earlier->fresh()->trashed())->toBeTrue();
    Storage::disk('public')->assertExists('content/gallery/first.png');
});

it('never follows external URLs or traversal paths when removing images', function (): void {
    Storage::disk('public')->put('editor/articles/protected.png', 'protected');
    $record = Article::factory()->create(['body_html' => '<img src="https://other.example/storage/editor/articles/protected.png"><img data-id="editor/../../private.txt">']);
    $record->delete();
    Storage::disk('public')->assertExists('editor/articles/protected.png');
    expect(app(MediaLifecycle::class)->normalize('/storage/editor/%2e%2e/private.txt'))->toBeNull();
});

it('removes an image uploaded through the real editor when it is discarded before saving', function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $record = Article::factory()->create(['title' => 'Artigo', 'body_html' => '<p>Texto</p>']);
    $component = Livewire\Livewire::test(EditArticle::class, ['record' => $record->id]);
    $editor = $component->instance()->getSchema('form')->getComponentByStatePath('body_html');
    Storage::disk('local')->put('livewire-tmp/inline.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true));
    $file = new TemporaryUploadedFile('inline.png', 'local');
    $path = $editor->saveUploadedFileAttachment($file);
    Storage::disk('public')->assertExists($path);
    $component->fillForm(['body_html' => '<p>Texto sem a imagem</p>'])->call('save')->assertHasNoFormErrors();
    Storage::disk('public')->assertMissing($path);
});

it('removes the cover through the real file field after saving the form', function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    Storage::disk('public')->put('content/articles/removed.png', 'cover');
    $record = Article::factory()->create(['title' => 'Artigo', 'cover_path' => 'content/articles/removed.png']);
    Livewire\Livewire::test(EditArticle::class, ['record' => $record->id])
        ->fillForm(['cover_path' => []])->call('save')->assertHasNoFormErrors();
    Storage::disk('public')->assertMissing('content/articles/removed.png');
    expect($record->fresh()->cover_path)->toBeNull();
});

it('removes a deleted user photo while preserving authored content and permits recovery', function (): void {
    Storage::disk('public')->put('content/users/avatar.png', 'avatar');
    $user = User::factory()->create(['cover_path' => 'content/users/avatar.png']);
    $article = Article::factory()->create(['created_by' => $user->id]);
    $user->delete();
    Storage::disk('public')->assertMissing('content/users/avatar.png');
    expect(Article::find($article->id)->created_by)->toBe($user->id)->and(User::find($user->id))->toBeNull();
    $user->restore();
    Storage::disk('public')->assertExists('content/users/avatar.png');
});
