<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryPhoto extends ContentModel
{
    protected $table = 'gallery_photos';

    public static string $sourceTable = 'galeria_foto';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'gallery_id');
    }
}
