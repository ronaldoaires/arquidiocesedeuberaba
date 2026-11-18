<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PastoralGallery extends ContentModel
{
    protected $table = 'pastoral_galleries';

    public static string $sourceTable = 'pastoral_galeria';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pastoralGroup(): BelongsTo
    {
        return $this->belongsTo(PastoralGroup::class, 'pastoral_group_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PastoralGalleryPhoto::class, 'gallery_id');
    }
}
