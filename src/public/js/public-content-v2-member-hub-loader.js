(() => {
    'use strict';

    if (!window.MH || document.documentElement.dataset.v2MemberHubLoaded === 'true') {
        return;
    }

    document.documentElement.dataset.v2MemberHubLoaded = 'true';

    const originalAlert = window.alert.bind(window);
    window.alert = message => {
        const value = String(message ?? '');

        if (value === 'here' || value.startsWith('/api/')) {
            return;
        }

        originalAlert(message);
    };

    const loaderUrl = new URL(document.currentScript.src, window.location.origin);
    loaderUrl.pathname = loaderUrl.pathname.replace(
        /public-content-v2-member-hub-loader\.js$/,
        'member-hub.js',
    );

    const script = document.createElement('script');
    script.src = loaderUrl.href;
    script.defer = true;
    document.body.append(script);
})();
