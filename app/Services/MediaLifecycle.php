<?php

namespace App\Services;

use App\Models\ContentModel;
use App\Support\ContentRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaLifecycle
{
    private int $galleryDeletionDepth = 0;

    private const FIELDS = ['cover_path', 'photo_path', 'file_path', 'body_html', 'description', 'map_html', 'service_hours', 'office_hours'];

    public function paths(array $attributes): array
    {
        $paths = [];
        foreach (array_intersect_key($attributes, array_flip(self::FIELDS)) as $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }
            $values = [$value];
            if (str_contains($value, '<')) {
                $document = new \DOMDocument;
                $previous = libxml_use_internal_errors(true);
                $document->loadHTML('<?xml encoding="UTF-8">'.$value);
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                $values = [];
                foreach ((new \DOMXPath($document))->query('//@src|//@href|//@poster|//@data-id|//@srcset|//@style') as $attribute) {
                    if ($attribute->name === 'srcset') {
                        foreach (explode(',', $attribute->value) as $candidate) {
                            $values[] = preg_split('/\s+/', trim($candidate))[0];
                        }
                    } elseif ($attribute->name === 'style') {
                        preg_match_all('~url\([\x22\x27]?([^\x22\x27)]+)~i', $attribute->value, $matches);
                        array_push($values, ...$matches[1]);
                    } else {
                        $values[] = $attribute->value;
                    }
                }
            }
            foreach ($values as $candidate) {
                if ($path = $this->normalize($candidate)) {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    public function normalize(string $value): ?string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $host = parse_url($value, PHP_URL_HOST);
        $allowed = array_filter([parse_url(config('app.url'), PHP_URL_HOST), request()->getHost(), 'arquidiocesedeuberaba.com.br', 'www.arquidiocesedeuberaba.com.br', 'arquidiocesedeuberaba.org.br', 'www.arquidiocesedeuberaba.org.br']);
        if ($host && ! in_array(strtolower($host), $allowed, true)) {
            return null;
        }
        $path = rawurldecode((string) parse_url($value, PHP_URL_PATH));
        $storagePrefix = parse_url(config('filesystems.disks.public.url', '/storage'), PHP_URL_PATH) ?: '/storage';
        if (str_starts_with($path, rtrim($storagePrefix, '/').'/')) {
            $path = substr($path, strlen(rtrim($storagePrefix, '/')) + 1);
        } elseif (str_starts_with($path, '/storage/')) {
            $path = substr($path, 9);
        } elseif (preg_match('~(?:^|/)uploads/(.+)$~', $path, $match)) {
            $path = 'legacy/uploads/'.$match[1];
        }
        if (! preg_match('~^(?:legacy/uploads|editor|content)/~', $path) || str_contains($path, '\\') || str_contains($path, "\0") || preg_match('~(^|/)\.{1,2}(/|$)~', $path)) {
            return null;
        }

        return $path;
    }

    public function changed(Model $record): void
    {
        $before = $this->paths($record->getRawOriginal());
        $after = $this->paths($record->getAttributes());
        $removed = array_diff($before, $after);
        if ($removed) {
            DB::connection($record->getConnectionName())->afterCommit(fn () => $this->retireUnused($removed));
        }
    }

    public function deleteGalleryPhotos(ContentModel $record): void
    {
        if (! in_array($record::$sourceTable, ['galeria', 'pastoral_galeria'], true)) {
            return;
        }
        $photos = $record->photos()->get();
        $manifest = ['class' => $record::class, 'id' => $record->getKey(), 'photos' => $photos->modelKeys(), 'paths' => []];
        $this->galleryDeletionDepth++;
        try {
            foreach ($photos as $photo) {
                $manifest['paths'] = array_merge($manifest['paths'], $this->paths($photo->getAttributes()));
                $photo->delete();
            }
        } finally {
            $this->galleryDeletionDepth--;
        }
        if (! Storage::disk('local')->put($this->recordManifest($record), json_encode($manifest, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Cannot track deleted gallery photos.');
        }
    }

    public function deleted(Model $record): void
    {
        if ($this->galleryDeletionDepth > 0) {
            return;
        }
        $paths = $this->paths($record->getAttributes());
        $private = Storage::disk('local');
        if ($record instanceof ContentModel && in_array($record::$sourceTable, ['galeria', 'pastoral_galeria'], true) && $private->exists($this->recordManifest($record))) {
            $manifest = json_decode($private->get($this->recordManifest($record)), true, flags: JSON_THROW_ON_ERROR);
            $paths = array_merge($paths, $manifest['paths']);
        }
        DB::connection($record->getConnectionName())->afterCommit(fn () => $this->retireUnused($paths));
    }

    public function restored(Model $record): void
    {
        $paths = $this->paths($record->getAttributes());
        foreach ($paths as $path) {
            foreach ($this->family($path) as $member) {
                $this->restorePath($member);
            }
        }
        $private = Storage::disk('local');
        if ($record instanceof ContentModel && in_array($record::$sourceTable, ['galeria', 'pastoral_galeria'], true) && $private->exists($this->recordManifest($record))) {
            $manifest = json_decode($private->get($this->recordManifest($record)), true, flags: JSON_THROW_ON_ERROR);
            foreach ($record->photos()->withTrashed()->whereIn('id', $manifest['photos'])->get() as $photo) {
                if ($photo->trashed()) {
                    $photo->restore();
                }
            }
        }
    }

    public function rememberPending(string $componentId, string $path): void
    {
        $key = $this->pendingKey($componentId);
        Cache::lock('media-pending:'.hash('sha256', $key), 15)->block(5, function () use ($key, $path): void {
            $private = Storage::disk('local');
            $paths = $private->exists($key) ? json_decode($private->get($key), true, flags: JSON_THROW_ON_ERROR) : [];
            $paths[] = $path;
            if (! $private->put($key, json_encode(array_values(array_unique($paths)), JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Cannot track pending media.');
            }
        });
    }

    public function finishForm(string $componentId): void
    {
        $key = $this->pendingKey($componentId);
        $private = Storage::disk('local');
        if (! $private->exists($key)) {
            return;
        }
        $paths = json_decode($private->get($key), true, flags: JSON_THROW_ON_ERROR);
        DB::afterCommit(function () use ($paths, $private, $key): void {
            $this->retireUnused($paths);
            $private->delete($key);
        });
    }

    public function retireUnused(array $paths): void
    {
        $candidates = [];
        foreach ($paths as $path) {
            if ($normalized = $this->normalize($path)) {
                $original = $this->originalPath($normalized);
                $candidates[$this->referenceKey($original)] = $original;
            }
        }
        if (! $candidates) {
            return;
        }
        foreach (ContentRegistry::all() as $source => $module) {
            $fields = array_values(array_intersect(self::FIELDS, array_column($module['columns'], 'name')));
            if (! $fields) {
                continue;
            }
            $class = ContentRegistry::model($source);
            foreach ($class::query()->select($fields)->cursor() as $record) {
                foreach ($this->paths($record->getAttributes()) as $path) {
                    unset($candidates[$this->referenceKey($this->originalPath($path))]);
                }
            }
            if (! $candidates) {
                return;
            }
        }
        foreach (array_values($candidates) as $path) {
            foreach ($this->family($path) as $member) {
                $this->archiveAndDelete($member);
            }
        }
    }

    private function archiveAndDelete(string $path): void
    {
        $public = Storage::disk('public');
        $root = realpath($public->path(''));
        $full = realpath($public->path($path));
        if (! $root || ! $full || ! str_starts_with($full, $root.DIRECTORY_SEPARATOR) || ! is_file($full)) {
            return;
        }
        $hash = hash_file('sha256', $full);
        $archive = 'media-trash/files/'.hash('sha256', $path).'/'.$hash;
        $private = Storage::disk('local');
        $stream = fopen($full, 'rb');
        try {
            if (! $private->put($archive, $stream) || hash_file('sha256', $private->path($archive)) !== $hash) {
                throw new RuntimeException('Cannot verify media recovery copy.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $manifest = ['path' => $path, 'archive' => $archive, 'sha256' => $hash];
        if (! $private->put($this->pathManifest($path), json_encode($manifest, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Cannot save media recovery manifest.');
        }
        if (! $public->delete($path)) {
            throw new RuntimeException('Cannot remove unused public media.');
        }
        DB::table('media_assets')->where('path', $path)->update(['state' => 'archived']);
    }

    private function restorePath(string $path): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        if ($public->exists($path) || ! $private->exists($this->pathManifest($path))) {
            return;
        }
        $manifest = json_decode($private->get($this->pathManifest($path)), true, flags: JSON_THROW_ON_ERROR);
        if (hash_file('sha256', $private->path($manifest['archive'])) !== $manifest['sha256']) {
            throw new RuntimeException('Media recovery copy integrity mismatch.');
        }
        $stream = $private->readStream($manifest['archive']);
        try {
            if (! $public->put($path, $stream)) {
                throw new RuntimeException('Cannot restore public media.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        DB::table('media_assets')->where('path', $path)->update(['state' => 'available']);
    }

    private function referenceKey(string $path): string
    {
        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }

    private function originalPath(string $path): string
    {
        return str_replace('/thumbs/', '/', $path);
    }

    private function family(string $path): array
    {
        $original = $this->originalPath($path);

        return array_values(array_unique([$original, dirname($original).'/thumbs/'.basename($original)]));
    }

    private function pendingKey(string $componentId): string
    {
        return 'media-pending/'.hash('sha256', (string) auth()->id().'|'.$componentId).'.json';
    }

    private function pathManifest(string $path): string
    {
        return 'media-trash/manifests/'.hash('sha256', $path).'.json';
    }

    private function recordManifest(Model $record): string
    {
        return 'media-trash/records/'.hash('sha256', $record::class.'|'.$record->getKey()).'.json';
    }
}
