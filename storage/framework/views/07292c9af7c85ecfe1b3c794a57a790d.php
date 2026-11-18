<section id="boletim" aria-labelledby="boletim-titulo" class="border-b border-white/30">
    <div class="site-container grid items-center gap-4 py-6 md:grid-cols-2">
        <div>
            <h2 id="boletim-titulo">Receba o boletim da Arquidiocese</h2>
            <p>Notícias, artigos, eventos e muito mais no seu e-mail.</p>
        </div>
        <form action="<?php echo e(route('site.newsletter.subscribe')); ?>" method="post">
            <?php echo csrf_field(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('newsletter_requested')): ?>
                <div role="alert" class="mb-4 flex items-start gap-3 rounded-lg border-l-4 border-amber-500 bg-amber-50 p-4 text-stone-900">
                    <i class="fa-solid fa-envelope-open-text mt-1 shrink-0 text-brand-dark" aria-hidden="true"></i>
                    <div>
                        <strong class="block text-lg">Confira seu e-mail</strong>
                        <span class="block">Use o link da mensagem para concluir ou gerenciar sua inscrição.</span>
                        <span class="mt-1 block">Se precisar, reenvie o link após um minuto.</span>
                    </div>
                </div>
            <?php elseif(session('newsletter_status')): ?>
                <p role="status"><?php echo e(session('newsletter_status')); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <label for="boletim-email">Seu e-mail</label>
            <div class="mt-1 grid gap-2 sm:flex">
                <input class="form-field min-w-0 flex-1 text-stone-900" id="boletim-email" type="email" name="newsletter_email"
                    value="<?php echo e(old('newsletter_email')); ?>" maxlength="254" autocomplete="email" required
                    <?php if($errors->newsletter->has('newsletter_email')): ?> aria-invalid="true" aria-describedby="boletim-erro" <?php endif; ?>>
                <button class="min-h-11 cursor-pointer rounded bg-white px-5 py-2 font-bold text-brand-dark" type="submit">
                    <?php echo e(session('newsletter_requested') ? 'Reenviar link' : 'Quero receber'); ?>

                </button>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newsletter_email', 'newsletter'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="boletim-erro" role="alert"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
    </div>
</section>
<?php /**PATH D:\www\laravel\arquidiocesedeuberaba\resources\views/site/newsletter/footer.blade.php ENDPATH**/ ?>