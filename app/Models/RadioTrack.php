<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadioTrack extends ContentModel
{
    protected $table = 'radio_tracks';

    public static string $sourceTable = 'radio_musica';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function radioStation(): BelongsTo
    {
        return $this->belongsTo(RadioStation::class, 'radio_station_id');
    }
}
