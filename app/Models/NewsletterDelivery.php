<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterDelivery extends Model
{
    public $timestamps = false;

    protected $hidden = ['subscription_key'];

    public const LABELS = [
        'pending' => 'Na fila', 'sending' => 'Em envio', 'accepted' => 'Aceito pelo serviço',
        'simulated' => 'Simulado', 'skipped' => 'Ignorado', 'failed' => 'Falhou', 'unknown' => 'Entrega incerta',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'newsletter_campaign_id');
    }

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
