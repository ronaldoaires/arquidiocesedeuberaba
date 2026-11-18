const fileDialog = document.querySelector('#file-dialog');
if (fileDialog && typeof fileDialog.showModal === 'function') {
    const content = fileDialog.querySelector('[data-file-content]');
    const title = fileDialog.querySelector('#file-dialog-title');
    const download = fileDialog.querySelector('[data-file-download]');
    let opener = null;
    let previousOverflow = '';
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.hasAttribute('download') || link.closest('#file-dialog')) return;
        const url = new URL(link.href, window.location.href);
        const isPublicFile = url.origin === window.location.origin && /^\/(storage|uploads)\//.test(url.pathname);
        const extension = (link.dataset.fileType || url.pathname.split('.').pop() || '').toLowerCase();
        const supported = ['pdf', 'mp3', 'wav', 'mp4', 'webm', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
        const documents = ['doc', 'docx', 'xls', 'xlsx', 'txt', 'xml', 'mxl', 'mscz', 'zip'];
        if (url.origin !== window.location.origin || (!link.hasAttribute('data-file-preview') && (!isPublicFile || ![...supported, ...documents].includes(extension)))) return;
        if (link.hasAttribute('data-lightbox') || link.hasAttribute('data-video')) return;
        event.preventDefault();
        opener = link;
        title.textContent = link.dataset.fileTitle || link.textContent.trim() || 'Visualizar arquivo';
        const downloadUrl = new URL(link.dataset.fileDownload || link.href, window.location.href);
        download.href = downloadUrl.origin === window.location.origin ? downloadUrl.href : link.href;
        content.replaceChildren();
        if (extension === 'pdf') {
            const frame = document.createElement('iframe');
            frame.title = title.textContent;
            frame.className = 'h-full w-full border-0';
            frame.src = link.href;
            content.append(frame);
        } else if (['mp3', 'wav', 'mp4', 'webm'].includes(extension)) {
            const media = document.createElement(['mp3', 'wav'].includes(extension) ? 'audio' : 'video');
            media.controls = true;
            media.preload = 'none';
            media.className = 'mx-auto h-full max-h-full w-full';
            media.src = link.href;
            content.append(media);
        } else if (supported.includes(extension)) {
            const image = document.createElement('img');
            image.src = link.href;
            image.alt = title.textContent;
            image.className = 'mx-auto max-h-full max-w-full object-contain';
            content.append(image);
        } else {
            const message = document.createElement('p');
            message.className = 'p-6';
            message.textContent = 'Este tipo de arquivo não possui pré-visualização. Use “Baixar arquivo” para abri-lo no seu dispositivo.';
            content.append(message);
        }
        previousOverflow = document.documentElement.style.overflow;
        document.documentElement.style.overflow = 'hidden';
        fileDialog.showModal();
    });
    fileDialog.querySelector('[data-file-close]').addEventListener('click', () => fileDialog.close());
    fileDialog.addEventListener('click', (event) => {
        if (event.target !== fileDialog) return;
        const bounds = fileDialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) fileDialog.close();
    });
    fileDialog.addEventListener('close', () => {
        content.replaceChildren();
        document.documentElement.style.overflow = previousOverflow;
        opener?.focus();
        opener = null;
    });
}
