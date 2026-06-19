(() => {
    'use strict';

    const normaliseSegment = value => String(value)
        .trim()
        .toLowerCase()
        .replaceAll('_', '-')
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const flattenTokens = (tokens, path = [], result = {}) => {
        if (!tokens || typeof tokens !== 'object' || Array.isArray(tokens)) {
            return result;
        }

        Object.entries(tokens).forEach(([key, value]) => {
            const segment = normaliseSegment(key);
            if (!segment) return;

            const currentPath = [...path, segment];

            if (value && typeof value === 'object' && !Array.isArray(value)) {
                flattenTokens(value, currentPath, result);
                return;
            }

            if (typeof value !== 'string' && typeof value !== 'number') {
                return;
            }

            result[`--${currentPath.join('-')}`] = String(value);
        });

        return result;
    };

    const applyVariables = (element, variables) => {
        Object.entries(variables).forEach(([name, value]) => {
            element.style.setProperty(name, value);
        });
    };

    const createMonogram = siteName => {
        const conciseName = String(siteName || 'Site').split(/\s+-\s+/)[0];
        const ignoredWords = new Set(['the', 'and', 'of']);
        const words = conciseName
            .replace(/[^a-z0-9]+/gi, ' ')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .filter(word => !ignoredWords.has(word.toLowerCase()));

        if (words.length === 0) return 'S';
        if (words.length === 1) return words[0].charAt(0).toUpperCase();

        return `${words[0].charAt(0)}${words.at(-1).charAt(0)}`.toUpperCase();
    };

    const applySiteBranding = brand => {
        if (!brand || typeof brand !== 'object') return;

        const logoIcon = document.querySelector('.site-logo .logo-icon');
        const logoMain = document.querySelector('.site-logo .logo-main');
        const tagline = document.querySelector('.site-logo .logo-tagline');
        const siteLogo = document.querySelector('.site-logo');

        if (logoMain && brand.site_name) {
            logoMain.textContent = brand.site_name;
        }

        if (tagline) {
            tagline.textContent = brand.tagline || '';
            tagline.hidden = !brand.tagline;
        }

        siteLogo?.classList.remove('site-logo--image', 'site-logo--wordmark', 'site-logo--monogram');

        if (logoIcon && brand.logo_url) {
            const image = document.createElement('img');
            image.src = brand.logo_url;
            image.alt = brand.site_name ? `${brand.site_name} logo` : 'Site logo';
            image.className = 'site-logo-image';
            image.decoding = 'async';
            logoIcon.replaceChildren(image);
            siteLogo?.classList.add('site-logo--image');
            return;
        }

        if (logoIcon) {
            const monogram = document.createElement('span');
            monogram.className = 'site-logo-monogram';
            monogram.textContent = createMonogram(brand.site_name);
            monogram.setAttribute('aria-hidden', 'true');
            logoIcon.replaceChildren(monogram);
        }

        siteLogo?.classList.add('site-logo--wordmark', 'site-logo--monogram');
    };

    document.addEventListener('public-content:document-composed', event => {
        const root = event.detail?.root;
        const documentData = event.detail?.document;

        if (!root || !documentData) return;

        const tokens = documentData.designTokens ?? documentData.design_tokens ?? {};
        const variables = flattenTokens(tokens);

        applyVariables(document.documentElement, variables);
        applyVariables(root, variables);
        applySiteBranding(tokens.brand);
    });
})();
