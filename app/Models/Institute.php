<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Institute extends ContentModel
{
    protected $table = 'institutes';

    public static string $sourceTable = 'instituto';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InstituteCategory::class, 'category_id');
    }
}
