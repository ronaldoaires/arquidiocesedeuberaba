<?php

namespace App\Console\Commands;

use App\Support\ContentRegistry;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SmokeTestMigratedSite extends Command
{
    protected $signature = 'legacy:smoke';

    protected $description = 'Verifica todas as páginas públicas migradas sem alterar contadores ou conteúdo';

    public function handle(Kernel $kernel): int
    {
        config(['migration.record_visits' => false]);
        $urls = ['/' => 200, '/noticias' => 200, '/artigos-dom-paulo' => 200, '/paroquias' => 200, '/galerias' => 200, '/agenda' => 200, '/correio-catolico' => 200, '/search?q=igreja' => 200, '/contato' => 200, '/solicitar' => 200, '/videos' => 200, '/central-de-midias' => 200, '/liturgia-diaria' => 200];
        foreach (ContentRegistry::all() as $source => $module) {
            if (! $module['route']) {
                continue;
            }
            $class = ContentRegistry::model($source);
            foreach ($class::all() as $record) {
                if (! $record->slug) {
                    continue;
                }
                $expected = $record->is_active && ($source !== 'noticia' || $record->created_at <= now()) ? 200 : 404;
                $urls['/'.$module['route'].'/'.$record->slug] = $expected;
            }
        }
        foreach (['pagina_categoria' => ['pagina/categoria'], 'noticia_categoria' => ['categoria/noticias'], 'clero_categoria' => ['cleros'], 'instituto_categoria' => ['institutos'], 'paroquia_cidade' => ['paroquias/cidade'], 'paroquia_forania' => ['foranias']] as $source => [$prefix]) {
            $class = ContentRegistry::model($source);
            foreach ($class::published()->get() as $record) {
                $urls['/'.$prefix.'/'.$record->slug] = 200;
            }
        }
        $failures = [];
        $tested = 0;
        foreach ($urls as $path => $expected) {
            $request = Request::create(rtrim(config('app.url'), '/').$path, 'GET');
            $response = $kernel->handle($request);
            if ($response->getStatusCode() !== $expected) {
                $failures[] = ['path' => $path, 'expected' => $expected, 'actual' => $response->getStatusCode()];
            }
            $kernel->terminate($request, $response);
            $tested++;
            if ($tested % 100 === 0) {
                $this->line('Páginas verificadas: '.$tested);
            }
        }
        $report = ['tested' => $tested, 'passed' => $failures === [], 'failures' => $failures];
        File::put(storage_path('app/private/migration/route-report.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
