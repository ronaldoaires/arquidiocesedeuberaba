<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadioProgram extends ContentModel
{
    protected $table = 'radio_programs';

    public static string $sourceTable = 'radio_programa';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function radioStation(): BelongsTo
    {
        return $this->belongsTo(RadioStation::class, 'radio_station_id');
    }
}
