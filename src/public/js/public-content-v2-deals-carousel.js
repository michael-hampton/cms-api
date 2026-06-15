(() => {
    'use strict';

    class MountedDealsCarousel {
        constructor(root) {
            this.root = root;
            this.track = root.querySelector('.deals-carousel-track');
            this.dots = root.querySelector('.carousel-dots');
            this.search = root.querySelector('.deals-search-input');
            this.noResults = root.querySelector('.deals-no-results');
            this.index = 0;
            this.prev = this.replaceButton(root.querySelector('.carousel-arrow-left'));
            this.next = this.replaceButton(root.querySelector('.carousel-arrow-right'));
        }

        replaceButton(button) {
            if (!button) return null;
            const replacement = button.cloneNode(true);
            replacement.removeAttribute('onclick');
            replacement.disabled = false;
            button.replaceWith(replacement);
            return replacement;
        }

        start() {
            if (!this.track || this.root.dataset.mountedDealsHydrated === 'true') return;
            this.root.dataset.mountedDealsHydrated = 'true';

            this.fixCardHitAreas();
            this.search?.removeAttribute('onkeyup');
            this.prev?.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                this.move(-1);
            });
            this.next?.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                this.move(1);
            });
            this.search?.addEventListener('input', () => this.filter());
            this.track.addEventListener('scroll', () => this.syncFromScroll(), {passive: true});
            window.addEventListener('resize', () => this.refresh());

            requestAnimationFrame(() => this.refresh());
        }

        fixCardHitAreas() {
            this.root.querySelectorAll('.deal-header-actions').forEach(overlay => {
                overlay.style.pointerEvents = 'none';

                overlay.querySelectorAll('a, button, .deal-wishlist-btn').forEach(control => {
                    control.style.pointerEvents = 'auto';
                });
            });

            this.root.querySelectorAll('.deal-actions').forEach(actions => {
                actions.style.position = 'relative';
                actions.style.zIndex = '20';
                actions.style.pointerEvents = 'auto';
            });
        }

        visibleCards() {
            return [...this.track.querySelectorAll('.deal-card:not(.is-hidden)')];
        }

        step() {
            const card = this.visibleCards()[0];
            if (!card) return Math.max(1, this.track.clientWidth);
            const gap = Number.parseFloat(getComputedStyle(this.track).gap || '0');
            return card.getBoundingClientRect().width + gap;
        }

        itemsPerView() {
            return Math.max(1, Math.floor(this.track.clientWidth / this.step()));
        }

        maxIndex() {
            return Math.max(0, this.visibleCards().length - this.itemsPerView());
        }

        move(direction) {
            this.index = Math.max(0, Math.min(this.maxIndex(), this.index + direction));
            this.track.scrollTo({left: this.index * this.step(), behavior: 'smooth'});
            this.updateControls();
        }

        syncFromScroll() {
            this.index = Math.max(0, Math.min(this.maxIndex(), Math.round(this.track.scrollLeft / this.step())));
            this.updateControls();
        }

        refresh() {
            this.index = Math.min(this.index, this.maxIndex());
            this.buildDots();
            this.updateControls();
        }

        updateControls() {
            const max = this.maxIndex();
            if (this.prev) {
                this.prev.disabled = this.index <= 0;
                this.prev.style.pointerEvents = this.index <= 0 ? 'none' : 'auto';
                this.prev.style.opacity = this.index <= 0 ? '0.5' : '1';
            }
            if (this.next) {
                this.next.disabled = this.index >= max;
                this.next.style.pointerEvents = this.index >= max ? 'none' : 'auto';
                this.next.style.opacity = this.index >= max ? '0.5' : '1';
            }
            [...(this.dots?.children ?? [])].forEach((dot, index) => {
                dot.classList.toggle('active', index === this.index);
            });
        }

        buildDots() {
            if (!this.dots) return;
            this.dots.replaceChildren();
            for (let index = 0; index <= this.maxIndex(); index++) {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'carousel-dot';
                dot.setAttribute('aria-label', `Go to deal ${index + 1}`);
                dot.addEventListener('click', () => {
                    this.index = index;
                    this.track.scrollTo({left: this.index * this.step(), behavior: 'smooth'});
                    this.updateControls();
                });
                this.dots.append(dot);
            }
        }

        filter() {
            const query = this.search?.value.trim().toLowerCase() ?? '';
            let visible = 0;

            this.track.querySelectorAll('.deal-card').forEach(card => {
                const title = (card.dataset.title || card.textContent || '').toLowerCase();
                const show = title.includes(query);
                card.classList.toggle('is-hidden', !show);
                if (show) visible++;
            });

            if (this.noResults) this.noResults.style.display = visible ? 'none' : 'block';
            this.index = 0;
            this.track.scrollLeft = 0;
            this.refresh();
        }
    }

    const initialise = root => {
        root.querySelectorAll('.deals-carousel-wrapper').forEach(element => {
            new MountedDealsCarousel(element).start();
        });
    };

    document.addEventListener('public-content:document-composed', event => {
        initialise(event.detail.root);
    });
})();
