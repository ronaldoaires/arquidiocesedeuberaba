<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PastoralDownload extends ContentModel
{
    protected $table = 'pastoral_downloads';

    public static string $sourceTable = 'pastoral_downloads';

    protected function casts(): array
    {
        return [...parent::casts(), 'visits' => 'integer', 'downloads' => 'integer'];
    }

    public function pastoralGroup(): BelongsTo
    {
        return $this->belongsTo(PastoralGroup::class, 'pastoral_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
