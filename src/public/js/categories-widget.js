(() => {
    'use strict';

    class CategoriesWidgetIsland {
        constructor(root) {
            this.root = root;
            this.carousel = root.querySelector('[data-category-carousel]');
        }

        start() {
            if (this.root.dataset.apiHydrated === 'true') return;
            this.root.dataset.apiHydrated = 'true';

            this.root.addEventListener('change', event => {
                const input = event.target.closest('input[name="category_filter"][data-url]');
                if (!input || !this.root.contains(input) || !input.checked) return;

                const targetUrl = input.getAttribute('data-url');
                if (!targetUrl) return;

                window.setTimeout(() => {
                    window.location.href = targetUrl;
                }, 150);
            });

            this.root.addEventListener('click', event => {
                const button = event.target.closest('[data-category-carousel-scroll]');
                if (!button || !this.root.contains(button) || !this.carousel) return;

                const distance = Number(button.dataset.categoryCarouselScroll || 0);
                this.carousel.scrollBy({left: distance, behavior: 'smooth'});
            });

            this.selectCurrentCategory();
        }

        selectCurrentCategory() {
            const currentPath = window.location.pathname;
            const radios = this.root.querySelectorAll('input[name="category_filter"][data-url]');

            radios.forEach(radio => {
                if (radio.getAttribute('data-url') !== currentPath) return;

                radio.checked = true;
                radio.closest('.category-pill')?.scrollIntoView({
                    behavior: 'smooth',
                    inline: 'center',
                    block: 'nearest',
                });
            });
        }
    }

    if (window.PublicIslands) {
        window.PublicIslands.register('categories-widget', {
            hydrate(root) {
                new CategoriesWidgetIsland(root).start();
            },
        });
    }
})();
