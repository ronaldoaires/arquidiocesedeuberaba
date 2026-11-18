<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ $record->subject }}</x-slot>
        <p><strong>Destinatários:</strong> {{ number_format($recipientCount, 0, ',', '.') }} inscrito(s) ativo(s) com e-mail confirmado.</p>
        <p><strong>Anexos:</strong>
            {{ collect($record->attachments)->map(fn ($path) => $record->attachment_names[$path] ?? basename($path))->implode(', ') ?: 'Nenhum' }}
        </p>
        @if(in_array(config('mail.mailers.'.config('mail.default').'.transport'), ['array', 'log'], true))
            <p role="status">Modo de simulação: o resultado ficará no histórico e os inscritos não receberão e-mails.</p>
        @endif
        @if($warning)<p role="alert">{{ $warning }}</p>@endif
    </x-filament::section>
    @if($html)
        <iframe sandbox="" referrerpolicy="no-referrer" title="Mensagem revisada" srcdoc="{{ $html }}" style="width:100%;height:52vh;border:0"></iframe>
        <p>Cada destinatário receberá também os links pessoais para atualizar os dados e cancelar a inscrição.</p>
    @endif
    <div style="display:flex;flex-wrap:wrap;gap:.75rem">
        <x-filament::button tag="a" color="gray" :href="$this->getResourceUrl('index')">Voltar para campanhas</x-filament::button>
        <x-filament::button type="button" wire:click="confirm" wire:loading.attr="disabled" :disabled="filled($warning)">Confirmar envio</x-filament::button>
    </div>
</x-filament-panels::page>
