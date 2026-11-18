<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section :heading="$this->getHeading()" :description="$this->getDescription()">
            <div style="display: flex; flex-wrap: wrap; align-items: end; gap: 1rem;">
                <div><label for="content-view-year" style="display: block; margin-bottom: .5rem;">Ano</label>
                    <x-filament::input.wrapper :valid="!$errors->has('year')"><x-filament::input.select id="content-view-year" wire:model.live="year">
                        @foreach($available['years'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach
                    </x-filament::input.select></x-filament::input.wrapper>
                    @error('year')<p role="alert">{{ $message }}</p>@enderror
                </div>
                <div><label for="content-view-month" style="display: block; margin-bottom: .5rem;">Mês</label>
                    <x-filament::input.wrapper :valid="!$errors->has('month')"><x-filament::input.select id="content-view-month" wire:model.live="month">
                        @foreach($available['months'][$year] as $value)<option value="{{ $value }}">{{ \Carbon\CarbonImmutable::create(2000,$value,1)->locale('pt_BR')->translatedFormat('F') }}</option>@endforeach
                    </x-filament::input.select></x-filament::input.wrapper>
                    @error('month')<p role="alert">{{ $message }}</p>@enderror
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: .5rem;">
                    <x-filament::button :color="$granularity==='monthly'?'primary':'gray'" :aria-pressed="$granularity==='monthly'?'true':'false'" wire:click="selectGranularity('monthly')">Por mês</x-filament::button>
                    <x-filament::button :color="$granularity==='daily'?'primary':'gray'" :aria-pressed="$granularity==='daily'?'true':'false'" wire:click="selectGranularity('daily')">Por dia</x-filament::button>
                    <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="refreshReport">Atualizar</x-filament::button>
                </div>
            </div>
            <div style="overflow-x: auto; margin-top: 1.5rem;">
                <x-filament::tabs label="Tipo de conteúdo" role="tablist">
                    @foreach($tabs as $key => $label)
                        <x-filament::tabs.item :active="$source===$key" role="tab" :aria-selected="$source===$key?'true':'false'" wire:click="selectSource('{{ $key }}')">{{ $label }}</x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            </div>

        <div class="fi-wi-chart-image" role="img" aria-label="{{ $this->getDescription() }} {{ $granularity === 'monthly' ? 'Por mês em '.$year : 'Por dia no mês '.$month.'/'.$year }}" style="margin-top: 1.5rem;">
            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                data-chart-type="bar"
                x-data="chart({ cachedData: @js($this->getCachedData()), options: @js($this->getOptions()), type: 'bar' })"
                class="fi-wi-chart-frame fi-wi-chart-canvas-ctn fi-wi-chart-frame-no-aspect-ratio"
                style="height: 360px;"
            >
                <canvas x-ref="canvas" aria-hidden="true" style="width: 100%; max-height: 360px;"></canvas>
                <span aria-hidden="true" x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                <span aria-hidden="true" x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                <span aria-hidden="true" x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                <span aria-hidden="true" x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
                <span aria-hidden="true" x-ref="tooltipBackgroundColorElement" class="fi-wi-chart-tooltip-bg-color"></span>
                <span aria-hidden="true" x-ref="tooltipTextColorElement" class="fi-wi-chart-tooltip-text-color"></span>
                <span aria-hidden="true" x-ref="tooltipBorderColorElement" class="fi-wi-chart-tooltip-border-color"></span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
