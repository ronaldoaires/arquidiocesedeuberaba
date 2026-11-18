<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsItem extends ContentModel
{
    protected $table = 'news';

    public static string $sourceTable = 'noticia';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }
}
