(() => {
    'use strict';

    const shell = document.querySelector('[data-directory-search-shell]');
    const app = document.getElementById('public-directory-app');
    if (!shell || !app) return;

    const input = shell.querySelector('[data-directory-search-shell-input]');
    const clear = shell.querySelector('[data-directory-search-shell-clear]');
    const summary = document.querySelector('[data-directory-search-shell-summary]');

    const initialQuery = new URLSearchParams(window.location.search).get('q') || '';
    input.value = initialQuery;
    clear.hidden = initialQuery === '';

    const sync = () => {
        const internalInput = app.querySelector('[data-directory-search]');
        const internalSearch = app.querySelector('.directory-search');
        const internalSummary = app.querySelector('.directory-search-summary');

        if (internalSearch) internalSearch.hidden = true;
        if (internalSummary) {
            internalSummary.hidden = true;
            if (summary) summary.textContent = internalSummary.textContent;
        }

        if (internalInput && internalInput.value !== input.value) {
            internalInput.value = input.value;
            internalInput.dispatchEvent(new Event('input', {bubbles: true}));
        }
    };

    input.addEventListener('input', () => {
        clear.hidden = input.value.trim() === '';
        sync();
    });

    clear.addEventListener('click', () => {
        input.value = '';
        clear.hidden = true;
        sync();
        input.focus();
    });

    new MutationObserver(sync).observe(app, {childList: true, subtree: true});
    sync();
})();
