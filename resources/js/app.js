import './file-preview.js';

document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    const slides = [...carousel.querySelectorAll('[data-slide]')];
    if (slides.length < 2) return;
    let index = 0;
    let paused = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const show = (step) => {
        slides[index].hidden = true;
        index = (index + step + slides.length) % slides.length;
        slides[index].hidden = false;
    };
    carousel.querySelector('[data-next]')?.addEventListener('click', () => show(1));
    carousel.querySelector('[data-previous]')?.addEventListener('click', () => show(-1));
    carousel.querySelector('[data-pause]')?.addEventListener('click', (event) => {
        paused = !paused;
        event.currentTarget.textContent = paused ? '▶' : 'Ⅱ';
        event.currentTarget.setAttribute('aria-label', paused ? 'Retomar destaques' : 'Pausar destaques');
    });
    carousel.addEventListener('focusin', () => { paused = true; });
    carousel.addEventListener('mouseenter', () => { paused = true; });
    window.setInterval(() => { if (!paused) show(1); }, 6000);
});

const dialog = document.querySelector('#media-dialog');
let galleryLinks = [];
let galleryIndex = 0;
const displayGalleryImage = (index) => {
    galleryIndex = (index + galleryLinks.length) % galleryLinks.length;
    const link = galleryLinks[galleryIndex];
    const previous = dialog.querySelector('#dialog-image');
    const status = dialog.querySelector('[data-image-status]');
    const image = new Image();
    image.id = previous.id;
    image.className = previous.className;
    image.alt = link.querySelector('img')?.alt ?? 'Imagem ampliada';
    image.decoding = 'async';
    image.hidden = true;
    status.textContent = 'Carregando imagem…';
    image.onload = () => {
        if (!image.isConnected) return;
        image.hidden = false;
        status.textContent = '';
    };
    image.onerror = () => {
        if (image.isConnected) status.textContent = 'Não foi possível carregar esta imagem.';
    };
    previous.replaceWith(image);
    image.src = link.href;
    dialog.querySelectorAll('[data-gallery-direction]').forEach((button) => { button.hidden = galleryLinks.length < 2; });
};
document.querySelectorAll('[data-lightbox]').forEach((link) => link.addEventListener('click', (event) => {
    event.preventDefault();
    galleryLinks = link.dataset.gallery ? [...document.querySelectorAll('[data-lightbox]')].filter((item) => item.dataset.gallery === link.dataset.gallery) : link.closest('.gallery-photos') ? [...link.closest('.gallery-photos').querySelectorAll('[data-lightbox]')] : [link];
    displayGalleryImage(galleryLinks.indexOf(link));
    dialog.showModal();
}));
dialog?.querySelectorAll('[data-gallery-direction]').forEach((button) => button.addEventListener('click', () => displayGalleryImage(galleryIndex + Number(button.dataset.galleryDirection))));
dialog?.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft') displayGalleryImage(galleryIndex - 1);
    if (event.key === 'ArrowRight') displayGalleryImage(galleryIndex + 1);
});
dialog?.querySelector('[data-close-dialog]')?.addEventListener('click', () => dialog.close());
document.querySelectorAll('[data-fallback-image]').forEach((image) => image.addEventListener('error', () => {
    if (image.src !== image.dataset.fallbackImage) image.src = image.dataset.fallbackImage;
}, { once: true }));
document.querySelector('[data-agenda-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    window.location.href = form.action + '/' + form.elements.year.value;
});

document.querySelector('[data-nav-toggle]')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    const menu = document.getElementById(button.getAttribute('aria-controls'));
    const isOpen = menu.classList.toggle('is-open');
    button.setAttribute('aria-expanded', String(isOpen));
});


document.querySelectorAll('[data-hover-menu]').forEach((menu) => {
    const desktopQuery = menu.classList.contains('nav-dropdown') ? '(min-width: 1024px) and (hover: hover)' : '(min-width: 992px) and (hover: hover)';
    menu.addEventListener('mouseenter', () => {
        if (window.matchMedia(desktopQuery).matches) menu.open = true;
    });
    menu.addEventListener('mouseleave', () => {
        if (window.matchMedia(desktopQuery).matches) menu.open = false;
    });
    menu.querySelector(':scope > summary')?.addEventListener('click', (event) => {
        if (event.detail > 0 && window.matchMedia(desktopQuery).matches) { event.preventDefault(); menu.open = true; }
    });
    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { menu.open = false; menu.querySelector('summary').focus(); }
    });
});


const instituteDrawer = document.querySelector('#institute-drawer');
document.querySelector('[data-open-drawer]')?.addEventListener('click', (event) => {
    event.preventDefault(); instituteDrawer.showModal();
});
document.querySelector('[data-close-drawer]')?.addEventListener('click', () => instituteDrawer.close());
document.querySelectorAll('[data-card-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('.carousel-track');
    const tiles = [...track.children];
    const dots = carousel.querySelector('[data-carousel-dots]');
    let buttons = [];
    let paused = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const visibleCount = () => track.classList.contains('media-track') && window.matchMedia('(min-width:992px)').matches ? 2 : 1;
    const renderDots = () => {
        dots.replaceChildren(); buttons = [];
        for (let page = 0; page < Math.ceil(tiles.length / visibleCount()); page++) {
            const button = document.createElement('button');
            button.type = 'button'; button.setAttribute('aria-label', `Exibir grupo ${page + 1}`);
            button.addEventListener('click', () => track.scrollTo({left:page * (track.clientWidth + 10), behavior:paused ? 'instant' : 'smooth'}));
            dots.append(button); buttons.push(button);
        }
        updateDots();
    };
    const updateDots = () => {
        const current = Math.round(track.scrollLeft / (track.clientWidth + 10));
        buttons.forEach((button,index) => button.setAttribute('aria-pressed', String(index === current)));
    };
    track.addEventListener('scroll', updateDots, {passive:true});
    window.addEventListener('resize', renderDots);
    carousel.addEventListener('mouseenter', () => {paused = true;});
    carousel.addEventListener('mouseleave', () => {paused = window.matchMedia('(prefers-reduced-motion: reduce)').matches;});
    carousel.addEventListener('focusin', () => {paused = true;});
    renderDots();
    if (carousel.hasAttribute('data-autoplay')) window.setInterval(() => {
        if (!paused && buttons.length > 1) buttons[(Math.round(track.scrollLeft / (track.clientWidth + 10)) + 1) % buttons.length].click();
    }, 5000);
});

const videoDialog = document.querySelector('#video-dialog');
document.querySelectorAll('[data-video]').forEach((link) => link.addEventListener('click', (event) => {
    const videoId = new URL(link.href).searchParams.get('v');
    if (!videoDialog || !/^[\w-]{11}$/.test(videoId ?? '')) return;
    event.preventDefault();
    videoDialog.querySelector('iframe').src = `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1`;
    videoDialog.showModal();
}));
videoDialog?.querySelector('[data-close-video]')?.addEventListener('click', () => videoDialog.close());
videoDialog?.addEventListener('close', () => videoDialog.querySelector('iframe').removeAttribute('src'));

document.querySelector('.support-form')?.addEventListener('change', (event) => {
    const form = event.currentTarget;
    form.querySelectorAll('[data-condition-field]').forEach((field) => {
        const input = form.querySelector(`[name="${field.dataset.conditionField}"]:checked`);
        field.hidden = !input || input.value !== field.dataset.conditionValue;
    });
});


document.querySelectorAll('[data-inline-video]').forEach((link) => link.addEventListener('click', (event) => {
    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || !/^[A-Za-z0-9_-]{11}$/.test(link.dataset.videoId || '')) return;
    event.preventDefault();
    const frame = document.createElement('iframe');
    frame.className = 'aspect-video w-full border-0';
    frame.title = link.dataset.videoTitle || 'Vídeo da pastoral';
    frame.src = `https://www.youtube-nocookie.com/embed/${link.dataset.videoId}?autoplay=1`;
    frame.allow = 'autoplay; encrypted-media; picture-in-picture';
    frame.allowFullscreen = true;
    frame.referrerPolicy = 'strict-origin-when-cross-origin';
    link.replaceWith(frame);
}));
