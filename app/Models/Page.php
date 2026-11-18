<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends ContentModel
{
    protected $table = 'pages';

    public static string $sourceTable = 'pagina';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'category_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class, 'page_id');
    }
}
