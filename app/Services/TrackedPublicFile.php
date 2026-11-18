<?php

namespace App\Services;

use App\Http\Controllers\StorageController;
use App\Models\ContentModel;
use App\Support\ContentRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrackedPublicFile
{
    public function preview(Request $request, ContentModel $record): RedirectResponse|BinaryFileResponse
    {
        abort_unless(filled($record->file_path), 404);
        $response = app(StorageController::class)->serve($request, $record->file_path);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }
        $this->recordAccess($request, $record, 'visits');

        return redirect()->route('media.show', ['path' => $record->file_path])->withHeaders(['Cache-Control' => 'private, no-store']);
    }

    public function download(Request $request, ContentModel $record): BinaryFileResponse
    {
        abort_unless(filled($record->file_path), 404);
        $response = app(StorageController::class)->serve($request, $record->file_path);
        $response->setContentDisposition('attachment', basename($record->file_path));
        $response->headers->set('Cache-Control', 'private, no-store');
        if ($response->getStatusCode() === 200) {
            $this->recordAccess($request, $record, 'downloads');
        }

        return $response;
    }

    private function recordAccess(Request $request, ContentModel $record, string $counter): void
    {
        if (! config('migration.record_visits', true) || ! $request->isMethod('GET') || $request->headers->has('Range')) {
            return;
        }
        $attributes = [$counter => DB::raw('COALESCE('.$counter.', 0) + 1')];
        if ($counter === 'visits' && in_array('last_visited_at', array_column(ContentRegistry::get($record::$sourceTable)['columns'], 'name'), true)) {
            $attributes['last_visited_at'] = now();
        }
        $record->newQuery()->whereKey($record->id)->toBase()->update($attributes);
    }
}
