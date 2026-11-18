<?php

namespace App\Models;

use App\Services\YouTubeVideoMetadata;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PastoralVideo extends ContentModel
{
    protected $table = 'pastoral_videos';

    public static string $sourceTable = 'pastoral_videos';

    public function getVideoTitleAttribute(): string
    {
        return filled($this->title) ? $this->title : (app(YouTubeVideoMetadata::class)->get($this->video_id)['title'] ?? 'Vídeo da pastoral');
    }

    public function getVideoThumbnailAttribute(): ?string
    {
        return app(YouTubeVideoMetadata::class)->get($this->video_id)['thumbnail'];
    }

    public function getVideoEmbedUrlAttribute(): ?string
    {
        return app(YouTubeVideoMetadata::class)->validId($this->video_id) ? 'https://www.youtube-nocookie.com/embed/'.$this->video_id : null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pastoralGroup(): BelongsTo
    {
        return $this->belongsTo(PastoralGroup::class, 'pastoral_group_id');
    }
}
