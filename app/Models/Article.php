<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends ContentModel
{
    protected $table = 'articles';

    public static string $sourceTable = 'artigo';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
