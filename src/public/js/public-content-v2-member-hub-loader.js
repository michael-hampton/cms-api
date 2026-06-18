(() => {
    'use strict';

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
            overlay.classList.add('is-visible');
            overlay.removeAttribute('aria-hidden');
            trigger.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        };

        const shut = () => {
            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
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

        if (!window.MH || document.documentElement.dataset.v2MemberHubLoaded === 'true') {
            return;
        }

        document.documentElement.dataset.v2MemberHubLoaded = 'true';

        const loaderUrl = new URL(document.currentScript?.src ?? '', window.location.origin);
        loaderUrl.pathname = loaderUrl.pathname.replace(
            /public-content-v2-member-hub-loader\.js$/,
            'member-hub.js',
        );

        const script = document.createElement('script');
        script.src = loaderUrl.href;
        script.onload = bindDrawer;
        document.body.append(script);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
})();
