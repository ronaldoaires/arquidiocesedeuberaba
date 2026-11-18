<?php

use App\Services\Migration\CanonicalLegacyMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('shares cover paths, clears missing covers and changes only local URLs inside editorial markup', function () {
    $old = '/uploads/summernote/santo.jpg?x=1&y=2#foto';
    $field = ['table' => 'artigo', 'id' => 7, 'target_field' => 'body_html', 'url' => $old, 'destination' => 'editor/articles/santo.jpg'];
    $cover = ['table' => 'artigo', 'id' => 7, 'target_field' => 'cover_path', 'destination' => 'content/articles/comum.jpg'];
    $media = new CanonicalLegacyMedia([], [$field, $cover, array_replace($cover, ['id' => 8]), ['table' => 'artigo', 'id' => 9, 'target_field' => 'cover_path', 'status' => 'missing']]);
    expect($media->value('artigo', '7', 'cover_path', 'comum.jpg'))->toBe($media->value('artigo', '8', 'cover_path', 'comum.jpg'))
        ->and($media->value('artigo', '9', 'cover_path', 'ausente.jpg'))->toBeNull();
    $html = '<p>'.$old.'</p><img src="/uploads/summernote/santo.jpg?x=1&amp;y=2#foto"><a href="https://external.example'.$old.'">externo</a>';
    expect($media->value('artigo', '7', 'body_html', $html))->toBe(str_replace('src="/uploads/summernote/', 'src="/storage/editor/articles/', $html));
});

it('serves old public links using available canonical media after cleanup', function () {
    Storage::fake('public');
    $file = 'editor/news/informe.txt';
    Storage::disk('public')->put($file, 'ok');
    DB::table('media_assets')->insert(['path' => $file, 'legacy_path' => 'uploads/Arquivos/informe.txt', 'state' => 'available', 'bytes' => 2, 'sha256' => hash('sha256', 'ok')]);
    $this->get('/uploads/Arquivos/informe.txt')->assertOk();
    $this->get('/storage/legacy/uploads/Arquivos/informe.txt')->assertOk();
    $this->get('/uploads/Arquivos/ausente.txt')->assertNotFound();
});

it('retains a recovered source on the next rebuild using the verified catalog entry', function () {
    Storage::fake('public');
    $path = 'content/articles/recuperada.jpg';
    $source = 'uploads/imagens/recuperada.jpg';
    Storage::disk('public')->put($path, 'bytes');
    DB::table('media_assets')->insert(['path' => $path, 'legacy_path' => $source, 'state' => 'available', 'bytes' => 5, 'sha256' => hash('sha256', 'bytes')]);
    $plan = CanonicalLegacyMedia::includeExistingMedia(['summary' => [], 'references' => [
        ['status' => 'missing', 'source' => $source], ['status' => 'missing', 'source' => 'uploads/other/recuperada.jpg'],
    ]]);
    expect($plan['references'][0])->toMatchArray(['status' => 'available_in_canonical_media', 'recovery_path' => $path])
        ->and($plan['references'][1]['status'])->toBe('missing');
});
