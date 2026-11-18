<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PastoralGalleryPhoto extends ContentModel
{
    protected $table = 'pastoral_gallery_photos';

    public static string $sourceTable = 'pastoral_galeria_foto';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(PastoralGallery::class, 'gallery_id');
    }

    public function pastoralGroup(): BelongsTo
    {
        return $this->belongsTo(PastoralGroup::class, 'pastoral_group_id');
    }
}
