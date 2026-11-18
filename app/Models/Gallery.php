<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gallery extends ContentModel
{
    protected $table = 'galleries';

    public static string $sourceTable = 'galeria';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GalleryPhoto::class, 'gallery_id');
    }
}
