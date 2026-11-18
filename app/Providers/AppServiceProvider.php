<?php

namespace App\Providers;

use App\Models\SiteSetting;
use App\Services\ContentDashboard;
use App\Services\ContentViewAnalytics;
use App\Services\MediaLifecycle;
use App\Services\YouTubeVideoMetadata;
use App\Support\ContentRegistry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ContentDashboard::class);
        $this->app->scoped(ContentViewAnalytics::class);
        $this->app->scoped(MediaLifecycle::class);
        $this->app->scoped(YouTubeVideoMetadata::class);
    }

    public function boot(): void
    {
        View::composer('site.layouts.app', function (\Illuminate\View\View $view): void {
            $ready = Schema::hasTable('site_settings');
            $menus = [];
            foreach (['pagina', 'pagina_categoria', 'noticia_categoria', 'clero_categoria', 'paroquia_cidade', 'paroquia_forania', 'instituto_categoria', 'pastoral'] as $source) {
                $class = ContentRegistry::model($source);
                $query = $ready ? $class::published() : null;
                if ($query) {
                    if ($source === 'pagina') {
                        $query->orderBy('sort_order')->orderBy('title');
                    } elseif ($source === 'paroquia_cidade') {
                        $query->orderBy('name');
                    } elseif ($source === 'pastoral') {
                        $query->orderBy('title');
                    } else {
                        $query->orderBy('id');
                    }
                }
                $menus[$source] = $query ? $query->get() : collect();
            }
            $view->with('settings', $ready ? SiteSetting::first() : null)->with('menus', $menus);
        });
    }
}
