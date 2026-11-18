<x-filament-widgets::widget>
    <div style="display: grid; gap: 1.5rem;">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
            <h2 style="font-size: 1.125rem; font-weight: 600;">Acesso rápido</h2>
            <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="refreshStatistics" wire:loading.attr="disabled" wire:target="refreshStatistics">Atualizar indicadores</x-filament::button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr)); gap: 1.5rem;">
            @forelse($report['modules'] as $module)
                <x-filament::section :heading="$module['label']" :icon="$module['icon']" :data-content-module="$module['source']">
                    <dl style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div><dt style="font-size: .875rem;">Cadastrados</dt><dd style="font-size: 1.75rem; font-weight: 600;">{{ number_format($module['total'], 0, ',', '.') }}</dd></div>
                        <div><dt style="font-size: .875rem;">Visualizações</dt><dd style="font-size: 1.75rem; font-weight: 600;">{{ number_format($module['visits'], 0, ',', '.') }}</dd></div>
                    </dl>
                    <div style="display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem;">
                        <x-filament::badge color="success">Ativos: {{ number_format($module['active'], 0, ',', '.') }}</x-filament::badge>
                        <x-filament::badge color="danger">Inativos: {{ number_format($module['inactive'], 0, ',', '.') }}</x-filament::badge>
                        @if($module['other_status'])<x-filament::badge color="gray">Outros status: {{ number_format($module['other_status'], 0, ',', '.') }}</x-filament::badge>@endif
                        @if($module['scheduled'])<x-filament::badge color="info">Agendadas: {{ number_format($module['scheduled'], 0, ',', '.') }}</x-filament::badge>@endif
                    </div>
                    <x-slot name="footer">
                        <div style="display: flex; flex-wrap: wrap; gap: .75rem;">
                            <x-filament::button tag="a" color="gray" :href="$module['index_url']" :aria-label="'Ver listagem de '.$module['label']">Listagem</x-filament::button>
                            @if($module['create_url'])<x-filament::button tag="a" icon="heroicon-o-plus" :href="$module['create_url']" :aria-label="'Cadastrar em '.$module['label']">Cadastrar</x-filament::button>@endif
                        </div>
                    </x-slot>
                </x-filament::section>
            @empty
                <p>Nenhum módulo de conteúdo disponível.</p>
            @endforelse
        </div>
    </div>
</x-filament-widgets::widget>
