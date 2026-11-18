<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorageController extends Controller
{
    public function serve(Request $request, string $path): BinaryFileResponse
    {
        return $this->respond($request, $path);
    }

    public function legacy(Request $request, string $path): BinaryFileResponse
    {
        return $this->respond($request, 'legacy/uploads/'.$path);
    }

    private function respond(Request $request, string $path): BinaryFileResponse
    {
        abort_if(
            str_contains($path, '\\')
            || str_contains($path, "\0")
            || str_starts_with($path, '/')
            || preg_match('~(^|/)\.[^/]*($|/)~', $path)
            || preg_match('~^[a-zA-Z]:~', $path),
            404,
        );

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        if (str_starts_with($path, 'legacy/uploads/') && ! $disk->exists($path)) {
            $alternates = DB::table('media_assets')->where('legacy_path', substr($path, 7))->where('state', 'available')->orderBy('path')->pluck('path');
            foreach ($alternates as $candidate) {
                if ($disk->exists($candidate)) {
                    $path = $candidate;
                    break;
                }
            }
        }
        $fullPath = realpath($disk->path($path));

        abort_unless(
            $root !== false
            && $fullPath !== false
            && str_starts_with($fullPath, $root.DIRECTORY_SEPARATOR)
            && is_file($fullPath)
            && is_readable($fullPath),
            404,
        );

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $inlineTypes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'avif' => ['image/avif'],
            'pdf' => ['application/pdf'],
            'mp3' => ['audio/mpeg', 'audio/mp3'],
            'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
        ];
        $downloadExtensions = ['doc', 'docx', 'xls', 'xlsx', 'txt', 'xml', 'mxl', 'mscz', 'zip', 'jpeg_bkp'];
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($fullPath);
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
            $inlineTypes[$extension] = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
        }

        abort_unless(
            (isset($inlineTypes[$extension]) && in_array($mimeType, $inlineTypes[$extension], true))
            || in_array($extension, $downloadExtensions, true),
            404,
        );

        $response = response()->file($fullPath, [
            'Content-Type' => isset($inlineTypes[$extension]) ? $mimeType : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);
        $response->setContentDisposition(
            isset($inlineTypes[$extension]) ? 'inline' : 'attachment',
            basename($fullPath),
        );
        $response->setLastModified(new \DateTimeImmutable('@'.filemtime($fullPath)));
        $response->setEtag(hash('sha256', $path.'|'.filesize($fullPath).'|'.filemtime($fullPath)), true);
        $response->isNotModified($request);

        return $response;
    }
}
