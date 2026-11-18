<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClergyCategory extends ContentModel
{
    protected $table = 'clergy_categories';

    public static string $sourceTable = 'clero_categoria';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
