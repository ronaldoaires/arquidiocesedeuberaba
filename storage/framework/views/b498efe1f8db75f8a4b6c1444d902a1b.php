<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item instanceof \App\Models\NewsItem): ?>
<article>
    <a href="<?php echo e($item->public_url); ?>" class="relative block overflow-hidden rounded-md"><img src="<?php echo e($item->thumbnail_url); ?>" alt="<?php echo e($item->display_title); ?>" loading="lazy" class="h-[200px] w-full object-cover"><span class="absolute inset-0 bg-black/25" aria-hidden="true"></span><span class="absolute right-2 bottom-2 rounded bg-brand-red/90 px-3 py-1 text-sm text-white"><?php echo e($item->category?->title); ?></span></a>
    <h2 class="mt-4 text-xl font-bold leading-normal text-[#212529]"><a href="<?php echo e($item->public_url); ?>"><?php echo e($item->display_title); ?></a></h2>
</article>
<?php else: ?>
<article class="overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->image_url): ?>
        <a href="<?php echo e($item->public_url ?? $item->image_url); ?>"><img src="<?php echo e($item->thumbnail_url); ?>" alt="<?php echo e($item->display_title); ?>" loading="lazy" class="aspect-[4/3] w-full object-cover" data-fallback-image="<?php echo e(asset('assets/site/capa-dom-paulo.png')); ?>"></a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="p-5">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->created_at): ?><p class="mb-2 text-xs text-stone-500"><?php echo e($item->created_at->timezone('America/Sao_Paulo')->format('d/m/Y')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <h2 class="text-xl leading-snug text-brand-red"><a href="<?php echo e($item->public_url ?? ($item->file_path ? url('storage/'.$item->file_path) : '#')); ?>"><?php echo e($item->display_title); ?></a></h2>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->starts_on): ?><p class="mt-3"><?php echo e($item->starts_on->format('d/m/Y')); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->ends_on): ?> a <?php echo e($item->ends_on->format('d/m/Y')); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->file_path): ?><a class="button mt-4 inline-block" href="<?php echo e(url('storage/'.$item->file_path)); ?>">Ler ou baixar arquivo</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</article>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\www\laravel\arquidiocesedeuberaba\resources\views/site/partials/card.blade.php ENDPATH**/ ?>