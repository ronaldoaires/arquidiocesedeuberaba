<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\SubscriptionLinks;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewsletterAccessMail extends Mailable
{
    public function __construct(private NewsletterSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subscriber->status === NewsletterSubscriber::ACTIVE
            ? 'Gerencie sua inscrição no boletim da Arquidiocese'
            : 'Confirme sua inscrição no boletim da Arquidiocese');
    }

    public function content(): Content
    {
        $active = $this->subscriber->status === NewsletterSubscriber::ACTIVE;

        return new Content(view: 'site.newsletter.email', with: [
            'active' => $active,
            'actionUrl' => $active ? SubscriptionLinks::profile($this->subscriber) : SubscriptionLinks::confirmation($this->subscriber),
            'cancelUrl' => $active ? SubscriptionLinks::cancellation($this->subscriber) : null,
        ]);
    }
}
