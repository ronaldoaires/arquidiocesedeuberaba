<?php

use App\Models\ClergyMember;
use App\Models\Gallery;
use App\Models\GalleryPhoto;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Parish;
use App\Models\ParishClergyAssignment;
use App\Support\HtmlContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('publishes active content while hiding inactive and scheduled news', function (): void {
    $published = NewsItem::factory()->create(['title' => 'Notícia publicada', 'slug' => 'published', 'body_html' => '<p>Conteúdo completo</p>', 'is_active' => true, 'created_at' => now()->subDay()]);
    NewsItem::factory()->create(['title' => 'Agendada', 'slug' => 'scheduled', 'body_html' => 'Texto', 'is_active' => true, 'created_at' => now()->addDay()]);
    NewsItem::factory()->create(['title' => 'Inativa', 'slug' => 'inactive', 'body_html' => 'Texto', 'is_active' => false]);

    $this->get('/noticia/published')->assertOk()->assertSee('Conteúdo completo');
    $this->get('/noticia/scheduled')->assertNotFound();
    $this->get('/noticia/inactive')->assertNotFound();
    $this->get('/noticias')->assertOk()->assertSee($published->title)->assertDontSee('Agendada');
});

it('renders page sections and parish assignments through actual relationships', function (): void {
    $page = Page::factory()->create(['title' => 'Página', 'slug' => 'page', 'is_active' => true]);
    PageSection::factory()->create(['page_id' => $page->id, 'title' => 'Complemento', 'body_html' => '<p>Detalhe preservado</p>', 'is_active' => true]);
    $parish = Parish::factory()->create(['name' => 'Paróquia', 'slug' => 'parish', 'is_active' => true]);
    $clergy = ClergyMember::factory()->create(['name' => 'Clérigo', 'slug' => 'clergy', 'is_active' => true]);
    ParishClergyAssignment::factory()->create(['parish_id' => $parish->id, 'clergy_member_id' => $clergy->id, 'role_text' => 'Pároco']);

    $this->get('/pagina/page')->assertOk()->assertSee('Detalhe preservado');
    $this->get('/paroquia/parish')->assertOk()->assertSee('Clérigo')->assertSee('Pároco');
    expect($parish->assignments()->first()->clergyMember->id)->toBe($clergy->id);
});

it('preserves image dimensions and legacy media URLs while removing executable markup', function (): void {
    $html = app(HtmlContent::class)->render('<p>Texto</p><img src="https://arquidiocesedeuberaba.com.br/uploads/summernote/a.jpg" style="width: 320px; height: 200px"><script>alert(1)</script>');
    expect($html)->toContain('/uploads/summernote/a.jpg')->toContain('width="320"')->toContain('Texto')->not->toContain('<script');
});

it('serves all public list and utility routes', function (string $path): void {
    $this->get($path)->assertOk();
})->with(['/', '/noticias', '/artigos-dom-paulo', '/paroquias', '/galerias', '/agenda', '/agenda/11/2026', '/correio-catolico', '/central-de-midias', '/videos', '/liturgia-diaria', '/contato', '/solicitar', '/search?q=teste']);

it('paginates only published photos of the selected album and renders thumbnails', function (): void {
    $disk = Storage::fake('public');
    $gallery = Gallery::factory()->create(['title' => 'Álbum', 'slug' => 'album', 'is_active' => true]);
    $other = Gallery::factory()->create(['title' => 'Outro', 'slug' => 'outro']);
    $photo = fn ($album, $file, $active = true) => GalleryPhoto::factory()->create([
        'gallery_id' => $album->id, 'title' => $file, 'photo_path' => 'content/gallery_photos/'.$file.'.jpg', 'is_active' => $active,
    ]);
    $photo($gallery, 'inativa', false);
    $photo($other, 'outro-album');
    foreach (range(1, 25) as $number) {
        $photo($gallery, 'foto-'.$number);
    }
    $disk->put('content/gallery_photos/thumbs/foto-1.jpg', 'thumb');

    $first = $this->get('/galeria/album')->assertOk()->assertSee('Fotos 1 a 24 de 25')
        ->assertSee('thumbs/foto-1.jpg')->assertSee('?page=2#fotos')->assertDontSee('foto-25.jpg')
        ->assertDontSee('outro-album.jpg')->assertDontSee('inativa.jpg');
    expect($first->viewData('related')['photos']->count())->toBe(24);
    $this->get('/galeria/album?page=2')->assertOk()->assertSee('Fotos 25 a 25 de 25')
        ->assertSee('foto-25.jpg')->assertDontSee('foto-1.jpg');
    $this->get('/galeria/album?page=3')->assertNotFound();
});
