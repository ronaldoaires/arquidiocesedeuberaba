<?php

namespace App\Filament\Widgets;

use App\Filament\Support\ContentResource;
use App\Services\ContentDashboard;
use App\Services\ContentViewAnalytics;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Locked;

class ContentViewsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '360px';

    protected string $view = 'filament.widgets.content-views-chart';

    public int $year;

    public int $month;

    #[Locked]
    public string $source = 'all';

    #[Locked]
    public string $granularity = 'monthly';

    public static function canView(): bool
    {
        return ContentResource::canViewAny();
    }

    public function mount(): void
    {
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $this->year = $now->year;
        $this->month = $now->month;
        parent::mount();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        return app(ContentViewAnalytics::class)->chart($this->year, $this->month, $this->source, $this->granularity);
    }

    public function getHeading(): string
    {
        return 'Visualizações de conteúdo';
    }

    public function getDescription(): string
    {
        return 'Visualizações acumuladas dos conteúdos, agrupadas pela data de cadastro.';
    }

    protected function getOptions(): array
    {
        return ['locale' => 'pt-BR', 'animation' => ['duration' => 0], 'responsive' => true, 'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]]]];
    }

    protected function getViewData(): array
    {
        $analytics = app(ContentViewAnalytics::class);
        $available = $analytics->availability();

        return ['available' => $available, 'tabs' => ['all' => 'Tudo', ...$analytics->labels()]];
    }

    public function selectSource(string $source): void
    {
        abort_unless(in_array($source, ['all', ...ContentDashboard::MODULE_ORDER], true), 422);
        $this->source = $source;
        $this->refreshReport();
    }

    public function selectGranularity(string $granularity): void
    {
        abort_unless(in_array($granularity, ['monthly', 'daily'], true), 422);
        $this->granularity = $granularity;
        $this->refreshReport();
    }

    public function updatedYear(): void
    {
        $this->normalizeSelection(false);
        $this->refreshReport();
    }

    public function updatedMonth(): void
    {
        $this->normalizeSelection(true);
        $this->refreshReport();
    }

    public function refreshReport(): void
    {
        app(ContentViewAnalytics::class)->clear();
        $this->cachedData = null;
        $this->updateChartData();
    }

    private function normalizeSelection(bool $validateMonth): void
    {
        $available = app(ContentViewAnalytics::class)->availability();
        $this->resetErrorBag();
        if (! in_array($this->year, $available['years'], true)) {
            $this->year = CarbonImmutable::now('America/Sao_Paulo')->year;
            $this->addError('year', 'Selecione um ano disponível.');
        }
        $months = $available['months'][$this->year];
        if (! in_array($this->month, $months, true)) {
            $this->month = $months[0];
            if ($validateMonth) {
                $this->addError('month', 'Selecione um mês disponível.');
            }
        }
    }
}
