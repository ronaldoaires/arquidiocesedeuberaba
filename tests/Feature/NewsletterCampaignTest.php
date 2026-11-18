<?php

use App\Filament\Clusters\Newsletter;
use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use App\Filament\Resources\NewsletterCampaigns\Pages\CreateNewsletterCampaign;
use App\Filament\Resources\NewsletterCampaigns\Pages\EditNewsletterCampaign;
use App\Filament\Resources\NewsletterCampaigns\Pages\ListNewsletterCampaigns;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Livewire\Admin\NewsletterContentPicker;
use App\Mail\NewsletterCampaignMail;
use App\Models\Article;
use App\Models\Event;
use App\Models\Institute;
use App\Models\NewsItem;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Newspaper;
use App\Models\Parish;
use App\Models\User;
use App\Services\Newsletter\CampaignCatalog;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
});

it('opens campaigns first and links both newsletter lists without a menu icon', function (): void {
    expect(Newsletter::getNavigationIcon())->toBeNull();
    $this->get('/admin/newsletter')->assertRedirect(NewsletterCampaignResource::getUrl());
    $this->get(NewsletterCampaignResource::getUrl())->assertOk()->assertSee('Nova campanha')->assertSee(NewsletterSubscriberResource::getUrl());
    $this->get(NewsletterSubscriberResource::getUrl())->assertOk()->assertSee(NewsletterCampaignResource::getUrl());
    $this->get(NewsletterCampaignResource::getUrl('create'))->assertOk()->assertSee('Adicionar conteúdo do site');
});

it('saves an editorial draft with an attachment and edits its mixed content order', function (): void {
    Storage::fake('local');
    $news = NewsItem::factory()->create(['created_at' => now()->subDay(), 'is_active' => true]);
    $article = Article::factory()->create(['is_active' => true]);
    $event = Event::factory()->create(['is_active' => true, 'location' => 'Catedral']);
    $keys = ['news:'.$news->id, 'events:'.$event->id, 'articles:'.$article->id];
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jCioAAAAASUVORK5CYII=');
    Livewire::test(CreateNewsletterCampaign::class)->fillForm([
        'subject' => 'Boletim da semana', 'body_html' => '<p>Uma mensagem da Arquidiocese.</p>',
        'content_keys' => $keys, 'attachments' => [UploadedFile::fake()->createWithContent('convite.png', $png)],
    ])->call('create')->assertHasNoFormErrors()->assertRedirect(NewsletterCampaignResource::getUrl());
    $campaign = NewsletterCampaign::sole();
    $path = $campaign->attachments[0];
    Storage::disk('local')->assertExists($path);
    expect($campaign->content_keys)->toBe($keys)->and($campaign->attachment_names[$path])->toBe('convite.png');
    $this->get('/storage/'.$path)->assertNotFound();
    Livewire::test(EditNewsletterCampaign::class, ['record' => $campaign->id])
        ->fillForm(['content_keys' => array_reverse($keys)])->call('save')->assertHasNoFormErrors();
    $campaign->refresh();
    $message = new NewsletterCampaignMail($campaign);
    expect($message->render())->toContain('Uma mensagem da Arquidiocese.', 'Catedral', $event->public_url)
        ->and($message->content()->with['items']->keys()->all())->toBe(array_reverse($keys));
    Mail::to(auth()->user()->email)->send(new NewsletterCampaignMail($campaign));
    $attachments = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getAttachments();
    expect($attachments)->toHaveCount(1)->and($attachments[0]->getFilename())->toBe('convite.png')
        ->and($attachments[0]->getBody())->toBe($png);
});

it('supports a plain announcement, preview, duplication and a test addressed only to the admin', function (): void {
    Livewire::test(CreateNewsletterCampaign::class)->fillForm(['subject' => 'Aviso', 'body_html' => '<p>Comunicado da Cúria.</p>'])
        ->call('create')->assertHasNoFormErrors();
    $campaign = NewsletterCampaign::sole();
    NewsletterSubscriber::factory()->create(['email' => 'reader@example.test', 'status' => 'active']);
    $list = Livewire::test(ListNewsletterCampaigns::class);
    $list->mountAction(TestAction::make('preview')->table($campaign));
    expect($list->instance()->getMountedAction()->getModalContent()->render())->toContain('Prévia da campanha', 'Comunicado da Cúria.');
    $list->unmountAction();
    $list->callAction(TestAction::make('replicate')->table($campaign));
    $copy = NewsletterCampaign::where('id', '!=', $campaign->id)->sole();
    expect($copy->subject)->toBe('Cópia de Aviso')->and($copy->body_html)->toBe($campaign->body_html);

    $list->callAction(TestAction::make('sendTest')->table($campaign))->assertNotified();
    $sent = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    expect($sent->getSubject())->toBe('Aviso')->and($sent->getTo())->toHaveCount(1)
        ->and($sent->getTo()[0]->getAddress())->toBe(auth()->user()->email)
        ->and($sent->getHtmlBody())->toContain('Comunicado da Cúria.');
});

it('keeps picker choices across pages and tabs, supports cancel and reorders applied items', function (): void {
    $older = NewsItem::factory()->create(['title' => 'Primeira edição', 'created_at' => now()->subYear(), 'is_active' => true]);
    NewsItem::factory()->count(12)->create(['created_at' => now()->subDay(), 'is_active' => true]);
    $article = Article::factory()->create(['is_active' => true]);
    $picker = Livewire::test(NewsletterContentPicker::class)->call('open')->assertDontSee('Primeira edição')
        ->call('gotoPage', 2, 'contentPage')->assertSee('Primeira edição')
        ->set('search', 'Primeira edição')->assertSee('Primeira edição')
        ->set('draft', ['news:'.$older->id])->call('selectType', 'articles')->assertSee($article->title)
        ->set('draft', ['news:'.$older->id, 'articles:'.$article->id])->call('applySelection')
        ->call('move', 1, -1)->assertSet('value', ['articles:'.$article->id, 'news:'.$older->id]);
    $picker->call('open')->set('draft', [])->call('close')->assertSet('value', ['articles:'.$article->id, 'news:'.$older->id])
        ->call('remove', 0)->assertSet('value', ['news:'.$older->id]);
});

it('prioritizes upcoming events and excludes unpublished or scheduled content', function (): void {
    $past = Event::factory()->create(['starts_on' => now()->subDay(), 'is_active' => true]);
    $next = Event::factory()->create(['starts_on' => now()->addDay(), 'is_active' => true]);
    $later = Event::factory()->create(['starts_on' => now()->addMonth(), 'is_active' => true]);
    $unpublished = Article::factory()->create(['is_active' => false]);
    $scheduled = NewsItem::factory()->create(['is_active' => true, 'created_at' => now()->addMonth()]);
    $catalog = app(CampaignCatalog::class);
    expect($catalog->browse('events', '')->pluck('id')->all())->toBe([$next->id, $later->id, $past->id])
        ->and($catalog->resolve(['news:'.$scheduled->id, 'articles:'.$unpublished->id]))->toBeEmpty();
    Livewire::test(CreateNewsletterCampaign::class)->fillForm([
        'subject' => 'Edição', 'body_html' => '<p>Texto</p>', 'content_keys' => ['news:'.$scheduled->id],
    ])->call('create')->assertHasFormErrors(['content_keys']);
    expect(NewsletterCampaign::count())->toBe(0);
});

it('flags a saved item or file that becomes unavailable before preview and test delivery', function (): void {
    $article = Article::factory()->create(['is_active' => true]);
    $campaign = NewsletterCampaign::factory()->create(['content_keys' => ['articles:'.$article->id]]);
    $article->update(['is_active' => false]);
    $list = Livewire::test(ListNewsletterCampaigns::class);
    $list->mountAction(TestAction::make('preview')->table($campaign));
    expect($list->instance()->getMountedAction()->getModalContent()->render())->toContain('Um conteúdo deixou de estar publicado.');
    $list->unmountAction()->callAction(TestAction::make('sendTest')->table($campaign));
    expect(Mail::mailer()->getSymfonyTransport()->messages())->toHaveCount(0);
    Storage::fake('local');
    $campaign->update(['content_keys' => [], 'attachments' => ['newsletter/attachments/missing.pdf']]);
    expect(fn () => (new NewsletterCampaignMail($campaign))->render())->toThrow(ValidationException::class);
});

it('restricts the campaign editor and catalog to active administrators', function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 1]));
    $this->get(NewsletterCampaignResource::getUrl())->assertForbidden();
    $this->get(NewsletterCampaignResource::getUrl('create'))->assertForbidden();
    Livewire::test(NewsletterContentPicker::class)->assertForbidden();
});

it('preserves shared private files when a duplicated draft removes its attachment', function (): void {
    Storage::fake('local');
    $path = 'newsletter/attachments/edition.pdf';
    Storage::disk('local')->put($path, 'newsletter PDF');
    $source = NewsletterCampaign::factory()->create(['attachments' => [$path], 'attachment_names' => [$path => 'Boletim.pdf']]);
    Livewire::test(ListNewsletterCampaigns::class)->callAction(TestAction::make('replicate')->table($source));
    $copy = NewsletterCampaign::whereKeyNot($source->id)->sole();
    expect($copy->attachments)->toBe([$path]);
    Livewire::test(EditNewsletterCampaign::class, ['record' => $copy->id])
        ->fillForm(['attachments' => []])->call('save')->assertHasNoFormErrors();
    expect($copy->fresh()->attachments)->toBeEmpty();
    Storage::disk('local')->assertExists($path);
    $message = new NewsletterCampaignMail($source);
    expect($message->attachments())->toHaveCount(1);
});

it('selects newspapers parishes and institutes together and renders their public links in order', function (): void {
    $edition = Newspaper::factory()->create(['title' => 'Correio Católico', 'edition' => '425', 'year' => 2026, 'month' => 10]);
    Newspaper::factory()->create(['title' => 'Edição anterior', 'year' => 2025, 'month' => 12]);
    $parish = Parish::factory()->create(['name' => 'Paróquia São José']);
    Parish::factory()->create(['name' => 'Paróquia São José inativa', 'is_active' => false]);
    $institute = Institute::factory()->create(['title' => 'Instituto São Bento']);
    $news = NewsItem::factory()->create(['title' => 'Notícia da semana', 'created_at' => now()->subDay()]);
    $keys = ['news:'.$news->id, 'newspapers:'.$edition->id, 'parishes:'.$parish->id, 'institutes:'.$institute->id];

    $picker = Livewire::test(NewsletterContentPicker::class)->call('open')->set('draft', [$keys[0]])
        ->call('selectType', 'newspapers')->assertViewHas('results', fn ($results): bool => $results->pluck('title')->all() === ['Correio Católico', 'Edição anterior'])
        ->set('search', 'Correio')->assertSee('outubro/2026')->assertSee('Edição 425')->assertDontSee('Edição anterior')
        ->set('draft', array_slice($keys, 0, 2))->call('selectType', 'parishes')->set('search', 'José')
        ->assertSee($parish->name)->assertDontSee('Paróquia São José inativa')
        ->set('draft', array_slice($keys, 0, 3))->call('selectType', 'institutes')->set('search', 'Bento')
        ->assertSee($institute->title)->set('draft', $keys)->call('applySelection')->assertSet('value', $keys);

    Livewire::test(CreateNewsletterCampaign::class)->fillForm([
        'subject' => 'Vida da Arquidiocese', 'body_html' => '<p>Destaques da semana.</p>', 'content_keys' => $picker->get('value'),
    ])->call('create')->assertHasNoFormErrors();
    $campaign = NewsletterCampaign::sole();
    $list = Livewire::test(ListNewsletterCampaigns::class);
    $list->mountAction(TestAction::make('preview')->table($campaign));
    expect($list->instance()->getMountedAction()->getModalContent()->render())
        ->toContain('Ver edição', 'Ver paróquia', 'Ver instituto', 'outubro/2026');
    $list->unmountAction()->callAction(TestAction::make('sendTest')->table($campaign));
    $message = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getHtmlBody();
    $document = new DOMDocument;
    $document->loadHTML($message);
    $links = (new DOMXPath($document))->query('//a/@href');
    expect(array_map(fn ($link) => $link->nodeValue, iterator_to_array($links)))
        ->toBe([$news->public_url, $edition->public_url, $parish->public_url, $institute->public_url]);
});
