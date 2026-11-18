<?php

namespace App\Console\Commands;

use App\Services\Migration\LegacyImporter;
use Illuminate\Console\Command;

class ImportLegacySnapshot extends Command
{
    protected $signature = 'legacy:import';

    protected $description = 'Importa o snapshot restaurado e transfere arquivos com verificação de integridade';

    public function handle(LegacyImporter $importer): int
    {
        $report = $importer->run();
        $this->info('Importação concluída: '.$report['records'].' registros e '.$report['files'].' arquivos.');

        return self::SUCCESS;
    }
}
