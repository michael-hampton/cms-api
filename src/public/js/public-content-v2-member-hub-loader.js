(() => {
    'use strict';

    // document.currentScript becomes null once execution leaves this file.
    // Capture the URL immediately because boot may run on DOMContentLoaded.
    const loaderScriptUrl = document.currentScript?.src ?? '';

    const boot = () => {
        if (!window.MH || document.documentElement.dataset.v2MemberHubLoaded === 'true') {
            return;
        }

        if (!loaderScriptUrl) {
            console.error('[MemberHub] Unable to resolve the member hub script URL.');
            return;
        }

        const memberHubUrl = new URL(loaderScriptUrl, window.location.origin);
        memberHubUrl.pathname = memberHubUrl.pathname.replace(
            /public-content-v2-member-hub-loader\.js$/,
            'member-hub.js',
        );

        if (memberHubUrl.href === loaderScriptUrl) {
            console.error('[MemberHub] Unable to derive member-hub.js from the loader URL.');
            return;
        }

        document.documentElement.dataset.v2MemberHubLoaded = 'true';

        const script = document.createElement('script');
        script.src = memberHubUrl.href;
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
