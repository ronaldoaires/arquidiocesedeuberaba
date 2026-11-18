<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends ContentModel
{
    protected $table = 'events';

    public static string $sourceTable = 'evento';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
