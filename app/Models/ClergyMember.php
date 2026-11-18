<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClergyMember extends ContentModel
{
    protected $table = 'clergy_members';

    protected $hidden = ['legacy_presentation_date_text'];

    public static string $sourceTable = 'clero';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClergyCategory::class, 'category_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ParishClergyAssignment::class, 'clergy_member_id');
    }

    protected function casts(): array
    {
        return [...parent::casts(), 'presentation_date' => 'date:Y-m-d'];
    }
}
