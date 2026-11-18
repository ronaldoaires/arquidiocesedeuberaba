<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parish extends ContentModel
{
    protected $table = 'parishes';

    public static string $sourceTable = 'paroquia';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function deanery(): BelongsTo
    {
        return $this->belongsTo(Deanery::class, 'deanery_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ParishClergyAssignment::class, 'parish_id');
    }
}
