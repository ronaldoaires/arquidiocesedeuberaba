<?php

namespace App\Console\Commands;

use App\Services\Migration\MigrationVerifier;
use Illuminate\Console\Command;

class VerifyLegacyMigration extends Command
{
    protected $signature = 'legacy:verify';

    protected $description = 'Verifica registros, conteúdo arquivado, relacionamentos e hashes de todos os uploads';

    public function handle(MigrationVerifier $verifier): int
    {
        $report = $verifier->run();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
