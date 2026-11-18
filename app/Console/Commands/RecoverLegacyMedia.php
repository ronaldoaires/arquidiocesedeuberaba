<?php

namespace App\Console\Commands;

use App\Services\Migration\LegacyMediaRecovery;
use Illuminate\Console\Command;

class RecoverLegacyMedia extends Command
{
    protected $signature = 'legacy:recover-media';

    protected $description = 'Tenta recuperar mídias ausentes somente nos domínios públicos conhecidos do legado';

    public function handle(LegacyMediaRecovery $recovery): int
    {
        $report = $recovery->run();
        $this->info('Endereços verificados: '.$report['attempted'].'; recuperados: '.count($report['recovered']).'; pendentes: '.count($report['unresolved']));

        return self::SUCCESS;
    }
}
