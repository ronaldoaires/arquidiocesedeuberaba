<?php

namespace App\Services;

use App\Filament\Support\ContentResource;
use App\Support\ContentRegistry;
use Carbon\CarbonImmutable;

class ContentViewAnalytics
{
    private array $cachedRows = [];

    private ?array $available = null;

    public function labels(): array
    {
        return collect(ContentDashboard::MODULE_ORDER)->mapWithKeys(fn (string $source): array => [$source => ContentRegistry::get($source)['label']])->all();
    }

    public function availability(): array
    {
        $this->authorize();
        if ($this->available !== null) {
            return $this->available;
        }
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $months = [];
        foreach (ContentDashboard::MODULE_ORDER as $source) {
            $model = ContentRegistry::model($source);
            $periods = $model::query()->whereNotNull('created_at')->selectRaw('SUBSTR(created_at, 1, 7) AS period')->groupBy('period')->toBase()->pluck('period');
            foreach ($periods as $period) {
                if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $matches) && (int) $matches[1] >= 1900 && (int) $matches[1] <= 9998) {
                    $months[(int) $matches[1]][] = (int) $matches[2];
                }
            }
        }
        $months[$now->year][] = $now->month;
        foreach ($months as &$values) {
            $values = array_values(array_unique($values));
            rsort($values);
        }
        unset($values);
        krsort($months);

        return $this->available = ['years' => array_keys($months), 'months' => $months];
    }

    public function chart(int $year, int $month, string $source, string $granularity): array
    {
        $this->authorize();
        abort_unless($year >= 1900 && $year <= 9998 && $month >= 1 && $month <= 12
            && in_array($source, ['all', ...ContentDashboard::MODULE_ORDER], true)
            && in_array($granularity, ['monthly', 'daily'], true), 422);
        $monthly = $granularity === 'monthly';
        $start = CarbonImmutable::create($year, $monthly ? 1 : $month, 1, 0, 0, 0, 'America/Sao_Paulo');
        $end = $monthly ? $start->addYear() : $start->addMonth();
        $labels = [];
        $prefixes = [];
        for ($index = 1, $count = $monthly ? 12 : $start->daysInMonth; $index <= $count; $index++) {
            $date = $monthly ? $start->month($index) : $start->day($index);
            $labels[] = $monthly ? $date->locale('pt_BR')->translatedFormat('M') : $date->format('d/m');
            $prefixes[] = $date->format($monthly ? 'Y-m' : 'Y-m-d');
        }
        $colors = ['noticia' => '#8f3234', 'artigo' => '#2563eb', 'evento' => '#0f766e', 'pagina' => '#b45309', 'paroquia' => '#7c3aed', 'slide' => '#475569'];
        $datasets = [];
        foreach ($this->labels() as $key => $label) {
            if ($source !== 'all' && $source !== $key) {
                continue;
            }
            $cacheKey = $key.':'.$start->toDateString().':'.$granularity;
            if (! array_key_exists($cacheKey, $this->cachedRows)) {
                $model = ContentRegistry::model($key);
                $length = $monthly ? 7 : 10;
                $this->cachedRows[$cacheKey] = $model::query()->where('created_at', '>=', $start->toDateTimeString())
                    ->where('created_at', '<', $end->toDateTimeString())
                    ->selectRaw("SUBSTR(created_at, 1, {$length}) AS period, COALESCE(SUM(visits), 0) AS views")
                    ->groupBy('period')->toBase()->pluck('views', 'period')->all();
            }
            $data = array_map(fn (string $period): int => (int) ($this->cachedRows[$cacheKey][$period] ?? 0), $prefixes);
            $datasets[] = ['label' => $label, 'data' => $data, 'backgroundColor' => $colors[$key], 'borderColor' => $colors[$key], 'borderWidth' => 1];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }

    public function clear(): void
    {
        $this->cachedRows = [];
        $this->available = null;
    }

    private function authorize(): void
    {
        abort_unless(ContentResource::canViewAny(), 403);
    }
}
