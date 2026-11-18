<?php

use App\Models\Article;
use App\Models\NewsItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preserves previous public URLs after slug edits without redirect chains', function (): void {
    $article = Article::factory()->create(['title' => 'Artigo', 'slug' => 'first-slug', 'is_active' => true]);
    $article->update(['slug' => 'second-slug']);
    $article->update(['slug' => 'final-slug']);
    $this->get('/artigo/first-slug')->assertRedirect('/artigo/final-slug')->assertStatus(301);
    $this->get('/artigo/second-slug')->assertRedirect('/artigo/final-slug')->assertStatus(301);
    $this->get('/artigo/final-slug')->assertOk();
});

it('publishes an XML sitemap without inactive or scheduled content', function (): void {
    Article::factory()->create(['title' => 'Publicado', 'slug' => 'public-article', 'is_active' => true]);
    NewsItem::factory()->create(['title' => 'Agendada', 'slug' => 'future-news', 'is_active' => true, 'created_at' => now()->addDay()]);
    $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('/artigo/public-article')->assertDontSee('/noticia/future-news');
});
