<?php

use App\Filament\Widgets\ContentViewsChart;
use App\Models\Article;
use App\Models\NewsItem;
use App\Models\User;
use App\Services\ContentDashboard;
use App\Services\ContentViewAnalytics;
use App\Support\ContentRegistry;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::withoutLazyLoading();
});

it('groups cumulative views by registration month and day for each selected module', function (string $source): void {
    $model = ContentRegistry::model($source);
    $record = $model::factory()->create(['visits' => 900, 'created_at' => '2025-03-15 08:00:00', 'is_active' => true]);
    $model::factory()->create(['visits' => 35, 'created_at' => '2025-03-15 20:00:00', 'is_active' => false]);
    $model::factory()->create(['visits' => 65, 'created_at' => '2025-03-31 23:59:59']);
    $model::factory()->create(['visits' => 700, 'created_at' => '2025-04-01 00:00:00']);
    $model::factory()->create(['visits' => 2000, 'created_at' => '2024-03-15 08:00:00']);
    $service = app(ContentViewAnalytics::class);
    $monthly = $service->chart(2025, 3, $source, 'monthly');
    expect($monthly['datasets'])->toHaveCount(1)->and($monthly['datasets'][0]['data'][2])->toBe(1000)->and($monthly['datasets'][0]['data'][3])->toBe(700)->and(array_sum($monthly['datasets'][0]['data']))->toBe(1700);
    $daily = $service->chart(2025, 3, $source, 'daily');
    expect($daily['labels'])->toHaveCount(31)->and($daily['datasets'][0]['data'][14])->toBe(935)->and($daily['datasets'][0]['data'][30])->toBe(65)->and($daily['datasets'][0]['data'][0])->toBe(0);
    expect($record->fresh()->visits)->toBe(900)->and(DB::table('content_view_daily')->count())->toBe(0);
})->with(ContentDashboard::MODULE_ORDER);

it('uses registration dates instead of visit dates and omits deleted records without rewriting counters', function (): void {
    $article = Article::factory()->create(['visits' => 123456, 'created_at' => '2023-02-01 10:00:00', 'last_visited_at' => '2026-11-16 10:00:00']);
    $deleted = Article::factory()->create(['visits' => 9000, 'created_at' => '2023-02-01 10:00:00']);
    $deleted->delete();
    $empty = Article::factory()->create(['created_at' => '2023-02-01 10:00:00']);
    DB::table('articles')->where('id', $empty->id)->update(['visits' => null]);
    $before = DB::table('articles')->orderBy('id')->get()->toJson();
    $service = app(ContentViewAnalytics::class);
    expect($service->chart(2023, 2, 'artigo', 'monthly')['datasets'][0]['data'][1])->toBe(123456)->and($service->chart(2026, 11, 'artigo', 'monthly')['datasets'][0]['data'][10])->toBe(0);
    expect(DB::table('articles')->orderBy('id')->get()->toJson())->toBe($before)->and($article->fresh()->visits)->toBe(123456);
});

it('combines all six sources in card order with exact monthly totals and leap-year day counts', function (): void {
    foreach (ContentDashboard::MODULE_ORDER as $index => $source) {
        $model = ContentRegistry::model($source);
        $model::factory()->create(['visits' => $index + 1, 'created_at' => '2024-02-29 12:00:00']);
    }
    $service = app(ContentViewAnalytics::class);
    $chart = $service->chart(2024, 2, 'all', 'monthly');
    expect(array_column($chart['datasets'], 'label'))->toBe(array_values($service->labels()))->and(array_sum(array_map(fn ($dataset) => $dataset['data'][1], $chart['datasets'])))->toBe(21);
    $daily = $service->chart(2024, 2, 'all', 'daily');
    expect($daily['labels'])->toHaveCount(29)->and($daily['datasets'][5]['data'][28])->toBe(6);
});

it('offers historical registration periods and the current period even when no content exists this month', function (): void {
    Article::factory()->create(['created_at' => '2020-01-01 08:00:00']);
    NewsItem::factory()->create(['created_at' => '2020-07-01 08:00:00']);
    $deleted = Article::factory()->create(['created_at' => '2010-01-01 08:00:00']);
    $deleted->delete();
    expect(app(ContentViewAnalytics::class)->availability())->toBe(['years' => [2026, 2020], 'months' => [2026 => [11], 2020 => [7, 1]]]);
});

it('renders only one chart card with controls inside it and no additional reports or summaries', function (): void {
    $component = Livewire::test(ContentViewsChart::class)->assertSet('year', 2026)->assertSet('month', 11)->assertSet('source', 'all')
        ->assertSee('Visualizações de conteúdo')->assertSee('Tudo')->assertSee('Por mês')->assertSee('Por dia')->assertSee('data de cadastro')
        ->assertDontSee('Dados do período')->assertDontSee('Visualizações por conteúdo')->assertDontSee('Histórico registrado desde')->assertDontSee('Conteúdos acessados no mês')->assertDontSee('Buscar conteúdo');
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$component->html());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " fi-section ")]')->length)->toBe(1)
        ->and($xpath->query('//section//canvas')->length)->toBe(1)->and($xpath->query('//section//select')->length)->toBe(2);
    $this->get('/admin')->assertOk()->assertSee('Acesso rápido')->assertSee('Visualizações de conteúdo')->assertDontSee('Dados do período');
});

it('updates the chart when tabs periods or daily mode change and refreshes current counters', function (): void {
    $article = Article::factory()->create(['visits' => 7, 'created_at' => '2025-12-15 08:00:00']);
    NewsItem::factory()->create(['visits' => 2, 'created_at' => '2026-11-16 08:00:00']);
    $component = Livewire::test(ContentViewsChart::class);
    $component->call('selectSource', 'artigo')->assertSet('source', 'artigo')->assertDispatched('updateChartData');
    $component->call('selectGranularity', 'daily')->assertSet('granularity', 'daily')->assertDispatched('updateChartData');
    $component->set('year', 2025)->assertSet('month', 12)->assertDispatched('updateChartData');
    $article->update(['visits' => 20]);
    $component->call('refreshReport')->assertDispatched('updateChartData', fn ($event, $params) => $params['data']['datasets'][0]['data'][14] === 20);
    $component->set('month', 13)->assertSet('month', 12)->assertHasErrors('month');
    $component->set('year', 1800)->assertSet('year', 2026)->assertSet('month', 11)->assertHasErrors('year');
});

it('rejects unknown chart tab and granularity values', function (string $method): void {
    Livewire::test(ContentViewsChart::class)->call($method, 'unknown')->assertStatus(422);
})->with(['selectSource', 'selectGranularity']);

it('restricts cumulative view reports to active administrators', function (bool $active, int $level): void {
    $this->actingAs(User::factory()->create(['is_active' => $active, 'legacy_level' => $level]));
    Livewire::test(ContentViewsChart::class)->assertForbidden();
    expect(fn () => app(ContentViewAnalytics::class)->chart(2026, 11, 'all', 'monthly'))->toThrow(HttpException::class);
})->with([[true, 1], [false, 3]]);

it('does not activate a second history collector on public content routes', function (): void {
    config(['migration.record_visits' => true]);
    $article = Article::factory()->create(['is_active' => true, 'visits' => 80, 'created_at' => now()->subDay()]);
    $this->get($article->public_url)->assertOk();
    expect(DB::table('content_view_daily')->count())->toBe(0);
    expect(in_array('App\\Http\\Middleware\\TrackContentViews', app('router')->getRoutes()->getByName('site.show.articles')->gatherMiddleware(), true))->toBeFalse();
});

it('initializes the native Filament JavaScript chart with the actual widget options', function (): void {
    $widget = Livewire::test(ContentViewsChart::class)->instance();
    $options = (new ReflectionMethod($widget, 'getOptions'))->invoke($widget);
    $script = <<<'JS'
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const options = JSON.parse(process.argv[1]);
const root = process.argv[2];
let created = 0;
let updated = 0;
class Chart {
    static defaults = { plugins: { legend: {} } };
    constructor(canvas, configuration) {
        created++;
        this.data = configuration.data;
        this.options = configuration.options;
    }
    update() { updated++; }
}
const context = { Chart, window: {}, getComputedStyle: () => ({ fontFamily: 'sans-serif' }) };
const source = fs.readFileSync(`${root}/vendor/filament/widgets/resources/js/components/chart.js`, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace('export default function chart', 'function chart');
vm.runInNewContext(`"use strict";\n${source}\nglobalThis.createChart = chart;`, context);
const component = context.createChart({ cachedData: { labels: ['Nov'], datasets: [{ label: 'Notícias', data: [10] }] }, options, type: 'bar' });
component.$el = {};
component.$refs = { canvas: { style: { maxHeight: '360px' } }, backgroundColorElement: {}, borderColorElement: {}, textColorElement: {}, gridColorElement: {} };
component.applyChartColors = () => {};
component.applyChartCustomProperties = () => {};
component.normalizeDatasets = () => {};
component.initChart();
assert.equal(created, 1, 'The native chart initializer must create the chart');
assert.equal(options.animation.duration, 0);
assert.equal(options.responsive, true);
const existing = new Chart({}, { data: {}, options });
component.getChart = () => existing;
component.updateChartData({ labels: ['Dez'], datasets: [{ data: [20] }] });
assert.equal(existing.data.datasets[0].data[0], 20);
assert.equal(updated, 1);
console.log('Native chart initialization and data updates passed');
JS;
    $process = new Process(['node', '--input-type=module', '-e', $script, json_encode($options, JSON_THROW_ON_ERROR), base_path()]);
    $process->setTimeout(20);
    $process->run();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
});
