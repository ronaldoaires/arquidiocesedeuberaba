<?php

namespace App\Services\Newsletter;

use App\Models\ContentModel;
use App\Models\NewsletterCampaign;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class QuickCampaign
{
    private const TYPES = ['noticia' => 'news', 'artigo' => 'articles', 'evento' => 'events'];

    public function send(ContentModel $record): void
    {
        $type = self::TYPES[$record::$sourceTable] ?? null;
        if (! $type || ! $record::published()->whereKey($record->getKey())->exists()) {
            Notification::make()->warning()->title('Conteúdo salvo, mas ainda não está publicado para envio.')->send();

            return;
        }

        $campaign = NewsletterCampaign::create([
            'subject' => $record->display_title,
            'body_html' => '<p>Confira o novo conteúdo da Arquidiocese de Uberaba.</p>',
            'content_keys' => [$type.':'.$record->getKey()],
        ]);

        try {
            $dispatch = app(CampaignDispatch::class);
            $review = $dispatch->review($campaign);
            $dispatch->confirm($campaign->id, $review['fingerprint']);
            Notification::make()->success()->title('Campanha criada e envio colocado na fila.')->send();
        } catch (ValidationException $exception) {
            Notification::make()->warning()->title('Conteúdo salvo; campanha criada como rascunho.')->body($exception->getMessage())->send();
        }
    }
}
