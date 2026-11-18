<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deanery extends ContentModel
{
    protected $table = 'deaneries';

    public static string $sourceTable = 'paroquia_forania';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
