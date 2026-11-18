<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClergyLegacyAffiliation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function clergyMember(): BelongsTo
    {
        return $this->belongsTo(ClergyMember::class);
    }

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }
}
