<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicMedia
{
    public static function thumbnailPath(?string $path): ?string
    {
        if (blank($path) || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
            return null;
        }
        $thumbnail = dirname($path).'/thumbs/'.basename($path);

        return Storage::disk('public')->exists($thumbnail) ? $thumbnail : $path;
    }
}
