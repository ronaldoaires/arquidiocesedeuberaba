<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstituteCategory extends ContentModel
{
    protected $table = 'institute_categories';

    public static string $sourceTable = 'instituto_categoria';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
