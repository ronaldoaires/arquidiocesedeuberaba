<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlContent
{
    public function render(string $html): string
    {
        $html = preg_replace_callback(
            '~https?://(?:www\\.)?arquidiocesedeuberaba\\.(?:com|org)\\.br/(uploads/[^\\s"\'<>]+)~i',
            function (array $match): string {
                $path = rawurldecode(ltrim(parse_url($match[0], PHP_URL_PATH), '/'));

                return Storage::disk('public')->exists('legacy/'.$path) ? url($match[1]) : $match[0];
            }, $html,
        );
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="content-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        foreach ((new DOMXPath($document))->query('//*[@style]') as $node) {
            $style = $node->getAttribute('style');
            foreach (['width', 'height'] as $dimension) {
                if (preg_match('/(?:^|;)\\s*'.$dimension.'\\s*:\\s*(\\d+)px/i', $style, $match)) {
                    $node->setAttribute($dimension, $match[1]);
                }
            }
        }
        foreach ((new DOMXPath($document))->query('//iframe') as $node) {
            $host = strtolower(parse_url($node->getAttribute('src'), PHP_URL_HOST) ?? '');
            if (! in_array($host, ['www.google.com', 'maps.google.com', 'www.youtube.com', 'www.youtube-nocookie.com', 'open.spotify.com', 'liturgiadiaria.edicoescnbb.com.br'], true)) {
                $node->parentNode->removeChild($node);
            }
        }
        $root = $document->getElementById('content-root');
        $result = '';
        if ($root) {
            foreach ($root->childNodes as $node) {
                $result .= $document->saveHTML($node);
            }
        }
        $config = app(HtmlSanitizerConfig::class)
            ->allowElement('iframe', ['src', 'width', 'height', 'title', 'loading', 'allowfullscreen', 'referrerpolicy'])
            ->allowElement('audio', ['src', 'controls', 'preload'])
            ->allowElement('video', ['src', 'controls', 'preload', 'poster', 'width', 'height'])
            ->allowElement('source', ['src', 'type']);

        return (new HtmlSanitizer($config))->sanitize($result);
    }
}
