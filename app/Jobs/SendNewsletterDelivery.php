<?php

namespace App\Jobs;

use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\CampaignAttachments;
use App\Services\Newsletter\CampaignDispatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

class SendNewsletterDelivery implements ShouldQueue
{
    use Queueable;

    public const QUEUE_NAME = 'newsletter';

    public int $tries = 3;

    public int $timeout = 35;

    public int $backoff = 60;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = NewsletterDelivery::findOrFail($this->deliveryId);
        if ($delivery->status !== 'pending') {
            if ($delivery->status === 'sending' && $this->attempts() > 1) {
                $this->finish($delivery, 'unknown', 'O processamento foi interrompido sem confirmação do serviço.');
            }
            CampaignDispatch::finishIfComplete($delivery->newsletter_campaign_id);

            return;
        }

        $campaign = $delivery->campaign;
        try {
            $expected = $campaign->attachment_hashes ?? [];
            ksort($expected);
            if (CampaignAttachments::hashes($campaign) !== $expected) {
                throw ValidationException::withMessages(['attachments' => 'Um anexo mudou depois da confirmação.']);
            }
        } catch (ValidationException $exception) {
            $this->finish($delivery, 'failed', $exception->getMessage(), 'pending');

            return;
        }

        $mailer = Mail::mailer($campaign->dispatch_mailer);
        $claimed = NewsletterDelivery::whereKey($delivery->id)->where('status', 'pending')
            ->update(['status' => 'sending', 'attempted_at' => now()]);
        if (! $claimed) {
            return;
        }

        $subscriber = NewsletterSubscriber::whereKey($delivery->newsletter_subscriber_id)
            ->where('email', $delivery->email)->where('link_key', $delivery->subscription_key)
            ->where('status', NewsletterSubscriber::ACTIVE)->whereNotNull('verified_at')->first();
        if (! $subscriber) {
            $this->finish($delivery, 'skipped', 'A inscrição deixou de ser válida para esta campanha.');

            return;
        }

        $message = new NewsletterCampaignMail($campaign, $subscriber);

        try {
            $sent = $mailer->to($delivery->email)->send($message);
        } catch (UnexpectedResponseException $exception) {
            if ($exception->getCode() >= 400 && $exception->getCode() < 500 && $this->attempts() < $this->tries) {
                NewsletterDelivery::whereKey($delivery->id)->where('status', 'sending')->update(['status' => 'pending']);
                $this->release($this->backoff);

                return;
            }
            if ($exception->getCode() >= 400 && $exception->getCode() < 600) {
                $this->finish($delivery, 'failed', 'O servidor de e-mail recusou a mensagem.');
            } else {
                $this->finish($delivery, 'unknown', 'O servidor não retornou uma confirmação válida.');
            }

            return;
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            $this->finish($delivery, 'unknown', 'O serviço não confirmou a aceitação da mensagem.');

            return;
        }

        $simulated = in_array(config('mail.mailers.'.$campaign->dispatch_mailer.'.transport'), ['log', 'array'], true);
        if (! $sent) {
            $this->finish($delivery, 'skipped', 'O envio foi interrompido antes da entrega ao serviço.');

            return;
        }
        $this->finish($delivery, $simulated ? 'simulated' : 'accepted');
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = NewsletterDelivery::find($this->deliveryId);
        if (! $delivery) {
            return;
        }
        if ($delivery->status === 'sending') {
            $this->finish($delivery, 'unknown', 'O processamento terminou sem confirmação do serviço.');
        } elseif ($delivery->status === 'pending') {
            $this->finish($delivery, 'failed', 'Não foi possível preparar o envio após as tentativas da fila.', 'pending');
        } else {
            CampaignDispatch::finishIfComplete($delivery->newsletter_campaign_id);
        }
    }

    private function finish(NewsletterDelivery $delivery, string $status, ?string $detail = null, string $from = 'sending'): void
    {
        NewsletterDelivery::whereKey($delivery->id)->where('status', $from)
            ->update(['status' => $status, 'finished_at' => now(), 'detail' => $detail]);
        CampaignDispatch::finishIfComplete($delivery->newsletter_campaign_id);
    }
}
