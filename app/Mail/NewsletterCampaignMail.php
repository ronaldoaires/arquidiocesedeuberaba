<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\CampaignAttachments;
use App\Services\Newsletter\CampaignCatalog;
use App\Services\Newsletter\SubscriptionLinks;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class NewsletterCampaignMail extends Mailable
{
    public function __construct(public NewsletterCampaign $campaign, private ?NewsletterSubscriber $subscriber = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function content(): Content
    {
        if ($this->campaign->dispatched_at) {
            $html = $this->campaign->dispatch_html;
            if ($this->subscriber) {
                $footer = view('site.newsletter.subscriber-links', SubscriptionLinks::forCampaign($this->subscriber))->render();
                $html = Str::replaceLast('</body>', $footer.'</body>', $html);
            }

            return new Content(htmlString: $html);
        }

        $keys = $this->campaign->content_keys ?? [];
        $items = app(CampaignCatalog::class)->resolve($keys);
        if ($items->count() !== count($keys)) {
            throw ValidationException::withMessages(['content_keys' => 'Um conteúdo deixou de estar publicado. Remova-o ou escolha outro na campanha.']);
        }

        $config = (new HtmlSanitizerConfig)->allowElement('a', ['href'])->allowLinkSchemes(['https', 'http', 'mailto']);
        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li'] as $element) {
            $config = $config->allowElement($element);
        }

        return new Content(view: 'site.newsletter.campaign', with: [
            'body' => (new HtmlSanitizer($config))->sanitize($this->campaign->body_html),
            'items' => $items,
            'linkLabels' => CampaignCatalog::linkLabels(),
        ]);
    }

    public function attachments(): array
    {
        return CampaignAttachments::forMail($this->campaign);
    }
}
