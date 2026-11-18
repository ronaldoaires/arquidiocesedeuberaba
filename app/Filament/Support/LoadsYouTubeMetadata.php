<?php

namespace App\Filament\Support;

use App\Services\YouTubeVideoMetadata;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

trait LoadsYouTubeMetadata
{
    #[Unexposed]
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $records = parent::getTableRecords();
        app(YouTubeVideoMetadata::class)->prime($records->pluck('video_id')->all());

        return $records;
    }
}
