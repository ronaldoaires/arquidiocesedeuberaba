<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RadioStation extends ContentModel
{
    protected $table = 'radio_stations';

    public static string $sourceTable = 'radio';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(RadioProgram::class, 'radio_station_id');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(RadioTrack::class, 'radio_station_id');
    }
}
