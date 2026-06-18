(() => {
    'use strict';

    const loaderScriptUrl = document.currentScript?.src ?? '';

    const normaliseMemberHubSource = source => source
        .replace(/\s*alert\(['"]here['"]\)\s*;?/g, '')
        .replace(/\s*alert\(url\)\s*;?/g, '')
        .replace(
            '(data ?? []).slice(0, 2)',
            '(Array.isArray(data) ? data : (data?.items ?? data?.data ?? [])).slice(0, 2)',
        );

    const boot = async () => {
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

        try {
            const response = await fetch(memberHubUrl.href, {
                credentials: 'same-origin',
                headers: {'Accept': 'text/javascript'},
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const source = normaliseMemberHubSource(await response.text());
            const blobUrl = URL.createObjectURL(new Blob([source], {type: 'text/javascript'}));
            const script = document.createElement('script');

            script.src = blobUrl;
            script.onload = () => URL.revokeObjectURL(blobUrl);
            script.onerror = () => {
                URL.revokeObjectURL(blobUrl);
                delete document.documentElement.dataset.v2MemberHubLoaded;
                console.error('[MemberHub] Failed to execute member-hub.js.');
            };

            document.body.append(script);
        } catch (error) {
            delete document.documentElement.dataset.v2MemberHubLoaded;
            console.error('[MemberHub] Failed to load member-hub.js:', error);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
})();
