<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PastoralDownload;
use App\Services\TrackedPublicFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PastoralDownloadController extends Controller
{
    public function preview(Request $request, int $download): RedirectResponse|BinaryFileResponse
    {
        return app(TrackedPublicFile::class)->preview($request, $this->publishedDownload($download));
    }

    public function download(Request $request, int $download): BinaryFileResponse
    {
        return app(TrackedPublicFile::class)->download($request, $this->publishedDownload($download));
    }

    private function publishedDownload(int $id): PastoralDownload
    {
        return PastoralDownload::query()->whereHas('pastoralGroup', fn (Builder $query): Builder => $query->published())->findOrFail($id);
    }
}
