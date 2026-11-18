<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Newspaper;
use App\Services\TrackedPublicFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NewspaperFileController extends Controller
{
    public function preview(Request $request, string $slug): RedirectResponse|BinaryFileResponse
    {
        return app(TrackedPublicFile::class)->preview($request, Newspaper::published()->where('slug', $slug)->firstOrFail());
    }

    public function download(Request $request, string $slug): BinaryFileResponse
    {
        return app(TrackedPublicFile::class)->download($request, Newspaper::published()->where('slug', $slug)->firstOrFail());
    }
}
