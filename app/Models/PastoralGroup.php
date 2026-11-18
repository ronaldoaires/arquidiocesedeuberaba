<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PastoralGroup extends ContentModel
{
    protected $table = 'pastoral_groups';

    public static string $sourceTable = 'pastoral';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(PastoralDownload::class, 'pastoral_group_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(PastoralVideo::class, 'pastoral_group_id');
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(PastoralGallery::class, 'pastoral_group_id');
    }
}
