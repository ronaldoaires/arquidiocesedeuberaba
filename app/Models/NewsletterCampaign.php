<?php

namespace App\Models;

use App\Services\Newsletter\CampaignAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class NewsletterCampaign extends Model
{
    use HasFactory;

    protected $fillable = ['subject', 'body_html', 'content_keys', 'attachments', 'attachment_names'];

    public const DISPATCH_FIELDS = ['dispatch_html', 'dispatch_mailer', 'attachment_hashes', 'dispatched_at', 'completed_at'];

    public function deliveries(): HasMany
    {
        return $this->hasMany(NewsletterDelivery::class);
    }

    protected function casts(): array
    {
        return ['content_keys' => 'array', 'attachments' => 'array', 'attachment_names' => 'array', 'dispatched_at' => 'datetime', 'completed_at' => 'datetime', 'attachment_hashes' => 'array'];
    }

    protected static function booted(): void
    {
        static::updated(function (self $campaign): void {
            $removed = array_diff($campaign->getOriginal('attachments') ?? [], $campaign->attachments ?? []);
            if ($removed) {
                DB::afterCommit(fn () => CampaignAttachments::removeUnused($removed));
            }
        });
    }
}
