<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParishClergyAssignment extends ContentModel
{
    protected $table = 'parish_clergy_assignments';

    public static string $sourceTable = 'paroquia_clero';

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class, 'parish_id');
    }

    public function clergyMember(): BelongsTo
    {
        return $this->belongsTo(ClergyMember::class, 'clergy_member_id');
    }
}
