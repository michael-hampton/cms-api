(() => {
    'use strict';

    class DealsCarousel {
        constructor(element) {
            this.root = typeof element === 'string' ? document.querySelector(element) : element;
            this.wrapper = this.root?.closest('.deals-carousel-wrapper') ?? this.root;
            this.track = this.root?.querySelector('.deals-carousel-track') ?? null;
            this.leftArrow = this.root?.querySelector('.carousel-arrow-left') ?? null;
            this.rightArrow = this.root?.querySelector('.carousel-arrow-right') ?? null;
            this.dotsContainer = this.wrapper?.querySelector('.carousel-dots') ?? null;
            this.searchInput = this.wrapper?.querySelector('.deals-search-input') ?? null;
            this.noResults = this.wrapper?.querySelector('.deals-no-results') ?? null;
            this.currentIndex = 0;
            this.itemsPerView = 1;
            this.maxIndex = 0;
            this.autoSlideInterval = null;
            this.autoSlideDelay = 5000;
        }

        start() {
            if (!this.root || !this.track || this.root.dataset.dealsHydrated === 'true') return;

            this.root.dataset.dealsHydrated = 'true';
            this.recalculate();
            this.createDots();
            this.updateArrows();

            this.leftArrow?.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                this.scroll(-1);
            });

            this.rightArrow?.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                this.scroll(1);
            });

            this.wrapper?.addEventListener('click', event => this.handleClick(event));
            this.searchInput?.addEventListener('input', () => this.filter());
            window.addEventListener('resize', () => this.onResize());

            this.bindTouch();
            this.startAutoSlide();
        }

        cards() {
            return [...this.track.querySelectorAll('.deal-card')];
        }

        visibleCards() {
            return this.cards().filter(card => !card.classList.contains('is-hidden'));
        }

        recalculate() {
            const card = this.cards()[0];
            const cardWidth = card?.getBoundingClientRect().width || 280;
            const styles = window.getComputedStyle(this.track);
            const gap = parseFloat(styles.columnGap || styles.gap || '16') || 16;
            const trackWidth = this.track.getBoundingClientRect().width || cardWidth;

            this.itemWidth = cardWidth + gap;
            this.itemsPerView = Math.max(1, Math.floor(trackWidth / this.itemWidth));
            this.maxIndex = Math.max(0, this.visibleCards().length - this.itemsPerView);
            this.currentIndex = Math.min(this.currentIndex, this.maxIndex);
        }

        scroll(direction) {
            this.stopAutoSlide();
            this.scrollToIndex(this.currentIndex + direction);
            this.startAutoSlide();
        }

        scrollToIndex(index) {
            this.currentIndex = Math.max(0, Math.min(this.maxIndex, index));
            this.track.scrollTo({left: this.currentIndex * this.itemWidth, behavior: 'smooth'});
            this.updateDots();
            this.updateArrows();
        }

        createDots() {
            if (!this.dotsContainer) return;

            this.dotsContainer.innerHTML = '';
            for (let index = 0; index <= this.maxIndex; index++) {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = `carousel-dot${index === 0 ? ' active' : ''}`;
                dot.setAttribute('aria-label', `Go to deal ${index + 1}`);
                dot.addEventListener('click', () => this.scrollToIndex(index));
                this.dotsContainer.appendChild(dot);
            }
        }

        updateDots() {
            this.dotsContainer?.querySelectorAll('.carousel-dot').forEach((dot, index) => {
                dot.classList.toggle('active', index === this.currentIndex);
            });
        }

        updateArrows() {
            this.setArrowState(this.leftArrow, this.currentIndex === 0);
            this.setArrowState(this.rightArrow, this.currentIndex >= this.maxIndex);
        }

        setArrowState(button, disabled) {
            if (!button) return;
            button.disabled = disabled;
            button.style.opacity = disabled ? '0.5' : '1';
            button.style.pointerEvents = disabled ? 'none' : 'auto';
        }

        filter() {
            const query = String(this.searchInput?.value ?? '').trim().toLowerCase();
            let visible = 0;

            this.cards().forEach(card => {
                const title = String(card.dataset.title || card.textContent || '').toLowerCase();
                const matches = title.includes(query);
                card.classList.toggle('is-hidden', !matches);
                if (matches) visible++;
            });

            if (this.noResults) this.noResults.style.display = visible === 0 ? 'block' : 'none';
            this.track.scrollLeft = 0;
            this.currentIndex = 0;
            this.recalculate();
            this.createDots();
            this.updateArrows();
        }

        async handleClick(event) {
            const wishlist = event.target.closest('[data-action="toggle-wishlist"]');
            if (wishlist && this.wrapper?.contains(wishlist)) {
                event.preventDefault();
                event.stopPropagation();
                await this.toggleWishlist(wishlist);
                return;
            }

            const cart = event.target.closest('[data-action="add-to-cart"]');
            if (cart && this.wrapper?.contains(cart)) {
                event.preventDefault();
                event.stopPropagation();
                await this.addToCart(cart);
            }
        }

        async toggleWishlist(button) {
            const productId = Number(button.dataset.productId);
            if (!productId || button.disabled) return;

            const active = button.classList.contains('active');
            button.disabled = true;

            try {
                const site = window.SITE || this.wrapper?.dataset.site || 'default';
                const response = await fetch(
                    active
                        ? `/api/${encodeURIComponent(site)}/wishlist/remove/${productId}`
                        : `/api/${encodeURIComponent(site)}/wishlist/add`,
                    {
                        method: active ? 'DELETE' : 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: active ? null : JSON.stringify({product_id: productId}),
                    },
                );

                const payload = await response.json();
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || 'Unable to update wishlist.');
                }

                const nextActive = !active;
                button.classList.toggle('active', nextActive);
                button.setAttribute('aria-pressed', String(nextActive));
                button.setAttribute('aria-label', nextActive ? 'Remove from wishlist' : 'Add to wishlist');
                const icon = button.querySelector('svg');
                if (icon) icon.setAttribute('fill', nextActive ? 'currentColor' : 'none');
                this.updateCount('wishlist-count', payload.count);
                this.toast(payload.message || (nextActive ? 'Added to wishlist' : 'Removed from wishlist'), 'success');
            } catch (error) {
                this.toast(error.message || 'Unable to update wishlist.', 'error');
            } finally {
                button.disabled = false;
            }
        }

        async addToCart(button) {
            const productId = Number(button.dataset.productId);
            if (!productId || button.disabled) return;

            const label = button.querySelector('span');
            const original = label?.textContent || 'Add to Cart';
            button.disabled = true;
            if (label) label.textContent = 'Adding…';

            try {
                const site = window.SITE || this.wrapper?.dataset.site || 'default';
                const response = await fetch(`/api/${encodeURIComponent(site)}/cart/add`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({product_id: productId, quantity: 1}),
                });

                const payload = await response.json();
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || 'Unable to add item to cart.');
                }

                this.updateCount('cart-count', payload.count);
                this.toast(payload.message || 'Added to cart', 'success');
            } catch (error) {
                this.toast(error.message || 'Unable to add item to cart.', 'error');
            } finally {
                button.disabled = false;
                if (label) label.textContent = original;
            }
        }

        updateCount(id, count) {
            if (typeof count !== 'number') return;
            const badge = document.getElementById(id);
            if (!badge) return;
            badge.textContent = String(count);
            badge.style.display = count > 0 ? 'block' : 'none';
        }

        toast(message, type) {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type);
                return;
            }

            let toast = document.getElementById('toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'toast';
                document.body.appendChild(toast);
            }

            toast.textContent = message;
            toast.className = `toast ${type} show`;
            window.setTimeout(() => toast.classList.remove('show'), 3000);
        }

        bindTouch() {
            let startX = 0;
            this.track.addEventListener('touchstart', event => {
                startX = event.touches[0]?.clientX ?? 0;
            }, {passive: true});
            this.track.addEventListener('touchend', event => {
                const endX = event.changedTouches[0]?.clientX ?? startX;
                if (Math.abs(startX - endX) > 50) this.scroll(startX > endX ? 1 : -1);
            }, {passive: true});
        }

        onResize() {
            this.recalculate();
            this.createDots();
            this.scrollToIndex(this.currentIndex);
        }

        startAutoSlide() {
            this.stopAutoSlide();
            if (this.maxIndex === 0) return;
            this.autoSlideInterval = window.setInterval(() => {
                this.scrollToIndex(this.currentIndex >= this.maxIndex ? 0 : this.currentIndex + 1);
            }, this.autoSlideDelay);
        }

        stopAutoSlide() {
            if (this.autoSlideInterval) window.clearInterval(this.autoSlideInterval);
            this.autoSlideInterval = null;
        }
    }

    const hydrate = container => {
        const roots = container?.matches?.('.deals-carousel')
            ? [container]
            : [...(container?.querySelectorAll?.('.deals-carousel') ?? [])];

        roots.forEach(root => {
            const instance = new DealsCarousel(root);
            instance.start();
            window.dealsCarousel = instance;
        });
    };

    if (window.PublicIslands) {
        window.PublicIslands.register('deals-carousel', {
            hydrate(root) {
                hydrate(root);
            },
        });
    }

    window.scrollCarousel = direction => window.dealsCarousel?.scroll(Number(direction));
    window.filterDeals = () => window.dealsCarousel?.filter();
    window.refreshDeals = async () => {
        const site = window.SITE || 'default';
        const button = document.querySelector('.refresh-deals-btn');
        if (button) button.disabled = true;

        try {
            const response = await fetch(`/api/${encodeURIComponent(site)}/deals/refresh`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            });
            if (!response.ok) throw new Error('Failed to refresh deals.');
            window.location.reload();
        } catch (error) {
            window.dealsCarousel?.toast(error.message || 'Failed to refresh deals.', 'error');
            if (button) button.disabled = false;
        }
    };
})();
