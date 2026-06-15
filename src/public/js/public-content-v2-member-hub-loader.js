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

    const script = document.createElement('script');
    script.src = `/js/member-hub.js?v=${Date.now()}`;
    script.defer = true;
    document.body.append(script);
})();
