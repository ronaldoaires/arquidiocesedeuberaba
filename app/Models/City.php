<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends ContentModel
{
    protected $table = 'cities';

    public static string $sourceTable = 'paroquia_cidade';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
