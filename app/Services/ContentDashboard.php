<?php

namespace App\Services;

use App\Filament\Support\ContentResource;
use App\Support\ContentRegistry;
use Filament\Facades\Filament;

class ContentDashboard
{
    public const MODULE_ORDER = ['noticia', 'artigo', 'evento', 'pagina', 'paroquia', 'slide'];

    private ?array $report = null;

    public function report(): array
    {
        abort_unless(ContentResource::canViewAny(), 403);
        if ($this->report !== null) {
            return $this->report;
        }
        $modules = [];
        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            if (! is_subclass_of($resource, ContentResource::class) || ! $resource::shouldRegisterNavigation() || ! in_array($resource::$sourceTable, self::MODULE_ORDER, true) || ! $resource::canViewAny()) {
                continue;
            }
            $source = $resource::$sourceTable;
            $definition = ContentRegistry::get($source);
            $fields = array_column($definition['columns'], 'name');
            $model = $resource::getModel();
            $expressions = ['COUNT(*) AS total'];
            $expressions[] = in_array('is_active', $fields, true) ? 'COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_count' : '0 AS active_count';
            $expressions[] = in_array('is_active', $fields, true) ? 'COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS inactive_count' : '0 AS inactive_count';
            $expressions[] = in_array('visits', $fields, true) ? 'COALESCE(SUM(visits), 0) AS visit_count' : '0 AS visit_count';
            $query = $model::query()->selectRaw(implode(', ', $expressions));
            if ($source === 'noticia') {
                $query->selectRaw('COALESCE(SUM(CASE WHEN is_active = 1 AND created_at > ? THEN 1 ELSE 0 END), 0) AS scheduled_count', [now()]);
            }
            $counts = $query->toBase()->first();
            $total = (int) $counts->total;
            $active = (int) $counts->active_count;
            $inactive = (int) $counts->inactive_count;
            $modules[] = [
                'source' => $source, 'label' => $resource::getPluralModelLabel(),
                'icon' => match ($source) {
                    'noticia' => 'heroicon-o-newspaper', 'artigo' => 'heroicon-o-document-text', 'pagina' => 'heroicon-o-document-duplicate',
                    'evento' => 'heroicon-o-calendar-days', 'slide' => 'heroicon-o-photo', 'paroquia' => 'heroicon-o-building-library',
                    default => 'heroicon-o-document-text',
                },
                'total' => $total, 'active' => $active, 'inactive' => $inactive, 'other_status' => max(0, $total - $active - $inactive),
                'visits' => (int) $counts->visit_count, 'scheduled' => (int) ($counts->scheduled_count ?? 0),
                'index_url' => $resource::getUrl('index'),
                'create_url' => $resource::canCreate() ? ($resource::hasPage('create') ? $resource::getUrl('create') : $resource::getUrl('index', ['action' => 'create'])) : null,
            ];
        }
        usort($modules, fn (array $first, array $second): int => array_search($first['source'], self::MODULE_ORDER, true) <=> array_search($second['source'], self::MODULE_ORDER, true));
        $totals = [];
        foreach (['total', 'active', 'inactive', 'visits', 'other_status'] as $key) {
            $totals[$key] = array_sum(array_column($modules, $key));
        }

        return $this->report = ['modules' => $modules, 'totals' => $totals];
    }

    public function clear(): void
    {
        $this->report = null;
    }
}
