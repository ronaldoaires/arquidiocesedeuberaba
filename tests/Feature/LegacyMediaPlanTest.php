<?php

use App\Services\Migration\LegacyMediaPlan;
use Illuminate\Support\Facades\File;

it('plans structured files, editor links and static references without changing uploads', function () {
    $root = sys_get_temp_dir().'/legacy-plan-'.bin2hex(random_bytes(12));
    try {
        foreach ([
            'uploads/eventos/festa.jpg', 'uploads/eventos/thumbs/festa.jpg',
            'uploads/summernote/Anúncio 1.jpg', 'uploads/Arquivos/folha.pdf',
            'uploads/Arquivos/outra/folha.pdf', 'uploads/imagens/logo.png',
            'uploads/imagens/externa.jpg', 'uploads/imagens/sem-uso.jpg',
            'uploads/eventos/thumbs/perdida.jpg', 'public/legacy/uploads/eventos/recuperada.jpg',
            'public/content/events/festa.jpg',
        ] as $path) {
            File::ensureDirectoryExists(dirname($root.'/'.$path));
            File::put($root.'/'.$path, $path);
        }
        File::ensureDirectoryExists($root.'/templates');
        File::ensureDirectoryExists($root.'/sistema');
        File::put($root.'/templates/footer.html', "{{ url('uploads/imagens/logo.png') }}");
        $plan = new LegacyMediaPlan($root, $root.'/public', ['localhost']);
        $module = ['media' => 'eventos', 'table' => 'events', 'columns' => [
            ['source' => 'capa', 'name' => 'cover_path'], ['source' => 'texto', 'name' => 'body_html'],
        ]];
        $html = '<img srcset="/uploads/summernote/An%C3%BAncio%201.jpg 2x"><a href="http://localhost/site/uploads/Arquivos/folha.pdf#page=1">A</a><a href="/uploads/Arquivos/outra/folha.pdf">B</a><img src="https://localhost/wp-content/uploads/2022/aviso.jpg"><img src="https://externo.example/uploads/imagens/externa.jpg"><i style="background:url(/uploads/imagens/falta.png)"></i>';
        $plan->scanRecord('evento', $module, ['id' => 1, 'capa' => 'festa.jpg', 'texto' => $html]);
        $plan->scanRecord('evento', $module, ['id' => 2, 'capa' => 'perdida.jpg']);
        $plan->scanRecord('evento', $module, ['id' => 3, 'capa' => 'recuperada.jpg']);
        $plan->scanTemplates();
        $report = $plan->report();
        $cover = $report['references'][0]['destination'];
        expect($cover)->toStartWith('content/events/festa-')
            ->and($report['destinations'])->toHaveKeys(['content/events/thumbs/'.basename($cover), 'editor/events/Anúncio 1.jpg', 'editor/documents/folha.pdf', 'content/site_settings/logo.png'])
            ->and(array_column($report['destinations'], 'source'))->toContain('uploads/Arquivos/outra/folha.pdf')
            ->and($report['summary']['reference_statuses'])->toMatchArray(['review_wordpress_url' => 1, 'missing' => 2, 'available_in_laravel_legacy' => 1, 'review_thumbnail_without_original' => 1])
            ->and(array_keys($report['unreferenced_candidates_bytes_by_path']))->toBe(['uploads/imagens/externa.jpg', 'uploads/imagens/sem-uso.jpg'])
            ->and(file_exists($root.'/public/'.$cover))->toBeFalse()
            ->and(File::get($root.'/public/content/events/festa.jpg'))->toBe('public/content/events/festa.jpg');
    } finally {
        File::deleteDirectory($root);
    }
});
