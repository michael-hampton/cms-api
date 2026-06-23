(() => {
    'use strict';

    function setLabel(selector, label) {
        document.querySelectorAll(selector).forEach(function (button) {
            if (!button.getAttribute('aria-label')) {
                button.setAttribute('aria-label', label);
            }
        });
    }

    function apply(root) {
        setLabel('#cart-btn', 'Open cart');
        setLabel('.carousel-arrow-left', 'Previous deals');
        setLabel('.carousel-arrow-right', 'Next deals');

        root.querySelectorAll('.carousel-arrow svg, #cart-btn svg').forEach(function (svg) {
            svg.setAttribute('aria-hidden', 'true');
            svg.setAttribute('focusable', 'false');
        });

        root.querySelectorAll('.oc-controls__dots').forEach(function (dots) {
            if (dots.getAttribute('role') === 'tablist') {
                dots.setAttribute('role', 'group');
            }

            dots.querySelectorAll('.oc-dot').forEach(function (dot) {
                if (dot.getAttribute('role') === 'tab') {
                    dot.removeAttribute('role');
                }
            });
        });
    }

    document.addEventListener('public-content:document-composed', function (event) {
        apply(event.detail.root);
    });

    document.addEventListener('public-content:component-mounted', function (event) {
        apply(event.detail.element);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            apply(document);
        });
    } else {
        apply(document);
    }
})();
