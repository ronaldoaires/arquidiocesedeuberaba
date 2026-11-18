<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

function sampleMediaImage(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
}

it('serves new and legacy media without a symbolic link', function (): void {
    Storage::disk('public')->put('editor/news/image.png', sampleMediaImage());
    Storage::disk('public')->put('legacy/uploads/summernote/image.png', sampleMediaImage());

    $this->get('/storage/editor/news/image.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('/uploads/summernote/image.png')->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->head('/storage/editor/news/image.png')->assertOk();
});

it('rejects missing files hidden paths executables and disguised images', function (string $path): void {
    Storage::disk('public')->put($path, '<?php echo "blocked";');

    $this->get('/storage/'.$path)->assertNotFound();
})->with(['editor/test.php', '.env', 'editor/.secret', 'editor/fake.png', 'editor/test.html', 'editor/test.svg']);

it('does not expose private storage through the public route', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('confidential.txt', 'private');

    $this->get('/storage/confidential.txt')->assertNotFound();
    $this->get('/storage/%2e%2e/private/confidential.txt')->assertNotFound();
    $this->get('/storage/missing.png')->assertNotFound();
});

it('supports conditional requests for public media', function (): void {
    Storage::disk('public')->put('editor/image.png', sampleMediaImage());
    $response = $this->get('/storage/editor/image.png')->assertOk();

    $this->withHeader('If-None-Match', $response->headers->get('ETag'))
        ->get('/storage/editor/image.png')->assertStatus(304);
});

it('delivers non-image documents as downloads', function (): void {
    Storage::disk('public')->put('editor/score.xml', '<score-partwise/>');

    $response = $this->get('/storage/editor/score.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('supports byte range requests for media', function (): void {
    Storage::disk('public')->put('editor/image.png', sampleMediaImage());

    $this->withHeader('Range', 'bytes=0-9')->get('/storage/editor/image.png')
        ->assertStatus(206)->assertHeader('Content-Length', '10');
});
