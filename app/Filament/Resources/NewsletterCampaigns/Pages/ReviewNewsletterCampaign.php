<?php

namespace App\Filament\Resources\NewsletterCampaigns\Pages;

use App\Filament\Resources\NewsletterCampaigns\NewsletterCampaignResource;
use App\Services\Newsletter\CampaignDispatch;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ReviewNewsletterCampaign extends Page
{
    use InteractsWithRecord;

    protected static string $resource = NewsletterCampaignResource::class;

    protected static ?string $title = 'Revisar envio';

    protected string $view = 'filament.newsletter.review';

    #[Locked]
    public string $fingerprint = '';

    #[Locked]
    public string $html = '';

    #[Locked]
    public int $recipientCount = 0;

    #[Locked]
    public ?string $warning = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(NewsletterCampaignResource::canViewAny(), 403);
        if ($this->record->dispatched_at) {
            $this->redirect(NewsletterCampaignResource::getUrl('history', ['record' => $this->record]));

            return;
        }
        $this->loadReview();
    }

    public function confirm(CampaignDispatch $dispatch): void
    {
        abort_unless(NewsletterCampaignResource::canViewAny(), 403);
        try {
            $dispatch->confirm($this->record->id, $this->fingerprint);
        } catch (ValidationException $exception) {
            $this->record->refresh();
            $this->loadReview();
            Notification::make()->warning()->title($exception->getMessage())->send();

            return;
        }

        $this->redirect(NewsletterCampaignResource::getUrl('history', ['record' => $this->record]));
    }

    private function loadReview(): void
    {
        $this->warning = null;
        try {
            $review = app(CampaignDispatch::class)->review($this->record);
            $this->html = $review['html'];
            $this->recipientCount = $review['count'];
            $this->fingerprint = $review['fingerprint'];
            if (! $this->recipientCount) {
                $this->warning = 'Ainda não há inscritos ativos com e-mail confirmado.';
            }
        } catch (ValidationException $exception) {
            $this->warning = $exception->getMessage();
            $this->fingerprint = '';
            $this->html = '';
        }
    }
}
