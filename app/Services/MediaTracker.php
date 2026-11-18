<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaTracker
{
    public function track(Model $record): void
    {
        $paths = app(MediaLifecycle::class)->paths($record->getAttributes());
        $disk = Storage::disk('public');
        foreach (array_unique($paths) as $path) {
            if (DB::table('media_assets')->where('path', $path)->exists() || ! $disk->exists($path)) {
                continue;
            }
            DB::table('media_assets')->insertOrIgnore(['path' => $path, 'bytes' => $disk->size($path), 'sha256' => hash_file('sha256', $disk->path($path)), 'state' => 'available']);
        }
    }
}
