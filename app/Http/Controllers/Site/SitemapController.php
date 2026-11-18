<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\ContentRegistry;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $writer = new \XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach (['/', '/noticias', '/artigos-dom-paulo', '/paroquias', '/galerias', '/agenda', '/correio-catolico', '/central-de-midias', '/videos', '/liturgia-diaria', '/contato'] as $path) {
            $writer->startElement('url');
            $writer->writeElement('loc', url($path));
            $writer->endElement();
        }
        foreach (ContentRegistry::all() as $source => $module) {
            if (! $module['route']) {
                continue;
            }
            $class = ContentRegistry::model($source);
            foreach ($class::published()->get() as $record) {
                if (! $record->slug) {
                    continue;
                }
                $writer->startElement('url');
                $writer->writeElement('loc', $record->public_url);
                if ($record->updated_at ?? $record->created_at) {
                    $writer->writeElement('lastmod', ($record->updated_at ?? $record->created_at)->toAtomString());
                }
                $writer->endElement();
            }
        }
        $writer->endElement();
        $writer->endDocument();

        return response($writer->outputMemory(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
