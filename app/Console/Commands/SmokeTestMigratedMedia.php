<?php

namespace App\Console\Commands;

use App\Services\Migration\CanonicalLegacyMedia;
use App\Services\Migration\SnapshotManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SmokeTestMigratedMedia extends Command
{
    protected $signature = 'legacy:smoke-media';

    protected $description = 'Verifica via HTTP todos os uploads existentes, sem transferir o corpo dos arquivos';

    public function handle(Kernel $kernel): int
    {
        config(['session.driver' => 'array', 'migration.record_visits' => false]);
        $snapshot = app(SnapshotManager::class)->current();
        $tested = 0;
        $failures = [];
        $requests = [];
        foreach (CanonicalLegacyMedia::load($snapshot)->destinations as $path => $file) {
            $requests['storage/'.$path] = $file;
            $requests[$file['source']] = $file;
        }
        foreach ($requests as $path => $expected) {
            $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
            $request = Request::create(rtrim(config('app.url'), '/').'/'.$encoded, 'HEAD');
            $response = $kernel->handle($request);
            if ($response->getStatusCode() !== 200 || (int) $response->headers->get('Content-Length') !== $expected['bytes']) {
                $failures[] = ['path' => $path, 'status' => $response->getStatusCode(), 'expected_bytes' => $expected['bytes'], 'actual_bytes' => $response->headers->get('Content-Length')];
            }
            $kernel->terminate($request, $response);
            $tested++;
            if ($tested % 1000 === 0) {
                $this->line('Arquivos verificados: '.$tested);
            }
        }
        $report = ['tested' => $tested, 'passed' => $failures === [], 'failures' => $failures];
        File::put(storage_path('app/private/migration/media-http-report.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->line(json_encode(['tested' => $tested, 'passed' => $failures === [], 'failure_count' => count($failures), 'first_failures' => array_slice($failures, 0, 5)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
