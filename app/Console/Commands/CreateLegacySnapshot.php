<?php

namespace App\Console\Commands;

use App\Services\Migration\SnapshotManager;
use Illuminate\Console\Command;

class CreateLegacySnapshot extends Command
{
    protected $signature = 'legacy:snapshot';

    protected $description = 'Cria backup consistente, copia arquivos e verifica a restauração isolada';

    public function handle(SnapshotManager $manager): int
    {
        $this->info('Capturando banco e arquivos; as escritas no MySQL serão bloqueadas temporariamente.');
        $snapshot = $manager->create(config('migration.source_path'));
        $this->info('Backup e restauração verificados: '.$snapshot['manifest']['id']);
        $this->line($snapshot['path']);

        return self::SUCCESS;
    }
}
