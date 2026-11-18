<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContactAttachmentController extends Controller
{
    public function __invoke(Request $request, int $submission, int $index): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->legacy_level === 3, 403);
        $record = ContactSubmission::findOrFail($submission);
        $attachment = $record->attachments[$index] ?? null;
        abort_unless($attachment, 404);
        $path = is_array($attachment) ? $attachment['path'] : $attachment;
        $name = is_array($attachment) ? $attachment['name'] : basename($path);
        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $fullPath = realpath($disk->path($path));
        abort_unless($root && $fullPath && str_starts_with($fullPath, $root.DIRECTORY_SEPARATOR) && is_file($fullPath), 404);

        return response()->download($fullPath, $name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
