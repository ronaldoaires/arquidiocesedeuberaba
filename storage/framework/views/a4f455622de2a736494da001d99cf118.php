<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e(isset($title) ? $title.' - ' : ''); ?>Arquidiocese de Uberaba</title>
    <meta name="description" content="<?php echo e(isset($record) ? Str::limit(strip_tags($record->body_html ?? ''), 160) : strip_tags($settings?->description ?? 'Arquidiocese de Uberaba')); ?>">
    <link rel="canonical" href="<?php echo e(url()->current()); ?>">
    <meta property="og:title" content="<?php echo e($title ?? 'Arquidiocese de Uberaba'); ?>">
    <meta property="og:url" content="<?php echo e(url()->current()); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($record) && $record->image_url): ?><meta property="og:image" content="<?php echo e($record->image_url); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.5.0/css/all.css">
    <link rel="icon" href="<?php echo e(asset('assets/shared/brasao32x32.png')); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alata&family=Cinzel:wght@400;600;700&display=swap" rel="stylesheet">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="bg-white text-[#646464] antialiased" style="--hero-image:url('<?php echo e(asset('assets/site/bg-slide.jpg')); ?>');--quick-image:url('<?php echo e(asset('assets/site/bg-acesso-rapido.webp')); ?>');--radio-image:url('<?php echo e(asset('assets/site/bg-radios.webp')); ?>');--media-image:url('<?php echo e(asset('assets/site/bg-midias.jpg')); ?>')">
<a href="#main-content" class="sr-only focus:not-sr-only focus:block focus:bg-white focus:p-3">Ir para o conteúdo</a>
<?php echo $__env->make('site.partials.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<main
    id="main-content"
    <?php if(in_array($module['source'] ?? null, ['clero', 'paroquia'], true)): ?>
        data-protected-content
    <?php endif; ?>
>
    <?php echo $__env->yieldContent('content'); ?>
</main>

<?php echo $__env->make('site.partials.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<dialog id="media-dialog" aria-label="Imagem ampliada" class="m-auto max-h-[95vh] max-w-[95vw] rounded bg-white p-3 backdrop:bg-black/80">
    <button type="button" data-close-dialog class="mb-2 block min-h-11 px-3" autofocus>Fechar imagem</button>
    <div class="relative grid place-items-center">
        <button type="button" data-gallery-direction="-1" aria-label="Foto anterior" hidden class="absolute top-1/2 left-0 z-10 min-h-11 min-w-11 -translate-y-1/2 rounded bg-black/60 text-3xl text-white">&lsaquo;</button>
        <img alt="" class="max-h-[80vh] max-w-full object-contain" id="dialog-image" hidden>
        <p data-image-status role="status" class="px-12 py-14 text-center empty:hidden"></p>
        <button type="button" data-gallery-direction="1" aria-label="Próxima foto" hidden class="absolute top-1/2 right-0 z-10 min-h-11 min-w-11 -translate-y-1/2 rounded bg-black/60 text-3xl text-white">&rsaquo;</button>
    </div>
</dialog>
<?php echo $__env->make('site.partials.video-dialog', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if (isset($component)) { $__componentOriginal23bf9151963fb40ad2a1c6b5d13f7204 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal23bf9151963fb40ad2a1c6b5d13f7204 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.site.file-dialog','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('site.file-dialog'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal23bf9151963fb40ad2a1c6b5d13f7204)): ?>
<?php $attributes = $__attributesOriginal23bf9151963fb40ad2a1c6b5d13f7204; ?>
<?php unset($__attributesOriginal23bf9151963fb40ad2a1c6b5d13f7204); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal23bf9151963fb40ad2a1c6b5d13f7204)): ?>
<?php $component = $__componentOriginal23bf9151963fb40ad2a1c6b5d13f7204; ?>
<?php unset($__componentOriginal23bf9151963fb40ad2a1c6b5d13f7204); ?>
<?php endif; ?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($module['source'] ?? null, ['clero', 'paroquia'], true)): ?>
    <script src="<?php echo e(asset('js/content-protection.js')); ?>" defer></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</body>
</html>

<?php /**PATH D:\www\laravel\arquidiocesedeuberaba\resources\views/site/layouts/app.blade.php ENDPATH**/ ?>