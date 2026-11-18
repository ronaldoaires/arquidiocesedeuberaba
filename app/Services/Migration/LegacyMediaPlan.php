<?php

namespace App\Services\Migration;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;

class LegacyMediaPlan
{
    private array $files = [];

    private array $byLowerPath = [];

    private array $used = [];

    private array $references = [];

    private array $reservations = [];

    private array $copies = [];

    public function __construct(private string $sourceRoot, private string $publicRoot, private array $hosts)
    {
        foreach (File::allFiles($sourceRoot.'/uploads') as $file) {
            $path = 'uploads/'.str_replace('\\', '/', $file->getRelativePathname());
            $this->files[$path] = $file->getSize();
            $this->byLowerPath[mb_strtolower($path)][] = $path;
        }
        ksort($this->files);
    }

    public function scanRecord(string $source, array $module, array $row): void
    {
        $id = $row['id'] ?? implode('/', [$row['id_paroquia'] ?? '', $row['id_clero'] ?? '']);
        $columns = array_column($module['columns'], null, 'source');
        foreach ($row as $field => $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $target = $columns[$field]['name'] ?? null;
            $owner = ['table' => $source, 'id' => $id, 'field' => $field, 'target_field' => $target];
            if (in_array($target, ['cover_path', 'photo_path', 'file_path'], true)) {
                $path = $this->uploadPath($value);
                if ($path === null && ! preg_match('~^(?:[a-z][a-z0-9+.-]*:|[/\\\\])~i', $value)) {
                    $path = 'uploads/'.$module['media'].'/'.trim($value);
                }
                if ($path === null) {
                    $this->references[] = $owner + ['status' => 'review_nonlocal_field'];

                    continue;
                }
                $this->record($path, 'content/'.$module['table'], $owner, true);

                continue;
            }
            if (stripos($value, 'uploads/') === false && stripos($value, 'uploads%2f') === false) {
                continue;
            }
            foreach ($this->urls($value) as $url) {
                $urlPath = rawurldecode((string) parse_url($url, PHP_URL_PATH));
                if (preg_match('~(?:^|/)wp-content/uploads/~i', $urlPath) && $this->localUrl($url)) {
                    $this->references[] = $owner + ['source' => $urlPath, 'status' => 'review_wordpress_url'];

                    continue;
                }
                if (($path = $this->uploadPath($url)) !== null) {
                    $image = in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'], true);
                    $directory = $image ? 'editor/'.$module['table'] : 'editor/documents';
                    $this->record($path, $directory, $owner + ['url' => $url], false);
                }
            }
        }
    }

    private function urls(string $value): array
    {
        if (! str_contains($value, '<')) {
            return [trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))];
        }
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$value, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $urls = [];
        foreach ((new DOMXPath($document))->query('//@src|//@href|//@poster|//@data-id|//@data-src|//@data|//@srcset|//@style|//style') as $node) {
            $text = $node->nodeValue;
            if ($node->nodeName === 'srcset') {
                foreach (explode(',', $text) as $entry) {
                    $urls[] = preg_split('/\s+/', trim($entry))[0];
                }
            } elseif ($node->nodeName === 'style') {
                preg_match_all('~url\(\s*([\'"]?)(.*?)\1\s*\)~is', $text, $matches);
                array_push($urls, ...$matches[2]);
            } else {
                $urls[] = $text;
            }
        }

        return array_unique($urls);
    }

    private function localUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true))
            || (isset($parts['host']) && ! in_array(strtolower($parts['host']), $this->hosts, true))) {
            return false;
        }

        return true;
    }

    private function uploadPath(string $url): ?string
    {
        if (! $this->localUrl($url)) {
            return null;
        }
        $path = rawurldecode((string) parse_url(trim($url), PHP_URL_PATH));
        if (preg_match('~(?:^|/)wp-content/uploads/~i', $path)) {
            return null;
        }

        return preg_match('~(?:^|/)uploads/(.+)$~i', $path, $match) ? 'uploads/'.$match[1] : null;
    }

    public function scanTemplates(): void
    {
        foreach (['templates', 'sistema'] as $directory) {
            foreach (File::allFiles($this->sourceRoot.'/'.$directory) as $file) {
                if (! in_array(strtolower($file->getExtension()), ['php', 'html', 'twig', 'css', 'js'], true)) {
                    continue;
                }
                $body = File::get($file->getPathname());
                // Literal paths only. Concatenations with database fields are covered by scanRecord().
                preg_match_all('~(?:https?://[^/\'"\s<>]+/|(?<![\w/])/?)(uploads/[^\'"{}()<>?#\r\n]*?\.[a-z0-9]{2,6})(?=[\'")?#\s<]|$)~i', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
                foreach ($matches as $match) {
                    $path = $this->uploadPath($match[0][0]);
                    if ($path === null) {
                        continue;
                    }
                    $owner = ['template' => $directory.'/'.str_replace('\\', '/', $file->getRelativePathname()), 'line' => 1 + substr_count(substr($body, 0, $match[0][1]), "\n")];
                    $this->record($path, 'content/site_settings', $owner, false);
                }
            }
        }
    }

    private function record(string $requested, string $directory, array $owner, bool $includeThumb): void
    {
        if (preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\\\\:\x00-\x1f]|//~', $requested)) {
            $this->references[] = $owner + ['source' => $requested, 'status' => 'review_invalid_path'];

            return;
        }
        $path = $requested;
        if (! isset($this->files[$path])) {
            $variants = $this->byLowerPath[mb_strtolower($path)] ?? [];
            if (count($variants) !== 1) {
                foreach ($variants as $variant) {
                    $this->used[$variant] = true;
                }
                $recovery = $this->publicRoot.'/legacy/'.$requested;
                $status = $variants ? 'review_ambiguous_case' : (is_file($recovery) ? 'available_in_laravel_legacy' : 'missing');
                $this->references[] = $owner + ['source' => $requested, 'directory' => $directory, 'status' => $status];
                if ($includeThumb) {
                    $candidate = dirname($requested).'/thumbs/'.basename($requested);
                    foreach ($this->byLowerPath[mb_strtolower($candidate)] ?? [] as $thumb) {
                        $this->used[$thumb] = true;
                        $this->references[] = $owner + ['source' => $thumb, 'status' => 'review_thumbnail_without_original'];
                    }
                }

                return;
            }
            $path = $variants[0];
        }
        $this->used[$path] = true;
        $destination = $this->destination($path, $directory);
        $this->references[] = $owner + ['source' => $requested, 'matched' => $path, 'destination' => $destination, 'status' => $path === $requested ? 'found' : 'found_case_variant'];
        $thumb = dirname($path).'/thumbs/'.basename($path);
        if ($includeThumb && isset($this->byLowerPath[mb_strtolower($thumb)])) {
            foreach ($this->byLowerPath[mb_strtolower($thumb)] as $actualThumb) {
                $this->used[$actualThumb] = true;
                $thumbDestination = dirname($destination).'/thumbs/'.basename($destination);
                if (! $this->available($actualThumb, $thumbDestination)) {
                    $this->references[] = $owner + ['source' => $actualThumb, 'destination' => $thumbDestination, 'status' => 'review_thumbnail_conflict'];

                    continue;
                }
                $this->reserve($actualThumb, $thumbDestination);
            }
        }
    }

    private function destination(string $path, string $directory): string
    {
        $name = basename($path);
        $destination = $directory.'/'.$name;
        if (! $this->available($path, $destination)) {
            $stem = pathinfo($name, PATHINFO_FILENAME);
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $suffix = substr(hash('sha256', $path), 0, 12);
            $index = 0;
            do {
                $destination = $directory.'/'.$stem.'-'.$suffix.($index ? '-'.$index : '').($extension === '' ? '' : '.'.$extension);
                $index++;
            } while (! $this->available($path, $destination));
        }
        $this->reserve($path, $destination);

        return $destination;
    }

    private function available(string $path, string $destination): bool
    {
        $key = mb_strtolower($destination);
        if (isset($this->reservations[$key])) {
            return $this->reservations[$key] === $path;
        }
        $existing = $this->publicRoot.'/'.$destination;

        return ! file_exists($existing) || (is_file($existing) && filesize($existing) === $this->files[$path]
            && hash_file('sha256', $existing) === hash_file('sha256', $this->sourceRoot.'/'.$path));
    }

    private function reserve(string $source, string $destination): void
    {
        $this->reservations[mb_strtolower($destination)] = $source;
        $this->copies[$destination] = ['source' => $source, 'bytes' => $this->files[$source]];
    }

    public function report(): array
    {
        ksort($this->copies);
        $unreferenced = array_diff_key($this->files, $this->used);

        return [
            'summary' => [
                'inventoried_files' => count($this->files),
                'referenced_files' => count($this->used),
                'unreferenced_candidates' => count($unreferenced),
                'unreferenced_bytes' => array_sum($unreferenced),
                'planned_destinations' => count($this->copies),
                'reference_statuses' => array_count_values(array_column($this->references, 'status')),
            ],
            'references' => $this->references,
            'destinations' => $this->copies,
            'unreferenced_candidates_bytes_by_path' => $unreferenced,
        ];
    }
}
