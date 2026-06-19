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

    document.addEventListener('public-content:document-composed', event => {
        const root = event.detail?.root;
        const documentData = event.detail?.document;

        if (!root || !documentData) return;

        const tokens = documentData.designTokens ?? documentData.design_tokens ?? {};
        const variables = flattenTokens(tokens);

        applyVariables(document.documentElement, variables);
        applyVariables(root, variables);
    });
})();
