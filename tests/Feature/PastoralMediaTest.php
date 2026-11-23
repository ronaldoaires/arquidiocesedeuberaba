<?php

use App\Filament\Resources\PastoralDownloads\PastoralDownloadResource;
use App\Filament\Resources\PastoralGalleries\PastoralGalleryResource;
use App\Filament\Resources\PastoralGalleryPhotos\PastoralGalleryPhotoResource;
use App\Filament\Resources\PastoralGroups\Pages\EditPastoralGroup;
use App\Filament\Resources\PastoralGroups\PastoralGroupResource;
use App\Filament\Resources\PastoralGroups\RelationManagers\DownloadsRelationManager;
use App\Filament\Resources\PastoralGroups\RelationManagers\GalleriesRelationManager;
use App\Filament\Resources\PastoralGroups\RelationManagers\VideosRelationManager;
use App\Filament\Resources\PastoralVideos\PastoralVideoResource;
use App\Models\PastoralDownload;
use App\Models\PastoralGallery;
use App\Models\PastoralGalleryPhoto;
use App\Models\PastoralGroup;
use App\Models\PastoralVideo;
use App\Models\User;
use App\Services\YouTubeVideoMetadata;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Http::preventStrayRequests();
    config(['migration.record_visits' => true, 'media.youtube_metadata_enabled' => true]);
    Cache::flush();
});

function pastoralTestDownload(): PastoralDownload
{
    $group = PastoralGroup::factory()->create(['title' => 'Pastoral de teste', 'slug' => 'pastoral-teste', 'is_active' => true]);
    Storage::disk('public')->put('pastoral/document.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");

    return PastoralDownload::factory()->create(['title' => 'Documento pastoral', 'pastoral_group_id' => $group->id, 'file_path' => 'pastoral/document.pdf', 'type' => 'pdf'])->fresh();
}

function pastoralAdmin(): void
{
    test()->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
}

it('hides auxiliary pastoral menus and places the parent under media', function (): void {
    expect(PastoralGroupResource::getNavigationGroup())->toBe('Mídias');
    foreach ([PastoralDownloadResource::class, PastoralGalleryResource::class, PastoralVideoResource::class, PastoralGalleryPhotoResource::class] as $resource) {
        expect($resource::shouldRegisterNavigation())->toBeFalse();
    }
});

it('preserves file fields and timestamps while counting reads and downloads independently', function (): void {
    $record = pastoralTestDownload();
    $before = $record->getRawOriginal();
    expect($record->visits)->toBe(0)->and($record->downloads)->toBe(0);
    $this->get('/pastoral/pastoral-teste')->assertOk()->assertSee('data-file-preview', false)
        ->assertSee(route('site.pastoral-files.download', $record->id), false);
    expect($record->fresh()->visits)->toBe(0);
    $this->get(route('site.pastoral-files.preview', $record->id))->assertRedirect(route('media.show', ['path' => $record->file_path]));
    $this->get(route('media.show', ['path' => $record->file_path]))->assertOk();
    $this->withHeader('Range', 'bytes=0-9')->get(route('media.show', ['path' => $record->file_path]))->assertStatus(206);
    $this->flushHeaders();
    $response = $this->get(route('site.pastoral-files.download', $record->id))->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
    $record->refresh();
    expect($record->visits)->toBe(1)->and($record->downloads)->toBe(1);
    foreach ($before as $field => $value) {
        if (! in_array($field, ['visits', 'downloads'], true)) {
            expect($record->getRawOriginal($field))->toBe($value);
        }
    }
});

it('does not count HEAD range disabled or missing-file accesses', function (): void {
    $record = pastoralTestDownload();
    $this->head(route('site.pastoral-files.preview', $record->id))->assertRedirect();
    $this->head(route('site.pastoral-files.download', $record->id))->assertOk();
    $this->withHeader('Range', 'bytes=0-9')->get(route('site.pastoral-files.download', $record->id))->assertStatus(206);
    $this->flushHeaders();
    config(['migration.record_visits' => false]);
    $this->get(route('site.pastoral-files.download', $record->id))->assertOk();
    config(['migration.record_visits' => true]);
    Storage::disk('public')->delete($record->file_path);
    $this->get(route('site.pastoral-files.download', $record->id))->assertNotFound();
    expect($record->fresh()->visits)->toBe(0)->and($record->fresh()->downloads)->toBe(0);
});

it('rejects files whose parent is inactive deleted or missing', function (): void {
    $record = pastoralTestDownload();
    $record->pastoralGroup->update(['is_active' => false]);
    foreach (['preview', 'download'] as $action) {
        $this->get(route('site.pastoral-files.'.$action, $record->id))->assertNotFound();
    }
    expect($record->fresh()->visits)->toBe(0)->and($record->fresh()->downloads)->toBe(0);
    $record->pastoralGroup->delete();
    $this->get(route('site.pastoral-files.download', $record->id))->assertNotFound();
});

it('shows and filters file counters without adding editable fields to the parent form', function (): void {
    pastoralAdmin();
    $record = pastoralTestDownload();
    DB::table('pastoral_downloads')->where('id', $record->id)->update(['visits' => 10, 'downloads' => 4]);
    Livewire::test(DownloadsRelationManager::class, ['ownerRecord' => $record->pastoralGroup, 'pageClass' => EditPastoralGroup::class])
        ->assertTableColumnStateSet('visits', 10, $record)->assertTableColumnStateSet('downloads', 4, $record)
        ->filterTable('visits', ['minimum' => 11])->assertCanNotSeeTableRecords([$record])
        ->filterTable('visits', ['maximum' => 10])->assertCanSeeTableRecords([$record])
        ->mountAction(TestAction::make('create')->table())->assertFormFieldDoesNotExist('visits')->assertFormFieldDoesNotExist('downloads');
});

it('uploads photos from the pastoral gallery row and shows them on the site without a gallery cover', function (): void {
    pastoralAdmin();
    $group = PastoralGroup::factory()->create(['slug' => 'pastoral-com-galeria', 'is_active' => true]);
    $gallery = PastoralGallery::factory()->create([
        'pastoral_group_id' => $group->id,
        'title' => 'Celebração',
        'cover_path' => null,
        'is_active' => true,
    ]);
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);

    Livewire::test(GalleriesRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditPastoralGroup::class])
        ->callAction(TestAction::make('addPhotos')->table($gallery), data: [
            'photo_path' => [
                UploadedFile::fake()->createWithContent('primeira.png', $bytes),
                UploadedFile::fake()->createWithContent('segunda.png', $bytes),
            ],
            'is_active' => true,
        ])->assertHasNoFormErrors()->assertNoRedirect()
        ->assertTableColumnStateSet('photos_count', 2, $gallery);

    $photos = PastoralGalleryPhoto::where('gallery_id', $gallery->id)->orderBy('id')->get();
    expect($photos)->toHaveCount(2);
    foreach ($photos as $photo) {
        expect($photo->pastoral_group_id)->toBe($group->id);
        Storage::disk('public')->assertExists($photo->photo_path);
    }

    $this->get('/pastoral/pastoral-com-galeria')->assertOk()
        ->assertSee('Celebração')
        ->assertSee('data-gallery="pastoral-'.$gallery->id.'"', false)
        ->assertSee(url('storage/'.$photos->first()->photo_path), false)
        ->assertDontSee(url('storage/'.$photos->last()->photo_path), false)
        ->assertSee(route('site.pastoral-gallery.photos', ['slug' => $group->slug, 'gallery' => $gallery->id]), false);

    $this->getJson(route('site.pastoral-gallery.photos', ['slug' => $group->slug, 'gallery' => $gallery->id]))
        ->assertOk()->assertJsonCount(2, 'photos')
        ->assertJsonPath('photos.1.url', url('storage/'.$photos->last()->photo_path));
});

it('loads pastoral gallery photos in bounded batches and excludes unpublished photos', function (): void {
    $group = PastoralGroup::factory()->create(['slug' => 'pastoral-muitas-fotos', 'is_active' => true]);
    $gallery = PastoralGallery::factory()->create(['pastoral_group_id' => $group->id, 'title' => 'Álbum', 'cover_path' => null, 'is_active' => true]);
    for ($number = 1; $number <= 25; $number++) {
        PastoralGalleryPhoto::factory()->create([
            'gallery_id' => $gallery->id,
            'pastoral_group_id' => $group->id,
            'photo_path' => 'content/pastoral_gallery_photos/foto-'.$number.'.jpg',
            'is_active' => true,
        ]);
    }
    PastoralGalleryPhoto::factory()->create([
        'gallery_id' => $gallery->id,
        'pastoral_group_id' => $group->id,
        'photo_path' => 'content/pastoral_gallery_photos/privada.jpg',
        'is_active' => false,
    ]);

    $feedUrl = route('site.pastoral-gallery.photos', ['slug' => $group->slug, 'gallery' => $gallery->id]);
    $this->get('/pastoral/pastoral-muitas-fotos')->assertOk()
        ->assertSee('foto-1.jpg')->assertDontSee('foto-25.jpg')->assertDontSee('privada.jpg');

    $firstPage = $this->getJson($feedUrl)->assertOk()->assertJsonCount(24, 'photos')->json();
    expect($firstPage['next'])->not->toBeNull();
    $this->getJson($firstPage['next'])->assertOk()->assertJsonCount(1, 'photos')
        ->assertJsonPath('photos.0.url', url('storage/content/pastoral_gallery_photos/foto-25.jpg'))
        ->assertJsonPath('next', null);

    $gallery->update(['is_active' => false]);
    $this->getJson($feedUrl)->assertNotFound();
});

it('fetches deduplicated YouTube titles in batches and serves subsequent requests from cache', function (): void {
    Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Título automático & original', 'html' => '<script>bad()</script>'])]);
    $service = app(YouTubeVideoMetadata::class);
    $service->prime(['abcdefghijk', 'abcdefghijk', 'lmnopqrstuv']);
    expect($service->get('abcdefghijk')['title'])->toBe('Título automático & original')
        ->and($service->get('abcdefghijk')['thumbnail'])->toBe('https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg');
    $service->prime(['abcdefghijk', 'lmnopqrstuv']);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://www.youtube.com/oembed') && $request['format'] === 'json');
});

it('uses a short failure cache and retains previously fetched titles during outages', function (): void {
    Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Título preservado'])]);
    $service = app(YouTubeVideoMetadata::class);
    $service->get('abcdefghijk');
    $this->travel(25)->hours();
    Http::fake(['www.youtube.com/oembed*' => Http::response([], 503)]);
    expect($service->get('abcdefghijk')['title'])->toBe('Título preservado');
    $service->get('abcdefghijk');
    Http::assertSentCount(1);
    $this->travelBack();
});

it('never fetches invalid video identifiers or renders provider HTML', function (): void {
    $service = app(YouTubeVideoMetadata::class);
    foreach ([null, 'short', '../12345678', 'abcdefghij<script>'] as $id) {
        expect($service->get($id)['thumbnail'])->toBeNull();
    }
    Http::assertNothingSent();
});

it('shows cached title and thumbnail with a watch action while keeping ID-only video forms', function (): void {
    pastoralAdmin();
    Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Encontro pastoral'])]);
    $group = PastoralGroup::factory()->create(['title' => 'Pastoral', 'is_active' => true]);
    $video = PastoralVideo::factory()->create(['pastoral_group_id' => $group->id, 'video_id' => 'abcdefghijk', 'is_active' => true]);
    $component = Livewire::test(VideosRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditPastoralGroup::class])
        ->assertTableColumnStateSet('title', 'Encontro pastoral', $video)
        ->assertTableColumnStateSet('cover_path', 'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg', $video)
        ->mountAction(TestAction::make('watch')->table($video))->assertActionMounted(TestAction::make('watch')->table($video))->assertNoRedirect();
    expect($component->instance()->getMountedAction()->getModalContent()->render())->toContain('https://www.youtube-nocookie.com/embed/abcdefghijk');
    $component->call('unmountAction')->mountAction(TestAction::make('create')->table())->assertFormFieldExists('video_id')->assertFormFieldDoesNotExist('title')->assertFormFieldDoesNotExist('cover_path');
    expect($video->fresh()->title)->toBeNull()->and($video->fresh()->cover_path)->toBeNull();
    Http::assertSentCount(1);
});

it('opens a pastoral video in the global dialog with its cached title', function (): void {
    Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Vídeo para assistir'])]);
    $group = PastoralGroup::factory()->create(['title' => 'Pastoral', 'slug' => 'pastoral', 'is_active' => true]);
    PastoralVideo::factory()->create(['pastoral_group_id' => $group->id, 'video_id' => 'abcdefghijk', 'is_active' => true]);
    PastoralVideo::factory()->create(['pastoral_group_id' => $group->id, 'video_id' => 'lmnopqrstuv', 'title' => 'Vídeo inativo', 'is_active' => false]);
    $this->get('/pastoral/pastoral')->assertOk()->assertSee('Vídeo para assistir')->assertSee('data-video', false)
        ->assertSee('id="video-dialog"', false)
        ->assertSee('https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg', false)->assertDontSee('Vídeo inativo');
    $this->get('/pastoral/pastoral')->assertOk();
    Http::assertSentCount(1);
});

it('paginates pastoral videos and downloads independently', function (): void {
    config(['media.youtube_metadata_enabled' => false]);
    $group = PastoralGroup::factory()->create(['slug' => 'pastoral-paginada', 'is_active' => true]);

    for ($number = 1; $number <= 9; $number++) {
        PastoralVideo::factory()->create([
            'pastoral_group_id' => $group->id,
            'video_id' => sprintf('video%06d', $number),
            'title' => 'Vídeo '.$number,
            'is_active' => true,
        ]);
    }

    for ($number = 1; $number <= 12; $number++) {
        PastoralDownload::factory()->create([
            'pastoral_group_id' => $group->id,
            'title' => 'Download '.$number,
        ]);
    }

    $this->get('/pastoral/pastoral-paginada')->assertOk()
        ->assertSee('Vídeo 9')->assertDontSee('Vídeo 1')
        ->assertSee('Download 12')->assertDontSee('Download 1</h3>', false)
        ->assertSee('videos_page=2', false)->assertSee('downloads_page=2', false);

    $this->get('/pastoral/pastoral-paginada?videos_page=2&downloads_page=2')->assertOk()
        ->assertSee('Vídeo 1')->assertDontSee('Vídeo 9')
        ->assertSee('Download 1')->assertDontSee('Download 12')
        ->assertSee('videos_page=2', false)->assertSee('downloads_page=2', false);
});

it('reuses resolved metadata within the request without repeating database cache lookups', function (): void {
    config(['cache.default' => 'database']);
    Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Título em cache'])]);
    $service = app(YouTubeVideoMetadata::class);
    $service->prime(['abcdefghijk']);
    expect(app(YouTubeVideoMetadata::class))->toBe($service);
    DB::enableQueryLog();
    DB::flushQueryLog();
    expect($service->get('abcdefghijk')['title'])->toBe('Título em cache');
    expect($service->get('abcdefghijk')['thumbnail'])->toBe('https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg');
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
    Http::assertSentCount(1);
});
