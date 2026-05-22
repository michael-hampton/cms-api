<?php

use App\Framework\Support\SiteContext;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Carousel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap"
          rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --ink: #1a1614;
            --ink-muted: #6b6460;
            --paper: #faf9f7;
            --paper-dark: #f0ede8;
            --border: #e8e3dc;
            --white: #ffffff;
            --accent: #c8501a;
            --accent-soft: #f5e8e2;
            --gold: #b8860b;
            --gold-light: #fef9ed;
            --green: #2d7a4f;
            --shadow-xs: 0 1px 3px rgba(0, 0, 0, .06);
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, .08);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, .12);
            --radius: 14px;
            --radius-sm: 8px;
            --transition: 200ms cubic-bezier(.4, 0, .2, 1);
            --font-display: 'Playfair Display', Georgia, serif;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --track-gap: 20px;
            --card-width: 220px;
        }

        body {
            font-family: var(--font-body);
            background: var(--paper);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Section wrapper ──────────────────────────────────────── */
        .cat-section {
            padding: 40px 0 48px;
        }

        .cat-section__head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 0 24px 20px;
            gap: 16px;
        }

        .cat-section__title {
            font-family: var(--font-display);
            font-size: clamp(22px, 3vw, 30px);
            font-weight: 700;
            letter-spacing: -.02em;
            line-height: 1.15;
        }

        .cat-section__title span {
            color: var(--accent);
        }

        .cat-section__sub {
            font-size: 13px;
            color: var(--ink-muted);
            margin-top: 4px;
            font-weight: 400;
        }

        .cat-section__nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .cat-nav-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1.5px solid var(--border);
            background: var(--white);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            color: var(--ink);
            flex-shrink: 0;
        }

        .cat-nav-btn:hover:not(:disabled) {
            border-color: var(--ink);
            background: var(--ink);
            color: var(--white);
        }

        .cat-nav-btn:disabled {
            opacity: .3;
            cursor: default;
        }

        .cat-nav-btn svg {
            width: 16px;
            height: 16px;
            display: block;
        }

        /* Dots */
        .cat-dots {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cat-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--border);
            border: none;
            padding: 0;
            cursor: pointer;
            transition: var(--transition);
        }

        .cat-dot.is-active {
            background: var(--ink);
            width: 18px;
            border-radius: 3px;
        }

        /* ── Track ────────────────────────────────────────────────── */
        .cat-track-outer {
            position: relative;
            overflow: hidden;
            padding: 4px 24px 16px;
            /* fade edges */
            mask-image: linear-gradient(to right, transparent 0px, black 28px, black calc(100% - 28px), transparent 100%);
            -webkit-mask-image: linear-gradient(to right, transparent 0px, black 28px, black calc(100% - 28px), transparent 100%);
        }

        .cat-track {
            display: flex;
            gap: var(--track-gap);
            transition: transform 420ms cubic-bezier(.4, 0, .2, 1);
            will-change: transform;
        }

        /* ── Cards ────────────────────────────────────────────────── */
        .cat-card {
            width: var(--card-width);
            flex-shrink: 0;
            border-radius: var(--radius);
            background: var(--white);
            border: 1.5px solid var(--border);
            overflow: hidden;
            cursor: pointer;
            transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .cat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
            border-color: transparent;
        }

        .cat-card.is-active {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px var(--accent), var(--shadow-sm);
        }

        /* Image area */
        .cat-card__img {
            width: 100%;
            height: 136px;
            overflow: hidden;
            background: var(--paper-dark);
            position: relative;
        }

        .cat-card__img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 360ms cubic-bezier(.4, 0, .2, 1);
        }

        .cat-card:hover .cat-card__img img {
            transform: scale(1.06);
        }

        /* Colour-block placeholder when no image */
        .cat-card__img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        /* Body */
        .cat-card__body {
            padding: 14px 16px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .cat-card__name {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.35;
            color: var(--ink);
        }

        .cat-card__count {
            font-size: 12px;
            color: var(--ink-muted);
        }

        .cat-card__arrow {
            position: absolute;
            bottom: 14px;
            right: 14px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--paper-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: translateX(-4px);
            transition: opacity var(--transition), transform var(--transition), background var(--transition);
        }

        .cat-card:hover .cat-card__arrow {
            opacity: 1;
            transform: translateX(0);
        }

        .cat-card.is-active .cat-card__arrow {
            opacity: 1;
            background: var(--accent-soft);
            transform: translateX(0);
        }

        .cat-card__arrow svg {
            width: 12px;
            height: 12px;
            color: var(--ink);
        }

        .cat-card.is-active .cat-card__arrow svg {
            color: var(--accent);
        }

        /* Active indicator strip */
        .cat-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 280ms cubic-bezier(.4, 0, .2, 1);
        }

        .cat-card.is-active::after {
            transform: scaleX(1);
        }

        /* ── Skeleton ─────────────────────────────────────────────── */
        .cat-skeleton {
            width: var(--card-width);
            flex-shrink: 0;
            border-radius: var(--radius);
            background: var(--white);
            border: 1.5px solid var(--border);
            overflow: hidden;
        }

        .cat-skeleton__img {
            height: 136px;
            background: linear-gradient(90deg, var(--paper-dark) 25%, var(--paper) 50%, var(--paper-dark) 75%);
            background-size: 300% 100%;
            animation: shimmer 1.4s infinite;
        }

        .cat-skeleton__body {
            padding: 14px 16px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .cat-skeleton__line {
            height: 12px;
            border-radius: 6px;
            background: linear-gradient(90deg, var(--paper-dark) 25%, var(--paper) 50%, var(--paper-dark) 75%);
            background-size: 300% 100%;
            animation: shimmer 1.4s infinite;
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        /* ── Progress bar ─────────────────────────────────────────── */
        .cat-progress {
            height: 2px;
            background: var(--border);
            border-radius: 1px;
            margin: 0 24px;
            overflow: hidden;
        }

        .cat-progress__fill {
            height: 100%;
            background: var(--ink);
            border-radius: 1px;
            transition: width 420ms cubic-bezier(.4, 0, .2, 1);
        }

        /* ── Touch hint ───────────────────────────────────────────── */
        @media (pointer: coarse) {
            .cat-section__nav {
                display: none;
            }
        }

        /* ── Demo frame (remove in production) ───────────────────── */
        .demo-frame {
            max-width: 960px;
            margin: 40px auto;
            background: var(--paper);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            padding: 0 0 24px;
        }
    </style>
</head>
<body>

<!--
    STANDALONE CATEGORY CAROUSEL
    ════════════════════════════
    Usage in your blade template:

        <div id="category-carousel"
             data-active-category=""
             data-site="<?= SiteContext::slug() ?>">
        </div>

    Then at the bottom of the page, pass your PHP data:

        <script>
          window.CATEGORY_CAROUSEL_DATA = <?= json_encode($categories ?? []) ?>;
          // Each item: { id, name, product_count, image_url?, emoji?, color? }
        </script>

    The JS class is self-contained below.
-->

<!-- DEMO only – remove wrapper in production -->

<div id="category-carousel"
     data-active-category=""
     data-site="demo">
</div>

<!-- Demo seed data – replace with your PHP json_encode output -->
<script>
    window.CATEGORY_CAROUSEL_DATA = [
        {id: 1, name: 'Electronics', product_count: 248, emoji: '🖥️', color: '#dbeafe'},
        {id: 2, name: 'Books & Literature', product_count: 183, emoji: '📚', color: '#fef9c3'},
        {id: 3, name: 'Kitchen & Home', product_count: 97, emoji: '🍳', color: '#dcfce7'},
        {id: 4, name: 'Clothing', product_count: 312, emoji: '👗', color: '#fce7f3'},
        {id: 5, name: 'Sports & Outdoor', product_count: 74, emoji: '🏕️', color: '#d1fae5'},
        {id: 6, name: 'Beauty & Care', product_count: 156, emoji: '💄', color: '#ffe4e6'},
        {id: 7, name: 'Toys & Games', product_count: 88, emoji: '🧸', color: '#f3e8ff'},
        {id: 8, name: 'Automotive', product_count: 42, emoji: '🚗', color: '#e0f2fe'},
        {id: 9, name: 'Garden & Tools', product_count: 61, emoji: '🪴', color: '#d1fae5'},
        {id: 10, name: 'Food & Beverage', product_count: 205, emoji: '🍷', color: '#fef3c7'},
    ];
</script>

<script>
    /**
     * CategoryCarousel
     * ════════════════
     * Self-contained, class/state-based carousel component.
     * No framework dependencies.
     *
     * State shape:
     *   categories      – Category[]      raw data
     *   activeId        – number|null     currently selected category
     *   page            – number          0-indexed current "page" of cards
     *   cardsPerPage    – number          computed from viewport
     *   loading         – boolean
     *
     * Public API:
     *   new CategoryCarousel(containerEl, options)
     *   instance.setActiveCategory(id)
     *   instance.destroy()
     */
    class CategoryCarousel {

        // ── Static config ────────────────────────────────────────────────
        static CARD_WIDTH = 220;
        static CARD_GAP = 20;
        static PEEK = 28;  // edge fade width in px

        static ARROW_SVG = {
            left: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>`,
            right: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>`,
            arrow: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>`,
        };

        // ── State ────────────────────────────────────────────────────────
        #state = {
            categories: [],
            activeId: null,
            page: 0,
            cardsPerPage: 4,
            loading: true,
        };

        // ── Internal ─────────────────────────────────────────────────────
        #container = null;
        #options = {};
        #els = {};
        #resizeObserver = null;
        #touchState = {startX: 0, startY: 0, tracking: false};

        /**
         * @param {HTMLElement} container
         * @param {object}      options
         * @param {string}      [options.onSelect]       callback(category)
         * @param {string}      [options.site]           site slug for links
         * @param {number|null} [options.initialActiveId]
         */
        constructor(container, options = {}) {
            this.#container = container;
            this.#options = {
                onSelect: options.onSelect ?? null,
                site: options.site ?? '',
                initialActiveId: options.initialActiveId ?? null,
            };

            this.#scaffold();
            this.#attachResizeObserver();
            this.#load();
        }

        // ── Public API ───────────────────────────────────────────────────

        setActiveCategory(id) {
            this.#setState({activeId: id === this.#state.activeId ? null : id});
        }

        destroy() {
            this.#resizeObserver?.disconnect();
            this.#container.innerHTML = '';
        }

        // ── Bootstrap ────────────────────────────────────────────────────

        #scaffold() {
            this.#container.innerHTML = `
            <section class="cat-section" aria-label="Browse by category">
                <div class="cat-section__head">
                    <div>
                        <h2 class="cat-section__title">Shop by <span>Category</span></h2>
                        <p class="cat-section__sub">Browse our curated collections</p>
                    </div>
                    <div class="cat-section__nav" role="group" aria-label="Carousel navigation">
                        <button class="cat-nav-btn" id="cat-prev" aria-label="Previous" disabled>
                            ${CategoryCarousel.ARROW_SVG.left}
                        </button>
                        <div class="cat-dots" id="cat-dots" aria-hidden="true"></div>
                        <button class="cat-nav-btn" id="cat-next" aria-label="Next">
                            ${CategoryCarousel.ARROW_SVG.right}
                        </button>
                    </div>
                </div>

                <div class="cat-track-outer" id="cat-track-outer">
                    <div class="cat-track" id="cat-track" role="list"></div>
                </div>

                <div class="cat-progress" aria-hidden="true">
                    <div class="cat-progress__fill" id="cat-progress-fill"></div>
                </div>
            </section>`;

            this.#els = {
                track: this.#container.querySelector('#cat-track'),
                trackOuter: this.#container.querySelector('#cat-track-outer'),
                prev: this.#container.querySelector('#cat-prev'),
                next: this.#container.querySelector('#cat-next'),
                dots: this.#container.querySelector('#cat-dots'),
                progressFill: this.#container.querySelector('#cat-progress-fill'),
            };

            this.#els.prev.addEventListener('click', () => this.#navigate(-1));
            this.#els.next.addEventListener('click', () => this.#navigate(1));
            this.#attachSwipe();
        }

        #attachResizeObserver() {
            this.#resizeObserver = new ResizeObserver(() => {
                const cpp = this.#computeCardsPerPage();
                if (cpp !== this.#state.cardsPerPage) {
                    this.#setState({cardsPerPage: cpp, page: 0});
                }
            });
            this.#resizeObserver.observe(this.#container);
        }

        async #load() {
            this.#setState({loading: true});

            // Prefer data passed from PHP via window global
            const raw = window.CATEGORY_CAROUSEL_DATA ?? [];
            const categories = raw.filter(c => c.product_count > 0);

            this.#setState({
                loading: false,
                categories,
                activeId: this.#options.initialActiveId ?? null,
                cardsPerPage: this.#computeCardsPerPage(),
            });
        }

        // ── State ────────────────────────────────────────────────────────

        #setState(patch) {
            // Clamp page to valid range after any mutation
            const merged = {...this.#state, ...patch};
            const totalPages = this.#totalPages(merged);
            merged.page = Math.min(Math.max(merged.page, 0), Math.max(totalPages - 1, 0));
            Object.assign(this.#state, merged);
            this.#render();
        }

        // ── Navigation ───────────────────────────────────────────────────

        #navigate(dir) {
            this.#setState({page: this.#state.page + dir});
        }

        #goToPage(index) {
            this.#setState({page: index});
        }

        // ── Rendering ────────────────────────────────────────────────────

        #render() {
            const {loading, categories, page, cardsPerPage, activeId} = this.#state;
            const totalPages = this.#totalPages(this.#state);

            if (loading) {
                this.#renderSkeletons(cardsPerPage + 1);
                return;
            }

            this.#renderCards(categories, activeId);
            this.#updateTrackPosition(page, cardsPerPage);
            this.#renderDots(totalPages, page);
            this.#updateNavButtons(page, totalPages);
            this.#updateProgress(page, totalPages);
        }

        #renderSkeletons(count) {
            this.#els.track.innerHTML = Array.from({length: count}, () => `
            <div class="cat-skeleton" role="listitem" aria-hidden="true">
                <div class="cat-skeleton__img"></div>
                <div class="cat-skeleton__body">
                    <div class="cat-skeleton__line" style="width:70%"></div>
                    <div class="cat-skeleton__line" style="width:40%"></div>
                </div>
            </div>`).join('');
        }

        #renderCards(categories, activeId) {
            if (categories.length === 0) {
                this.#els.track.innerHTML = `
                <div style="padding:40px 24px; font-size:14px; color:var(--ink-muted);">
                    No categories available.
                </div>`;
                return;
            }

            this.#els.track.innerHTML = categories.map(cat => this.#cardHtml(cat, activeId)).join('');

            // Attach click handlers
            this.#els.track.querySelectorAll('.cat-card').forEach(el => {
                const id = Number(el.dataset.id);
                el.addEventListener('click', e => {
                    e.preventDefault();
                    this.#handleCardClick(id);
                });
                // Allow keyboard activation
                el.addEventListener('keydown', e => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.#handleCardClick(id);
                    }
                });
            });
        }

        #cardHtml(cat, activeId) {
            const isActive = cat.id === activeId;
            const href = this.#options.site
                ? `/shop?category=${cat.id}`
                : `#cat-${cat.id}`;

            const imageHtml = cat.image_url
                ? `<img src="${this.#escape(cat.image_url)}" alt="${this.#escape(cat.name)}" loading="lazy">`
                : `<div class="cat-card__img-placeholder" style="background:${this.#escape(cat.color ?? '#f0ede8')}">${this.#escape(cat.emoji ?? '📦')}</div>`;

            const countLabel = cat.product_count === 1
                ? '1 product'
                : `${cat.product_count.toLocaleString()} products`;

            return `
            <a class="cat-card ${isActive ? 'is-active' : ''}"
               role="listitem"
               href="${href}"
               data-id="${cat.id}"
               aria-pressed="${isActive}"
               aria-label="${this.#escape(cat.name)}, ${countLabel}${isActive ? ', selected' : ''}">
                <div class="cat-card__img">${imageHtml}</div>
                <div class="cat-card__body">
                    <div class="cat-card__name">${this.#escape(cat.name)}</div>
                    <div class="cat-card__count">${countLabel}</div>
                </div>
                <div class="cat-card__arrow" aria-hidden="true">
                    ${CategoryCarousel.ARROW_SVG.arrow}
                </div>
            </a>`;
        }

        #updateTrackPosition(page, cardsPerPage) {
            const step = CategoryCarousel.CARD_WIDTH + CategoryCarousel.CARD_GAP;
            const offset = page * cardsPerPage * step;
            this.#els.track.style.transform = `translateX(-${offset}px)`;
        }

        #renderDots(totalPages, currentPage) {
            if (totalPages <= 1) {
                this.#els.dots.innerHTML = '';
                return;
            }

            this.#els.dots.innerHTML = Array.from({length: totalPages}, (_, i) => `
            <button class="cat-dot ${i === currentPage ? 'is-active' : ''}"
                    aria-label="Go to page ${i + 1} of ${totalPages}"
                    data-page="${i}">
            </button>`).join('');

            this.#els.dots.querySelectorAll('.cat-dot').forEach(dot => {
                dot.addEventListener('click', () => this.#goToPage(Number(dot.dataset.page)));
            });
        }

        #updateNavButtons(page, totalPages) {
            this.#els.prev.disabled = page === 0;
            this.#els.next.disabled = page >= totalPages - 1;
        }

        #updateProgress(page, totalPages) {
            const pct = totalPages <= 1 ? 100 : Math.round(((page + 1) / totalPages) * 100);
            this.#els.progressFill.style.width = `${pct}%`;
        }

        // ── Event handlers ───────────────────────────────────────────────

        #handleCardClick(id) {
            const wasActive = this.#state.activeId === id;
            const newActiveId = wasActive ? null : id;

            this.#setState({activeId: newActiveId});

            if (this.#options.onSelect) {
                const cat = this.#state.categories.find(c => c.id === id) ?? null;
                this.#options.onSelect(wasActive ? null : cat);
            }

            // Dispatch DOM event for external listeners (e.g. products.js)
            this.#container.dispatchEvent(new CustomEvent('category:select', {
                bubbles: true,
                detail: {categoryId: newActiveId},
            }));
        }

        // ── Touch / swipe ────────────────────────────────────────────────

        #attachSwipe() {
            const outer = this.#els.trackOuter;

            outer.addEventListener('touchstart', e => {
                this.#touchState = {
                    startX: e.touches[0].clientX,
                    startY: e.touches[0].clientY,
                    tracking: true,
                };
            }, {passive: true});

            outer.addEventListener('touchmove', e => {
                if (!this.#touchState.tracking) return;
                const dx = e.touches[0].clientX - this.#touchState.startX;
                const dy = e.touches[0].clientY - this.#touchState.startY;
                // Cancel if scrolling vertically
                if (Math.abs(dy) > Math.abs(dx)) {
                    this.#touchState.tracking = false;
                }
            }, {passive: true});

            outer.addEventListener('touchend', e => {
                if (!this.#touchState.tracking) return;
                const dx = e.changedTouches[0].clientX - this.#touchState.startX;
                if (Math.abs(dx) > 40) {
                    this.#navigate(dx < 0 ? 1 : -1);
                }
                this.#touchState.tracking = false;
            }, {passive: true});
        }

        // ── Utilities ────────────────────────────────────────────────────

        #computeCardsPerPage() {
            const outerWidth = this.#els.trackOuter?.offsetWidth ?? 800;
            const available = outerWidth - CategoryCarousel.PEEK * 2;
            const perPage = Math.floor(available / (CategoryCarousel.CARD_WIDTH + CategoryCarousel.CARD_GAP));
            return Math.max(1, perPage);
        }

        #totalPages(state) {
            const {categories, cardsPerPage} = state;
            if (!categories.length || !cardsPerPage) return 1;
            return Math.ceil(categories.length / cardsPerPage);
        }

        #escape(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    }

    // ── Auto-init ────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('category-carousel');
        if (!container) return;

        const site = container.dataset.site ?? '';
        const initialActiveId = container.dataset.activeCategory
            ? Number(container.dataset.activeCategory)
            : null;

        window.categoryCarousel = new CategoryCarousel(container, {
            site,
            initialActiveId,
            onSelect: (category) => {
                // Delegate entirely to your app state management layer to filter products and update URLs cleanly
                // if (category) {
                //     if (typeof window.switchTab === 'function') {
                //         window.switchTab(`cat-${category.id}`);
                //     }
                // } else {
                //     if (typeof window.switchTab === 'function') {
                //         window.switchTab('all');
                //     }
                // }
            },
        });
    });
</script>

</body>
</html>