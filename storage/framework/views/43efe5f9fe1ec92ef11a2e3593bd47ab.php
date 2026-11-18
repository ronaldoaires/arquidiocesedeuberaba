<dialog id="file-dialog" aria-labelledby="file-dialog-title" class="m-auto h-[92dvh] max-h-[95dvh] w-[96vw] max-w-6xl rounded-lg border-0 bg-white p-0 text-stone-800 shadow-xl backdrop:bg-black/80">
    <div class="flex h-full flex-col">
        <header class="flex shrink-0 flex-wrap items-center gap-3 border-b p-3">
            <h2 id="file-dialog-title" class="min-w-0 flex-1 break-words font-bold">Visualizar arquivo</h2>
            <a data-file-download download class="rounded border px-3 py-2 text-brand-blue focus-visible:ring-2" href="#">Baixar arquivo</a>
            <button data-file-close type="button" autofocus class="min-h-11 rounded bg-brand-red px-4 py-2 text-white focus-visible:ring-2">Fechar</button>
        </header>
        <div data-file-content class="min-h-0 flex-1 overflow-auto bg-stone-100"></div>
        <p class="shrink-0 border-t px-3 py-2 text-sm">Se a visualização não estiver disponível no seu navegador, use “Baixar arquivo”.</p>
    </div>
</dialog>
<?php /**PATH D:\www\laravel\arquidiocesedeuberaba\resources\views/components/site/file-dialog.blade.php ENDPATH**/ ?>