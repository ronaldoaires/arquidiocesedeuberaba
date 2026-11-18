<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;

class SiteSetting extends ContentModel
{
    protected $table = 'site_settings';

    public static string $sourceTable = 'config';

    public function getLogoUrlAttribute(): string
    {
        $disk = Storage::disk('public');

        return filled($this->cover_path) && $disk->exists($this->cover_path)
            ? $disk->url($this->cover_path)
            : asset('assets/site/logoVermelho.png');
    }
}
