<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsCategory extends ContentModel
{
    protected $table = 'news_categories';

    public static string $sourceTable = 'noticia_categoria';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
