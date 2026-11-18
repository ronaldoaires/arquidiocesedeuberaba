
(() => {
    'use strict';

    const content = document.querySelector('[data-protected-content]');
    if (!content) return;

    const mediaDialog = document.querySelector('#media-dialog');

    const protectedAreas = [
        content,
        ...(mediaDialog ? [mediaDialog] : []),
    ];

    const isEditable = (target) =>
        target instanceof Element &&
        Boolean(target.closest(
            'input, textarea, select, [contenteditable="true"]'
        ));

    const isProtected = (target) =>
        target instanceof Node &&
        protectedAreas.some((area) => area.contains(target)) &&
        !isEditable(target instanceof Element
            ? target
            : target.parentElement);

    // Block context menu, selection and dragging.
    ['contextmenu', 'selectstart', 'dragstart'].forEach((type) => {
        protectedAreas.forEach((area) => {
            area.addEventListener(type, (event) => {
                if (isProtected(event.target)) {
                    event.preventDefault();
                }
            });
        });
    });

    // Block copying and cutting protected selections.
    ['copy', 'cut'].forEach((type) => {
        document.addEventListener(type, (event) => {
            const selection = window.getSelection();

            if (!selection || selection.isCollapsed) return;

            for (let i = 0; i < selection.rangeCount; i++) {
                const range = selection.getRangeAt(i);

                if (protectedAreas.some((area) =>
                    range.intersectsNode(area)
                )) {
                    event.preventDefault();
                    return;
                }
            }
        });
    });

    // Intercept printing shortcuts.
    document.addEventListener('keydown', (event) => {
        const modifier = event.ctrlKey || event.metaKey;
        const key = event.key.toLowerCase();

        if (modifier && key === 'p') {
            event.preventDefault();
        }
    }, true);

    // Protect selection and printed output.
    const style = document.createElement('style');

    style.textContent = `
        [data-protected-content],
        #media-dialog {
            -webkit-user-select: none !important;
            user-select: none !important;
            -webkit-touch-callout: none !important;
        }

        [data-protected-content] img,
        #media-dialog img {
            -webkit-user-drag: none !important;
            user-drag: none !important;
        }

        [data-protected-content] input,
        [data-protected-content] textarea,
        [data-protected-content] select,
        [data-protected-content] [contenteditable="true"] {
            -webkit-user-select: text !important;
            user-select: text !important;
        }

        @media print {
            [data-protected-content],
            #media-dialog {
                display: none !important;
            }
        }
    `;

    document.head.appendChild(style);
})();
