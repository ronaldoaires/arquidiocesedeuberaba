<?php

namespace App\Filament\Widgets;

use App\Filament\Support\ContentResource;
use App\Services\ContentDashboard;
use Filament\Widgets\Widget;

class SiteOverview extends Widget
{
    protected static ?int $sort = 1;

    protected static ?bool $isLazyLoaded = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.site-overview';

    public static function canView(): bool
    {
        return ContentResource::canViewAny();
    }

    protected function getViewData(): array
    {
        return ['report' => app(ContentDashboard::class)->report()];
    }

    public function refreshStatistics(): void
    {
        app(ContentDashboard::class)->clear();
    }
}
