<?php

namespace App\Services\Newsletter;

use App\Jobs\SendNewsletterDelivery;
use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

class CampaignDispatch
{
    public function review(NewsletterCampaign $campaign): array
    {
        $html = (new NewsletterCampaignMail($campaign))->render();
        $count = $this->audience()->count();
        $hashes = CampaignAttachments::hashes($campaign);
        $mailer = config('mail.default');
        $fingerprint = hash('sha256', serialize([$campaign->subject, $html, $hashes, $campaign->attachment_names, $count, $mailer]));

        return compact('html', 'count', 'hashes', 'mailer', 'fingerprint');
    }

    public function confirm(int $campaignId, string $fingerprint): void
    {
        DB::transaction(function () use ($campaignId, $fingerprint): void {
            $campaign = NewsletterCampaign::lockForUpdate()->findOrFail($campaignId);
            if ($campaign->dispatched_at) {
                return;
            }

            $review = $this->review($campaign);
            if (! $review['count']) {
                throw ValidationException::withMessages(['audience' => 'Ainda não há inscritos ativos com e-mail confirmado.']);
            }
            if (! hash_equals($review['fingerprint'], $fingerprint)) {
                throw $this->changedReview();
            }
            $campaign->forceFill([
                'dispatch_html' => $review['html'], 'attachment_hashes' => $review['hashes'],
                'dispatch_mailer' => $review['mailer'], 'dispatched_at' => now(),
            ])->save();

            DB::table('newsletter_deliveries')->insertUsing(
                ['newsletter_campaign_id', 'newsletter_subscriber_id', 'email', 'subscription_key'],
                $this->audience()->selectRaw('?, id, email, link_key', [$campaignId])->toBase(),
            );
            if ($campaign->deliveries()->count() !== $review['count']) {
                throw $this->changedReview();
            }

            $batchSize = max(1, (int) config('newsletter.batch_size'));
            $start = now()->toImmutable();
            $index = 0;
            $campaign->deliveries()->select('id')->chunkById(100, function ($rows) use ($batchSize, $start, &$index): void {
                $jobs = [];
                foreach ($rows as $row) {
                    $jobs[] = (new SendNewsletterDelivery($row->id))->beforeCommit()->delay($start->addMinutes(intdiv($index++, $batchSize)));
                }
                $this->enqueue($jobs);
            });
        }, 3);
    }

    public function retryFailed(NewsletterDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $campaign = NewsletterCampaign::lockForUpdate()->findOrFail($delivery->newsletter_campaign_id);
            $reset = $campaign->deliveries()->whereKey($delivery->id)->where('status', 'failed')
                ->update(['status' => 'pending', 'detail' => null, 'finished_at' => null, 'attempted_at' => null]);
            if ($reset) {
                $campaign->forceFill(['completed_at' => null])->save();
                $this->enqueue([(new SendNewsletterDelivery($delivery->id))->beforeCommit()]);
            }
        }, 3);
    }

    public static function finishIfComplete(int $campaignId): void
    {
        NewsletterCampaign::whereKey($campaignId)->whereNull('completed_at')->whereNotNull('dispatched_at')
            ->whereDoesntHave('deliveries', fn (Builder $query) => $query->whereIn('status', ['pending', 'sending']))
            ->update(['completed_at' => now()]);
    }

    private function enqueue(array $jobs): void
    {
        $queue = Queue::connection('database');
        if ($queue->getDatabase() !== DB::connection()) {
            throw ValidationException::withMessages(['queue' => 'Configure a fila database na mesma conexão do boletim para confirmar a campanha e a fila juntas.']);
        }
        $queue->bulk($jobs, queue: SendNewsletterDelivery::QUEUE_NAME);
    }

    private function audience(): Builder
    {
        return NewsletterSubscriber::where('status', NewsletterSubscriber::ACTIVE)->whereNotNull('verified_at');
    }

    private function changedReview(): ValidationException
    {
        return ValidationException::withMessages(['review' => 'A campanha ou a lista de inscritos mudou. Confira a revisão atualizada antes de confirmar.']);
    }
}
