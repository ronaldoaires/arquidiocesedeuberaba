<?php

use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Mail\NewsletterAccessMail;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Newsletter\SubscriptionLinks;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

it('accepts the footer request in place and limits repeated mail for the same address', function (): void {
    $this->get('/contato')->assertOk()->assertSee('Quero receber');
    $url = route('site.newsletter.subscribe');
    $this->from('/contato')->post($url, ['newsletter_email' => ' Reader@Example.test '])
        ->assertRedirect(url('/contato').'#boletim')->assertSessionHas('newsletter_requested');
    $this->get('/contato')->assertSee('Reenviar link');
    $this->post($url, ['newsletter_email' => 'reader@example.test']);

    $subscriber = NewsletterSubscriber::sole();
    expect($subscriber->email)->toBe('reader@example.test')->and($subscriber->status)->toBe('pending')
        ->and($subscriber->verified_at)->toBeNull();
    Mail::assertSent(NewsletterAccessMail::class, 1);
    Mail::assertSent(NewsletterAccessMail::class, function (NewsletterAccessMail $mail): bool {
        $mail->assertSeeInHtml('Concluir minha inscrição');

        return $mail->hasTo('reader@example.test');
    });

    $this->travel(61)->seconds();
    $this->post($url, ['newsletter_email' => 'reader@example.test']);
    Mail::assertSent(NewsletterAccessMail::class, 2);
});

it('keeps footer validation separate from other forms', function (): void {
    $this->from('/contato')->post(route('site.newsletter.subscribe'), ['newsletter_email' => 'invalid'])
        ->assertRedirect(url('/contato').'#boletim')->assertSessionHasErrorsIn('newsletter', 'newsletter_email');
    expect(NewsletterSubscriber::count())->toBe(0);
    Mail::assertNothingSent();
});

it('activates only on submission and allows a completely optional profile', function (): void {
    $this->post(route('site.newsletter.subscribe'), ['newsletter_email' => 'reader@example.test']);
    $mail = Mail::sent(NewsletterAccessMail::class)->sole();
    $confirmation = $mail->content()->with['actionUrl'];
    $this->get($confirmation)->assertOk()->assertSee('Confirmar inscrição')->assertHeader('Referrer-Policy', 'no-referrer');
    expect(NewsletterSubscriber::sole()->status)->toBe('pending');

    $response = $this->post($confirmation)->assertRedirect()->assertSessionHas('newsletter_status');
    $this->get($response->headers->get('Location'))->assertOk()->assertSee('Inscrição confirmada!');
    $subscriber = NewsletterSubscriber::sole();
    expect($subscriber->status)->toBe('active')->and($subscriber->verified_at)->not->toBeNull()
        ->and($subscriber->name)->toBeNull()->and($subscriber->city)->toBeNull();
    $this->post($confirmation, ['name' => 'Outro nome'])->assertGone();
    expect($subscriber->fresh()->name)->toBeNull();
});

it('rejects expired or altered links and supports a fresh confirmation', function (): void {
    $subscriber = NewsletterSubscriber::factory()->create();
    $url = SubscriptionLinks::confirmation($subscriber);
    $this->get($url.'&extra=1')->assertGone()->assertSee('Solicitar novo link');
    $this->travel(25)->hours();
    $this->post($url)->assertGone();
    $this->post(route('site.newsletter.subscribe'), ['newsletter_email' => $subscriber->email]);
    $freshUrl = Mail::sent(NewsletterAccessMail::class)->sole()->content()->with['actionUrl'];
    $this->post($freshUrl, ['name' => 'Maria', 'city' => 'Uberaba', 'state' => 'MG'])->assertRedirect();
    expect($subscriber->fresh()->city)->toBe('Uberaba')->and($subscriber->fresh()->status)->toBe('active');
});

it('validates profile fields, supplies management links and requires a new confirmation after cancellation', function (): void {
    $subscriber = NewsletterSubscriber::factory()->create(['status' => 'active', 'verified_at' => now()]);
    $this->post(route('site.newsletter.subscribe'), ['newsletter_email' => $subscriber->email]);
    $mail = Mail::sent(NewsletterAccessMail::class)->sole();
    $mail->assertSeeInHtml('Atualizar meus dados')->assertSeeInHtml('Cancelar inscrição');
    $urls = $mail->content()->with;
    $this->post($urls['actionUrl'], ['state' => 'XX'])->assertSessionHasErrors('state')->assertRedirect($urls['actionUrl']);
    $this->post($urls['actionUrl'], ['city' => 'Araxá', 'state' => 'MG', 'email' => 'other@example.test'])
        ->assertRedirect($urls['actionUrl']);
    expect($subscriber->fresh()->city)->toBe('Araxá')->and($subscriber->fresh()->email)->toBe($subscriber->email);
    $this->get($urls['cancelUrl'])->assertOk()->assertSee('Confirmar cancelamento');
    expect($subscriber->fresh()->status)->toBe('active');

    $this->post($urls['cancelUrl'])->assertRedirect()->assertSessionHas('newsletter_status');
    $this->post($urls['actionUrl'], ['city' => 'Outra'])->assertGone();
    $this->post(route('site.newsletter.subscribe'), ['newsletter_email' => $subscriber->email]);
    expect($subscriber->fresh()->status)->toBe('unsubscribed')->and($subscriber->fresh()->unsubscribed_at)->not->toBeNull();
    $url = Mail::sent(NewsletterAccessMail::class)->last()->content()->with['actionUrl'];
    $this->post($url)->assertRedirect();
    $this->post($urls['cancelUrl'])->assertGone();
    expect($subscriber->fresh()->status)->toBe('active')->and($subscriber->fresh()->unsubscribed_at)->toBeNull();
});

it('lists subscribers for administrators with search and status and state filters', function (): void {
    $active = NewsletterSubscriber::factory()->create(['status' => 'active', 'state' => 'MG', 'city' => 'Uberaba']);
    $pending = NewsletterSubscriber::factory()->create(['state' => 'SP']);
    $admin = User::factory()->create(['is_active' => true, 'legacy_level' => 3]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->get(NewsletterSubscriberResource::getUrl())->assertOk()->assertSee('Inscritos na newsletter');
    Livewire::test(ListNewsletterSubscribers::class)->assertCanSeeTableRecords([$active, $pending])
        ->filterTable('status', 'active')->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$pending])
        ->resetTableFilters()->filterTable('state', 'SP')->assertCanSeeTableRecords([$pending])->assertCanNotSeeTableRecords([$active])
        ->resetTableFilters()->searchTable('Uberaba')->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$pending]);
});

it('restricts the subscriber list to active administrators', function (): void {
    $url = NewsletterSubscriberResource::getUrl();
    $this->get($url)->assertRedirect();
    foreach ([[true, 2], [false, 3]] as [$active, $level]) {
        $this->actingAs(User::factory()->create(['is_active' => $active, 'legacy_level' => $level]))
            ->get($url)->assertForbidden();
    }
});

it('lets a reader retry after a mail transport failure', function (): void {
    $address = 'reader@example.test';
    $mailFake = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->with($address)->once()->andThrow(new TransportException('Delivery unavailable'));

    $this->post(route('site.newsletter.subscribe'), ['newsletter_email' => $address])
        ->assertSessionHasErrorsIn('newsletter', 'newsletter_email');
    expect(NewsletterSubscriber::sole()->status)->toBe('pending');
    Mail::swap($mailFake);
    $this->post(route('site.newsletter.subscribe'), ['newsletter_email' => $address])
        ->assertSessionHas('newsletter_requested');
    Mail::assertSent(NewsletterAccessMail::class, 1);
});
