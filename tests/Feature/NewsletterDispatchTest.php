<?php

use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use App\Filament\Resources\NewsletterCampaigns\Pages\ListNewsletterCampaigns;
use App\Filament\Resources\NewsletterCampaigns\Pages\NewsletterDeliveryHistory;
use App\Filament\Resources\NewsletterCampaigns\Pages\ReviewNewsletterCampaign;
use App\Jobs\SendNewsletterDelivery;
use App\Mail\NewsletterCampaignMail;
use App\Models\Article;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Newsletter\CampaignDispatch;
use App\Services\Newsletter\Subscriptions;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    config(['newsletter.batch_size' => 2]);
});

function confirmedNewsletterSubscriber(): NewsletterSubscriber
{
    return NewsletterSubscriber::factory()->create(['status' => 'active', 'verified_at' => now()]);
}

function queueNewsletterEdition(NewsletterCampaign $campaign): void
{
    $dispatch = app(CampaignDispatch::class);
    $dispatch->confirm($campaign->id, $dispatch->review($campaign)['fingerprint']);
    $campaign->refresh();
}

it('reviews and queues only confirmed recipients, staggers batches and makes duplicate confirmation harmless', function (): void {
    $campaign = NewsletterCampaign::factory()->create(['subject' => 'Edição de novembro']);
    confirmedNewsletterSubscriber();
    confirmedNewsletterSubscriber();
    confirmedNewsletterSubscriber();
    NewsletterSubscriber::factory()->create();
    NewsletterSubscriber::factory()->create(['status' => 'unsubscribed', 'verified_at' => now()]);
    NewsletterSubscriber::factory()->create(['status' => 'active', 'verified_at' => null]);

    $this->get(NewsletterCampaignResource::getUrl('review', ['record' => $campaign]))->assertOk()->assertSee('Confirmar envio');
    $page = Livewire::test(ReviewNewsletterCampaign::class, ['record' => $campaign->id])
        ->assertSet('recipientCount', 3)->assertSee('Modo de simulação')->assertSee('Edição de novembro');
    expect($campaign->deliveries()->count())->toBe(0);
    $token = $page->get('fingerprint');
    $page->call('confirm')->assertRedirect(NewsletterCampaignResource::getUrl('history', ['record' => $campaign]));
    app(CampaignDispatch::class)->confirm($campaign->id, $token);
    expect($campaign->deliveries()->count())->toBe(3)->and(DB::table('jobs')->count())->toBe(3);
    $delays = DB::table('jobs')->orderBy('id')->pluck('available_at');
    expect($delays[2] - $delays[0])->toBe(60);
    $campaign->refresh();
    $this->get(NewsletterCampaignResource::getUrl('edit', ['record' => $campaign]))->assertForbidden();
    $this->get(NewsletterCampaignResource::getUrl('history', ['record' => $campaign]))->assertOk()->assertSee('Na fila (3)');
    Livewire::test(ListNewsletterCampaigns::class)->callAction(TestAction::make('replicate')->table($campaign));
    $copy = NewsletterCampaign::whereKeyNot($campaign->id)->sole();
    expect($copy->dispatched_at)->toBeNull()->and($copy->dispatch_html)->toBeNull()->and($copy->deliveries()->count())->toBe(0);
});

it('requires a new review for changes and leaves empty audiences as drafts', function (): void {
    $campaign = NewsletterCampaign::factory()->create();
    $page = Livewire::test(ReviewNewsletterCampaign::class, ['record' => $campaign->id])->assertSet('recipientCount', 0);
    $page->call('confirm');
    expect($campaign->fresh()->dispatched_at)->toBeNull();
    confirmedNewsletterSubscriber();
    $page->call('confirm')->assertSet('recipientCount', 1);
    $campaign->update(['subject' => 'Assunto revisto']);
    $page->call('confirm')->assertSee('Assunto revisto');
    expect($campaign->fresh()->dispatched_at)->toBeNull()->and(DB::table('jobs')->count())->toBe(0);
    $page->call('confirm')->assertRedirect();
    expect($campaign->fresh()->dispatched_at)->not->toBeNull();
});

it('processes the frozen edition through the database worker and keeps each management link personal', function (): void {
    $first = confirmedNewsletterSubscriber();
    $second = confirmedNewsletterSubscriber();
    $article = Article::factory()->create(['title' => 'Título na confirmação']);
    $campaign = NewsletterCampaign::factory()->create(['content_keys' => ['articles:'.$article->id]]);
    queueNewsletterEdition($campaign);
    $article->update(['title' => 'Título posterior', 'is_active' => false]);
    expect((new NewsletterCampaignMail($campaign))->render())->toContain('Título na confirmação')->not->toContain('Título posterior');

    $this->artisan('queue:work', [
        'connection' => 'database', '--queue' => 'default,newsletter', '--stop-when-empty' => true,
        '--max-jobs' => 25, '--max-time' => 50, '--tries' => 3, '--timeout' => 40,
    ])->assertSuccessful();
    $messages = Mail::mailer()->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(2)->and($campaign->deliveries()->where('status', 'simulated')->count())->toBe(2)
        ->and($campaign->fresh()->completed_at)->not->toBeNull();
    $sent = $messages->first()->getOriginalMessage();
    expect($sent->getTo())->toHaveCount(1)->and($sent->getTo()[0]->getAddress())->toBe($first->email);
    $html = $sent->getHtmlBody();
    expect($html)->toContain('Título na confirmação', 'Cancelar inscrição', 'Atualizar meus dados', $first->link_key)->not->toContain($second->link_key);
    $document = new DOMDocument;
    $document->loadHTML($html);
    $cancel = (new DOMXPath($document))->query('//a[contains(@href,"/cancelar/")]')->item(0)->getAttribute('href');
    $this->travel(3)->days();
    $this->get($cancel)->assertOk();
    (new SendNewsletterDelivery($campaign->deliveries()->first()->id))->handle();
    expect($messages)->toHaveCount(2);
});

it('skips cancellations and later subscriptions even when the email is confirmed again', function (): void {
    $first = confirmedNewsletterSubscriber();
    $second = confirmedNewsletterSubscriber();
    $campaign = NewsletterCampaign::factory()->create();
    queueNewsletterEdition($campaign);
    $subscriptions = app(Subscriptions::class);
    $subscriptions->unsubscribe($first->link_key);
    $subscriptions->unsubscribe($second->link_key);
    $subscriptions->confirm($second->fresh()->link_key, []);
    confirmedNewsletterSubscriber();
    foreach ($campaign->deliveries as $delivery) {
        (new SendNewsletterDelivery($delivery->id))->handle();
    }
    expect($campaign->deliveries()->where('status', 'skipped')->count())->toBe(2)
        ->and(Mail::mailer()->getSymfonyTransport()->messages())->toBeEmpty()->and($campaign->fresh()->completed_at)->not->toBeNull();
});

it('can retry an attachment failure while retaining the approved bytes and filename', function (): void {
    Storage::fake('local');
    $path = 'newsletter/attachments/boletim.pdf';
    Storage::disk('local')->put($path, 'conteúdo aprovado');
    confirmedNewsletterSubscriber();
    $campaign = NewsletterCampaign::factory()->create(['attachments' => [$path], 'attachment_names' => [$path => 'Boletim.pdf']]);
    queueNewsletterEdition($campaign);
    $delivery = $campaign->deliveries()->sole();
    Storage::disk('local')->put($path, 'arquivo diferente');
    (new SendNewsletterDelivery($delivery->id))->handle();
    expect($delivery->fresh()->status)->toBe('failed')->and($campaign->fresh()->completed_at)->not->toBeNull();
    Storage::disk('local')->put($path, 'conteúdo aprovado');
    Livewire::test(NewsletterDeliveryHistory::class, ['record' => $campaign->id])->callAction(TestAction::make('retry')->table($delivery));
    expect($delivery->fresh()->status)->toBe('pending')->and($campaign->fresh()->completed_at)->toBeNull();
    (new SendNewsletterDelivery($delivery->id))->handle();
    $attachment = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getAttachments()[0];
    expect($attachment->getBody())->toBe('conteúdo aprovado')->and($attachment->getFilename())->toBe('Boletim.pdf');
});

it('records an interrupted handoff as uncertain without automatically sending again', function (): void {
    confirmedNewsletterSubscriber();
    $campaign = NewsletterCampaign::factory()->create();
    queueNewsletterEdition($campaign);
    $delivery = $campaign->deliveries()->sole();
    $delivery->forceFill(['status' => 'sending'])->save();
    $job = new SendNewsletterDelivery($delivery->id);
    $job->failed(null);
    $job->handle();
    app(CampaignDispatch::class)->retryFailed($delivery);
    expect($delivery->fresh()->status)->toBe('unknown')->and(Mail::mailer()->getSymfonyTransport()->messages())->toBeEmpty()
        ->and($campaign->fresh()->completed_at)->not->toBeNull();
});

it('protects review and delivery history from nonadministrators', function (): void {
    confirmedNewsletterSubscriber();
    $campaign = NewsletterCampaign::factory()->create();
    $this->actingAs(User::factory()->create(['legacy_level' => 1, 'is_active' => true]));
    $this->get(NewsletterCampaignResource::getUrl('review', ['record' => $campaign]))->assertForbidden();
    queueNewsletterEdition($campaign);
    $this->get(NewsletterCampaignResource::getUrl('history', ['record' => $campaign]))->assertForbidden();
});

it('handles SMTP acceptance, temporary refusals and uncertain responses individually', function (): void {
    config(['mail.default' => 'delivery-test', 'mail.mailers.delivery-test' => ['transport' => 'delivery-test']]);
    $transport = Mockery::mock(TransportInterface::class);
    Mail::extend('delivery-test', fn () => $transport);
    $transport->shouldReceive('send')->once()->andThrow(new UnexpectedResponseException('Temporary refusal', 451));
    $transport->shouldReceive('send')->once()->andReturnUsing(fn ($message, $envelope) => new SentMessage($message, $envelope ?? Envelope::create($message)));
    $transport->shouldReceive('send')->once()->andThrow(new UnexpectedResponseException('Empty response', 0));
    $transport->shouldReceive('send')->once()->andThrow(new UnexpectedResponseException('Refused', 550));
    confirmedNewsletterSubscriber();
    confirmedNewsletterSubscriber();
    confirmedNewsletterSubscriber();
    $campaign = NewsletterCampaign::factory()->create();
    queueNewsletterEdition($campaign);
    [$first, $second, $third] = $campaign->deliveries()->orderBy('id')->get()->all();

    $retry = (new SendNewsletterDelivery($first->id))->withFakeQueueInteractions();
    $retry->handle();
    $retry->assertReleased(60);
    expect($first->fresh()->status)->toBe('pending');
    (new SendNewsletterDelivery($first->id))->handle();
    (new SendNewsletterDelivery($second->id))->handle();
    (new SendNewsletterDelivery($third->id))->handle();

    expect($first->fresh()->status)->toBe('accepted')->and($second->fresh()->status)->toBe('unknown')
        ->and($third->fresh()->status)->toBe('failed')->and($campaign->fresh()->completed_at)->not->toBeNull();
});
