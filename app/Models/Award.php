<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends ContentModel
{
    protected $table = 'awards';

    public static string $sourceTable = 'premio';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
