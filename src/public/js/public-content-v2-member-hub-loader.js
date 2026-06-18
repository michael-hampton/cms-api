(() => {
    'use strict';

    // document.currentScript is only reliable while this file is executing.
    // Capture it now because boot may run later on DOMContentLoaded.
    const loaderScriptUrl = document.currentScript?.src ?? '';

    const bindDrawer = () => {
        const trigger = document.getElementById('mh-hub-trigger');
        const panel = document.getElementById('mh-panel');
        const overlay = document.getElementById('mh-overlay');
        const close = document.getElementById('mh-close');

        if (!trigger || !panel || !overlay || trigger.dataset.v2DrawerBound === 'true') {
            return;
        }

        trigger.dataset.v2DrawerBound = 'true';

        const open = () => {
            panel.classList.add('is-open');
            panel.setAttribute('aria-hidden', 'false');
            panel.removeAttribute('inert');
            overlay.classList.add('is-visible');
            overlay.removeAttribute('aria-hidden');
            trigger.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        };

        const shut = () => {
            if (panel.contains(document.activeElement)) {
                trigger.focus();
            }

            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
            panel.setAttribute('inert', '');
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
            trigger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        };

        trigger.addEventListener('click', event => {
            event.preventDefault();
            panel.classList.contains('is-open') ? shut() : open();
        });
        close?.addEventListener('click', shut);
        overlay.addEventListener('click', shut);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && panel.classList.contains('is-open')) {
                shut();
            }
        });
    };

    const boot = () => {
        bindDrawer();

        if (window.MH || document.documentElement.dataset.v2MemberHubLoaded === 'true') {
            return;
        }

        if (!loaderScriptUrl) {
            console.error('[MemberHub] Unable to resolve the member hub script URL.');
            return;
        }

        document.documentElement.dataset.v2MemberHubLoaded = 'true';

        const memberHubUrl = new URL(loaderScriptUrl, window.location.origin);
        memberHubUrl.pathname = memberHubUrl.pathname.replace(
            /public-content-v2-member-hub-loader\.js$/,
            'member-hub.js',
        );

        const script = document.createElement('script');
        script.src = memberHubUrl.href;
        script.onload = bindDrawer;
        script.onerror = () => {
            delete document.documentElement.dataset.v2MemberHubLoaded;
            console.error('[MemberHub] Failed to load member-hub.js.');
        };
        document.body.append(script);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
})();
