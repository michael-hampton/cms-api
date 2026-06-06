<!-- ══════════════════════════════════════════════════════════
     MINI CART PANEL
═══════════════════════════════════════════════════════════ -->
<div id="mini-cart-overlay" class="mc-overlay" onclick="MiniCart.close()" aria-hidden="true"></div>

<aside id="mini-cart-panel" class="mc-panel" role="dialog" aria-label="Shopping cart" aria-modal="true">

    <!-- Header -->
    <div class="mc-header">
        <div class="mc-header-left">
            <svg class="mc-header-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span class="mc-title">Your Cart</span>
            <span class="mc-count-badge" id="mc-badge">0</span>
        </div>
        <button class="mc-close-btn" onclick="MiniCart.close()" aria-label="Close cart">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    <!-- Scrollable item list -->
    <div class="mc-body" id="mc-body">

        <!-- Loading skeleton -->
        <div class="mc-loading" id="mc-loading">
            <div class="mc-skeleton-item">
                <div class="mc-skeleton mc-sk-img"></div>
                <div class="mc-skeleton-lines">
                    <div class="mc-skeleton mc-sk-line" style="width:70%"></div>
                    <div class="mc-skeleton mc-sk-line" style="width:45%"></div>
                    <div class="mc-skeleton mc-sk-line" style="width:55%"></div>
                </div>
            </div>
            <div class="mc-skeleton-item">
                <div class="mc-skeleton mc-sk-img"></div>
                <div class="mc-skeleton-lines">
                    <div class="mc-skeleton mc-sk-line" style="width:80%"></div>
                    <div class="mc-skeleton mc-sk-line" style="width:40%"></div>
                    <div class="mc-skeleton mc-sk-line" style="width:60%"></div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div class="mc-empty" id="mc-empty" style="display:none">
            <div class="mc-empty-icon">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25">
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
            </div>
            <p class="mc-empty-title">Your cart is empty</p>
            <p class="mc-empty-sub">Add products to get started</p>
            <button class="mc-shop-btn" onclick="MiniCart.close(); window.location.href='/shop'">
                Browse Products
            </button>
        </div>

        <!-- Items grouped by merchant -->
        <div id="mc-items" style="display:none"></div>

    </div><!-- /.mc-body -->

    <!-- Footer totals + CTA -->
    <div class="mc-footer" id="mc-footer" style="display:none">

        <div class="mc-voucher-row" id="mc-voucher-row">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 12V22H4V12"/>
                <path d="M22 7H2v5h20V7z"/>
                <path d="M12 22V7"/>
                <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/>
                <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
            </svg>
            <span id="mc-voucher-label">Have a voucher? Apply at checkout</span>
        </div>

        <div class="mc-totals">
            <div class="mc-total-row">
                <span>Subtotal</span>
                <span id="mc-subtotal">£0.00</span>
            </div>
            <div class="mc-total-row mc-shipping-row">
                <span>Shipping</span>
                <span id="mc-shipping">Calculated at checkout</span>
            </div>
            <div class="mc-total-row mc-total-main">
                <span>Estimated Total</span>
                <span id="mc-total">£0.00</span>
            </div>
        </div>

        <div class="mc-actions">
            <a href="/<?= \App\Framework\Support\SiteContext::slug()?>/checkout" class="mc-checkout-btn">
                Proceed to Checkout
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
            <a href="/cart" class="mc-view-cart-btn">View Full Cart</a>
        </div>

        <div class="mc-trust">
            <div class="mc-trust-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                SSL Secure
            </div>
            <div class="mc-trust-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Free returns
            </div>
            <div class="mc-trust-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                Safe payment
            </div>
        </div>
    </div>

</aside>

<style>
    /* ══════════════════════════════════════════
       MINI CART — VARIABLES & RESET
    ══════════════════════════════════════════ */
    :root {
        --mc-width: 420px;
        --mc-bg: #ffffff;
        --mc-border: #e8edf2;
        --mc-text: #1a202c;
        --mc-text-muted: #64748b;
        --mc-accent: #2563eb;
        --mc-accent-dark: #1d4ed8;
        --mc-success: #059669;
        --mc-danger: #dc2626;
        --mc-merchant-bg: #f1f5f9;
        --mc-shadow: 0 25px 60px rgba(0, 0, 0, .18), 0 8px 20px rgba(0, 0, 0, .10);
        --mc-radius: 16px;
        --mc-item-radius: 10px;
        --mc-font: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        --mc-transition: cubic-bezier(.4, 0, .2, 1);
    }

    /* Overlay */
    .mc-overlay {
        position: fixed;
        inset: 0;
        background: rgba(10, 15, 25, .45);
        backdrop-filter: blur(3px);
        -webkit-backdrop-filter: blur(3px);
        z-index: 9998;
        opacity: 0;
        pointer-events: none;
        transition: opacity .3s var(--mc-transition);
    }

    .mc-overlay.mc-active {
        opacity: 1;
        pointer-events: all;
    }

    /* Panel */
    .mc-panel {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: var(--mc-width);
        max-width: 100vw;
        background: var(--mc-bg);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        box-shadow: var(--mc-shadow);
        transform: translateX(100%);
        transition: transform .35s var(--mc-transition);
        font-family: var(--mc-font);
        border-left: 1px solid var(--mc-border);
        overflow: hidden;
    }

    .mc-panel.mc-active {
        transform: translateX(0);
    }

    /* Header */
    .mc-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.125rem 1.375rem;
        border-bottom: 1px solid var(--mc-border);
        background: var(--mc-bg);
        flex-shrink: 0;
        position: relative;
    }

    .mc-header::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, var(--mc-accent), transparent 60%);
    }

    .mc-header-left {
        display: flex;
        align-items: center;
        gap: .6rem;
    }

    .mc-header-icon {
        color: var(--mc-accent);
        flex-shrink: 0;
    }

    .mc-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--mc-text);
        letter-spacing: -.01em;
    }

    .mc-count-badge {
        background: var(--mc-accent);
        color: #fff;
        font-size: .7rem;
        font-weight: 700;
        padding: .15rem .45rem;
        border-radius: 99px;
        min-width: 20px;
        text-align: center;
        line-height: 1.4;
        transition: transform .2s, background .2s;
    }

    .mc-count-badge.mc-pop {
        transform: scale(1.3);
        background: var(--mc-success);
    }

    .mc-close-btn {
        background: none;
        border: none;
        cursor: pointer;
        padding: .4rem;
        border-radius: 8px;
        color: var(--mc-text-muted);
        transition: background .2s, color .2s;
        display: flex;
        align-items: center;
    }

    .mc-close-btn:hover {
        background: #f1f5f9;
        color: var(--mc-text);
    }

    /* Body */
    .mc-body {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        scroll-behavior: smooth;
    }

    .mc-body::-webkit-scrollbar {
        width: 4px;
    }

    .mc-body::-webkit-scrollbar-track {
        background: transparent;
    }

    .mc-body::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 99px;
    }

    /* Loading skeletons */
    .mc-loading {
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .mc-skeleton-item {
        display: flex;
        gap: .875rem;
        align-items: flex-start;
    }

    .mc-skeleton {
        background: linear-gradient(90deg, #f0f4f8 25%, #e2e8f0 50%, #f0f4f8 75%);
        background-size: 200% 100%;
        animation: mc-shimmer 1.4s infinite;
        border-radius: 6px;
    }

    .mc-sk-img {
        width: 72px;
        height: 72px;
        flex-shrink: 0;
        border-radius: var(--mc-item-radius);
    }

    .mc-skeleton-lines {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: .5rem;
        padding-top: .25rem;
    }

    .mc-sk-line {
        height: 13px;
    }

    @keyframes mc-shimmer {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: -200% 0;
        }
    }

    /* Empty state */
    .mc-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem 2rem;
        text-align: center;
        height: 100%;
        min-height: 320px;
    }

    .mc-empty-icon {
        color: #cbd5e1;
        margin-bottom: 1.25rem;
    }

    .mc-empty-title {
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--mc-text);
        margin-bottom: .375rem;
    }

    .mc-empty-sub {
        font-size: .875rem;
        color: var(--mc-text-muted);
        margin-bottom: 1.5rem;
    }

    .mc-shop-btn {
        background: var(--mc-accent);
        color: #fff;
        border: none;
        padding: .7rem 1.5rem;
        border-radius: 8px;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .2s, transform .15s;
    }

    .mc-shop-btn:hover {
        background: var(--mc-accent-dark);
        transform: translateY(-1px);
    }

    /* Merchant groups */
    .mc-merchant-group {
        border-bottom: 1px solid var(--mc-border);
    }

    .mc-merchant-group:last-child {
        border-bottom: none;
    }

    .mc-merchant-header {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .875rem 1.375rem .5rem;
        background: linear-gradient(135deg, var(--mc-merchant-bg) 0%, #e8eef6 100%);
        border-left: 3px solid var(--mc-accent);
    }

    .mc-merchant-icon {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        background: var(--mc-accent);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .mc-merchant-name {
        font-size: .8rem;
        font-weight: 700;
        color: var(--mc-text);
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mc-merchant-count {
        font-size: .72rem;
        color: var(--mc-text-muted);
        background: #fff;
        border: 1px solid var(--mc-border);
        padding: .1rem .45rem;
        border-radius: 99px;
        flex-shrink: 0;
    }

    /* Cart items */
    .mc-items-list {
        padding: .5rem 1.375rem;
        display: flex;
        flex-direction: column;
        gap: .125rem;
    }

    .mc-item {
        display: grid;
        grid-template-columns: 76px 1fr auto;
        gap: .875rem;
        padding: .875rem 0;
        border-bottom: 1px dashed #f0f4f8;
        align-items: start;
        animation: mc-slide-in .25s var(--mc-transition) both;
    }

    .mc-item:last-child {
        border-bottom: none;
    }

    .mc-item.mc-removing {
        opacity: .4;
        pointer-events: none;
        transition: opacity .2s;
    }

    @keyframes mc-slide-in {
        from {
            opacity: 0;
            transform: translateX(12px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Image */
    .mc-item-img-wrap {
        position: relative;
        width: 76px;
        height: 76px;
        border-radius: var(--mc-item-radius);
        overflow: hidden;
        border: 1px solid var(--mc-border);
        background: #f8fafc;
        flex-shrink: 0;
    }

    .mc-item-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .mc-item-img-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #cbd5e1;
    }

    .mc-gift-badge {
        position: absolute;
        top: 4px;
        left: 4px;
        background: var(--mc-success);
        color: #fff;
        font-size: .6rem;
        font-weight: 700;
        padding: .1rem .35rem;
        border-radius: 99px;
        text-transform: uppercase;
        letter-spacing: .04em;
        line-height: 1.4;
    }

    /* Item details */
    .mc-item-details {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: .3rem;
        padding-top: .1rem;
    }

    .mc-item-name {
        font-size: .875rem;
        font-weight: 600;
        color: var(--mc-text);
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .mc-item-variant {
        font-size: .75rem;
        color: var(--mc-text-muted);
        line-height: 1.3;
    }

    .mc-item-delivery {
        font-size: .72rem;
        color: var(--mc-success);
        display: flex;
        align-items: center;
        gap: .25rem;
    }

    .mc-item-price-row {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-top: .1rem;
    }

    .mc-item-price {
        font-size: .9rem;
        font-weight: 700;
        color: var(--mc-text);
    }

    .mc-item-price.mc-free {
        color: var(--mc-success);
    }

    .mc-item-qty {
        font-size: .75rem;
        color: var(--mc-text-muted);
        background: #f1f5f9;
        padding: .1rem .4rem;
        border-radius: 4px;
    }

    .mc-sub-badge {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        font-size: .7rem;
        font-weight: 600;
        color: var(--mc-accent);
        background: rgba(37, 99, 235, .08);
        border: 1px solid rgba(37, 99, 235, .2);
        padding: .15rem .45rem;
        border-radius: 99px;
        margin-top: .15rem;
        width: fit-content;
    }

    /* Remove button */
    .mc-item-remove {
        background: none;
        border: none;
        cursor: pointer;
        color: #cbd5e1;
        padding: .25rem;
        border-radius: 6px;
        transition: color .2s, background .2s;
        display: flex;
        align-items: center;
        flex-shrink: 0;
        margin-top: .05rem;
    }

    .mc-item-remove:hover {
        color: var(--mc-danger);
        background: #fef2f2;
    }

    /* Footer */
    .mc-footer {
        border-top: 1px solid var(--mc-border);
        padding: 1.125rem 1.375rem 1.375rem;
        background: #fafbfc;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        gap: .875rem;
    }

    .mc-voucher-row {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .78rem;
        color: var(--mc-text-muted);
        background: #f1f5f9;
        padding: .5rem .75rem;
        border-radius: 7px;
        border: 1px dashed #cbd5e1;
    }

    .mc-voucher-row.mc-voucher-active {
        background: #ecfdf5;
        border-color: #6ee7b7;
        color: var(--mc-success);
    }

    .mc-totals {
        display: flex;
        flex-direction: column;
        gap: .4rem;
    }

    .mc-total-row {
        display: flex;
        justify-content: space-between;
        font-size: .85rem;
        color: var(--mc-text-muted);
    }

    .mc-total-row span:last-child {
        font-weight: 500;
        color: var(--mc-text);
    }

    .mc-shipping-row span {
        font-style: italic;
        font-size: .8rem;
    }

    .mc-total-main {
        font-size: 1rem !important;
        font-weight: 700;
        color: var(--mc-text) !important;
        padding-top: .5rem;
        border-top: 1px solid var(--mc-border);
        margin-top: .15rem;
    }

    .mc-total-main span {
        color: var(--mc-text) !important;
        font-weight: 700;
    }

    .mc-actions {
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }

    .mc-checkout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        background: var(--mc-accent);
        color: #fff;
        text-decoration: none;
        padding: .875rem 1.25rem;
        border-radius: 10px;
        font-size: .9rem;
        font-weight: 700;
        letter-spacing: -.01em;
        transition: background .2s, transform .15s, box-shadow .2s;
        box-shadow: 0 2px 8px rgba(37, 99, 235, .25);
    }

    .mc-checkout-btn:hover {
        background: var(--mc-accent-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(37, 99, 235, .3);
    }

    .mc-view-cart-btn {
        display: block;
        text-align: center;
        text-decoration: none;
        color: var(--mc-text-muted);
        font-size: .82rem;
        font-weight: 500;
        padding: .5rem;
        border-radius: 8px;
        transition: color .2s, background .2s;
    }

    .mc-view-cart-btn:hover {
        color: var(--mc-accent);
        background: rgba(37, 99, 235, .05);
    }

    .mc-trust {
        display: flex;
        justify-content: center;
        gap: 1.25rem;
    }

    .mc-trust-item {
        display: flex;
        align-items: center;
        gap: .3rem;
        font-size: .7rem;
        color: var(--mc-text-muted);
    }

    /* Error toast inside panel */
    .mc-error-toast {
        position: absolute;
        bottom: 1rem;
        left: 1rem;
        right: 1rem;
        background: #fef2f2;
        border: 1px solid #fca5a5;
        color: #991b1b;
        font-size: .8rem;
        padding: .6rem .875rem;
        border-radius: 8px;
        z-index: 10000;
        animation: mc-slide-in .2s ease both;
    }

    /* Responsive */
    @media (max-width: 480px) {
        :root {
            --mc-width: 100vw;
        }

        .mc-panel {
            border-left: none;
            border-radius: 0;
        }
    }

    @media (min-width: 481px) and (max-width: 640px) {
        :root {
            --mc-width: 360px;
        }
    }
</style>

<script>
    /**
     * MiniCart — lightweight sliding cart panel controller.
     *
     * Public API:
     *   MiniCart.open()             — slide panel in & fetch cart
     *   MiniCart.close()            — slide panel out
     *   MiniCart.refresh()          — re-fetch cart without opening
     *   MiniCart.removeItem(id)     — delete item and re-render
     *   MiniCart.init()             — called automatically on DOMContentLoaded
     */
    const MiniCart = (() => {
        /* ── DOM selectors ─────────────────────────────────────────── */
        const SEL = {
            panel: () => document.getElementById('mini-cart-panel'),
            overlay: () => document.getElementById('mini-cart-overlay'),
            badge: () => document.getElementById('mc-badge'),
            loading: () => document.getElementById('mc-loading'),
            empty: () => document.getElementById('mc-empty'),
            items: () => document.getElementById('mc-items'),
            footer: () => document.getElementById('mc-footer'),
            body: () => document.getElementById('mc-body'),
            subtotal: () => document.getElementById('mc-subtotal'),
            shipping: () => document.getElementById('mc-shipping'),
            total: () => document.getElementById('mc-total'),
            voucherRow: () => document.getElementById('mc-voucher-row'),
            voucherLabel: () => document.getElementById('mc-voucher-label'),
        };

        let _cartData = null;
        let _open = false;
        let _fetching = false;

        /* ── Helpers ───────────────────────────────────────────────── */
        function _fmt(amount) {
            const sym = (typeof CURRENCY_SYMBOL !== 'undefined') ? CURRENCY_SYMBOL : '£';
            return sym + parseFloat(amount || 0).toFixed(2);
        }

        function _api() {
            const site = (typeof SITE !== 'undefined') ? SITE : '';
            return site ? `/api/${site}` : '/api';
        }

        function _esc(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function _initials(name) {
            return (name || '?')
                .split(' ')
                .filter(Boolean)
                .slice(0, 2)
                .map(w => w[0].toUpperCase())
                .join('') || '?';
        }

        /* ── Session voucher ───────────────────────────────────────── */
        function _getSessionVoucher() {
            try {
                const raw = sessionStorage.getItem('appliedVoucher');
                if (!raw) return null;
                const parsed = JSON.parse(raw);
                /* Validate minimal shape */
                if (parsed && parsed.code && parsed.discount !== undefined) {
                    return parsed;
                }
                return null;
            } catch (_) {
                return null;
            }
        }

        /* ── Item HTML builder ─────────────────────────────────────── */
        function _buildItem(item) {
            const isFree = item.price === 0 || item.price === '0'
                || (item.options?.is_gift === true)
                || (item.options?.type === 'free_gift');
            const isSub = !!item.subscription_plan_id;

            const imgHtml = item.product_image
                ? `<img class="mc-item-img" src="${_esc(item.product_image)}" alt="${_esc(item.product_name || item.name || '')}" loading="lazy">`
                : `<div class="mc-item-img-placeholder">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
               </div>`;

            const variantHtml = (item.variant_id && item.variant_options)
                ? Object.entries(item.variant_options)
                    .map(([k, v]) => `${_esc(k)}: <b>${_esc(String(v))}</b>`)
                    .join(' · ')
                : '';

            const deliveryHtml = item.estimated_delivery
                ? `<div class="mc-item-delivery">📦 ${_esc(item.estimated_delivery)}</div>` : '';

            const subBadge = isSub
                ? `<span class="mc-sub-badge">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
                ${_esc(item.options?.delivery_type || 'Subscription')}
               </span>` : '';

            const priceHtml = isFree
                ? `<span class="mc-item-price mc-free">FREE</span>`
                : `<span class="mc-item-price">${_fmt(item.price)}</span>`;

            return `
        <div class="mc-item" data-item-id="${item.id}">
            <div class="mc-item-img-wrap">
                ${isFree ? '<span class="mc-gift-badge">🎁 Gift</span>' : ''}
                ${imgHtml}
            </div>
            <div class="mc-item-details">
                <div class="mc-item-name">${_esc(item.product_name || item.name || 'Unknown')}</div>
                ${variantHtml ? `<div class="mc-item-variant">${variantHtml}</div>` : ''}
                ${subBadge}
                ${deliveryHtml}
                <div class="mc-item-price-row">
                    ${priceHtml}
                    <span class="mc-item-qty">×${item.quantity}</span>
                </div>
            </div>
            <button class="mc-item-remove" onclick="MiniCart.removeItem(${item.id})" title="Remove item">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    <path d="M10 11v6M14 11v6"/>
                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                </svg>
            </button>
        </div>`;
        }

        /* ── Render ────────────────────────────────────────────────── */
        function _render(data) {
            _cartData = data;
            const items = data.items || [];
            const count = data.count ?? items.length;

            /* Update all cart count elements on the page */
            document.querySelectorAll('#cart-count, .mc-cart-count').forEach(el => el.textContent = count);

            const badge = SEL.badge();
            if (badge) {
                badge.textContent = count;
                badge.classList.add('mc-pop');
                setTimeout(() => badge.classList.remove('mc-pop'), 400);
            }

            SEL.loading().style.display = 'none';

            if (!items.length) {
                SEL.empty().style.display = 'flex';
                SEL.items().style.display = 'none';
                SEL.footer().style.display = 'none';
                return;
            }

            SEL.empty().style.display = 'none';
            SEL.items().style.display = 'block';
            SEL.footer().style.display = 'flex';

            /* Group by merchant — use merchant_name from item, not raw ID */
            const byMerchant = {};
            const directKey = '__direct__';

            items.forEach(item => {
                const mid = item.options?.merchant_id || item.merchant_id || null;
                const key = mid ? String(mid) : directKey;
                const name = mid
                    ? (item.merchant_name || item.options?.merchant_name || `Merchant ${mid}`)
                    : 'Direct';

                if (!byMerchant[key]) {
                    byMerchant[key] = {name, id: mid, items: []};
                }
                byMerchant[key].items.push(item);
            });

            const groups = Object.values(byMerchant);
            /* Show merchant headers when there are multiple merchants or at least one real merchant */
            const showHeaders = groups.length > 1 || (groups.length === 1 && groups[0].id !== null);

            let html = '';
            groups.forEach(group => {
                if (showHeaders && group.id !== null) {
                    html += `<div class="mc-merchant-group">
                    <div class="mc-merchant-header">
                        <div class="mc-merchant-icon">${_esc(_initials(group.name))}</div>
                        <span class="mc-merchant-name">${_esc(group.name)}</span>
                        <span class="mc-merchant-count">${group.items.length} item${group.items.length > 1 ? 's' : ''}</span>
                    </div>
                    <div class="mc-items-list">${group.items.map(_buildItem).join('')}</div>
                </div>`;
                } else {
                    html += `<div class="mc-items-list" style="padding-top:.5rem">${group.items.map(_buildItem).join('')}</div>`;
                }
            });

            SEL.items().innerHTML = html;

            /* Totals */
            const subtotal = parseFloat(data.subtotal ?? data.total ?? 0);
            SEL.subtotal().textContent = _fmt(subtotal);
            SEL.total().textContent = _fmt(subtotal);   /* add tax/shipping if available */

            if (data.shipping > 0) {
                SEL.shipping().textContent = _fmt(data.shipping);
            } else if (data.requiresShipping === false) {
                SEL.shipping().textContent = 'Free (digital)';
            } else {
                SEL.shipping().textContent = 'Calculated at checkout';
            }

            /* Voucher row */
            const voucher = _getSessionVoucher();
            const vRow = SEL.voucherRow();
            const vLabel = SEL.voucherLabel();
            if (voucher && vRow && vLabel) {
                vRow.classList.add('mc-voucher-active');
                vLabel.textContent = `${voucher.code} — -${_fmt(voucher.discount)} off`;
            } else if (vLabel) {
                vRow?.classList.remove('mc-voucher-active');
                vLabel.textContent = 'Have a voucher? Apply at checkout';
            }
        }

        /* ── Fetch ─────────────────────────────────────────────────── */
        async function _fetch() {
            if (_fetching) return;
            _fetching = true;

            /* Show skeleton if first load */
            if (!_cartData) {
                SEL.loading().style.display = 'flex';
                SEL.empty().style.display = 'none';
                SEL.items().style.display = 'none';
                SEL.footer().style.display = 'none';
            }

            try {
                const res = await fetch(`${_api()}/cart`);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                _render(data);
            } catch (err) {
                console.error('[MiniCart] fetch error:', err);
                SEL.loading().style.display = 'none';
                /* Show a non-intrusive error inside the panel */
                _showError('Could not load your cart. Please refresh.');
            } finally {
                _fetching = false;
            }
        }

        /* ── Error toast inside panel ──────────────────────────────── */
        function _showError(msg) {
            const existing = SEL.panel()?.querySelector('.mc-error-toast');
            if (existing) existing.remove();

            const el = document.createElement('div');
            el.className = 'mc-error-toast';
            el.textContent = msg;
            SEL.panel()?.appendChild(el);
            setTimeout(() => el.remove(), 4000);
        }

        /* ── Public API ────────────────────────────────────────────── */
        function open() {
            if (_open) return;
            _open = true;

            SEL.panel()?.classList.add('mc-active');
            SEL.overlay()?.classList.add('mc-active');
            SEL.panel()?.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';   /* prevent background scroll */

            _fetch();

            /* Trap keyboard focus: close on Escape */
            document.addEventListener('keydown', _onKeyDown);
        }

        function close() {
            if (!_open) return;
            _open = false;

            SEL.panel()?.classList.remove('mc-active');
            SEL.overlay()?.classList.remove('mc-active');
            SEL.panel()?.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';

            document.removeEventListener('keydown', _onKeyDown);
        }

        function _onKeyDown(e) {
            if (e.key === 'Escape') close();
        }

        async function refresh() {
            await _fetch();
        }

        async function removeItem(itemId) {
            /* Optimistic UI — grey out the item immediately */
            const itemEl = document.querySelector(`.mc-item[data-item-id="${itemId}"]`);
            if (itemEl) itemEl.classList.add('mc-removing');

            try {
                const res = await fetch(`${_api()}/cart/${itemId}`, {method: 'DELETE'});
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();

                if (data.success) {
                    await _fetch();   /* full re-render with updated totals */
                } else {
                    /* Roll back optimistic UI */
                    if (itemEl) itemEl.classList.remove('mc-removing');
                    _showError(data.message || 'Could not remove item.');
                }
            } catch (err) {
                if (itemEl) itemEl.classList.remove('mc-removing');
                _showError('Could not remove item. Please try again.');
                console.error('[MiniCart] removeItem error:', err);
            }
        }

        function init() {
            /* Silently load count on page load so the badge is accurate */
            fetch(`${_api()}/cart`)
                .then(r => r.json())
                .then(data => {
                    const count = data.count ?? (data.items?.length ?? 0);
                    document.querySelectorAll('#cart-count, .mc-cart-count, #mc-badge')
                        .forEach(el => el.textContent = count);
                    _cartData = data;   /* cache so first open is instant */
                })
                .catch(() => { /* silently fail — not critical on page load */
                });
        }

        /* Auto-init */
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        return {open, close, refresh, removeItem, init};
    })();
</script>