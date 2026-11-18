<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Slide extends ContentModel
{
    protected $table = 'slides';

    public static string $sourceTable = 'slide';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
