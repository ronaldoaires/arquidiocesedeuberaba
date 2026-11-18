<?php

use Illuminate\Support\Facades\Validator;

it('renders the public context in Brazilian Portuguese', function (): void {
    $this->get(route('site.home'))->assertOk()->assertViewIs('site.home')
        ->assertSee('Arquidiocese de Uberaba')->assertSee('lang="pt-BR"', false);
    expect(config('app.locale'))->toBe('pt_BR');
});

it('keeps media route names independent from application contexts', function (): void {
    expect(route('media.show', ['path' => 'editor/image.png'], false))->toBe('/storage/editor/image.png')
        ->and(route('media.legacy', ['path' => 'summernote/image.png'], false))->toBe('/uploads/summernote/image.png')
        ->and(config('filesystems.links'))->toBe([]);
});

it('renders the admin login and validation feedback in Portuguese', function (): void {
    $this->get('/admin/login')->assertOk()->assertSee('Faça login')->assertSee('Senha');
    $validator = Validator::make([], ['name' => 'required']);
    expect($validator->errors()->first('name'))->toBe('O campo nome é obrigatório.');
});

it('renders missing pages in Portuguese', function (): void {
    $this->get('/missing-page')->assertNotFound()->assertSee('Página não encontrada');
});
