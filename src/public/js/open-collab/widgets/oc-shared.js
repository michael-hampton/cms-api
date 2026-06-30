/**
 * oc-shared.js
 * Formatting helpers + small UI primitives shared by every Open Collab widget.
 * Must be loaded before any widget file or the surface controller.
 */
(() => {
    const esc   = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const cap   = (value) => value ? String(value).charAt(0).toUpperCase() + String(value).slice(1) : '';
    const money = (pence, currency = 'GBP') => `${String(currency).toUpperCase() === 'GBP' ? '£' : '$'}${((Number(pence || 0)) / 100).toFixed(2)}`;
    const date  = (value) => value ? new Date(value).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}) : '—';
    const badge = (status) => ({paid:'oc-badge--published',approved:'oc-badge--free',pending:'oc-badge--waiting-approval',rejected:'oc-badge--revoked',resolved:'oc-badge--published',open:'oc-badge--waiting-approval',refunded:'oc-badge--revoked'}[status] ?? 'oc-badge--draft');

    /** Generic stat-card grid markup, shared by several widgets' summary sections. */
    function statsGrid(items = []) {
        return `<div class="oc-stats" style="animation:fadeSlideIn .4s ease;margin-bottom:24px;">${items.map(item => `<div class="oc-stat${item.variant ? ` oc-stat--${esc(item.variant)}` : ''}"><div class="oc-stat__label">${esc(item.label)}</div><div class="oc-stat__value">${item.format === 'money' ? money(item.value) : esc(item.value ?? 0)}</div><div class="oc-stat__sub">${esc(item.sub ?? '')}</div></div>`).join('')}</div>`;
    }

    function skeleton(title) {
        return `<div class="oc-card" style="animation:fadeSlideIn .4s ease;"><div class="oc-card__header"><span class="oc-card__title">${esc(title)}</span></div><div class="oc-card__body oc-widget__loading"><div class="oc-skeleton-line"></div><div class="oc-skeleton-line oc-skeleton-line--short"></div></div></div>`;
    }

    function errorBox(message) {
        return `<div class="oc-card" style="padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">${esc(message)}</div>`;
    }

    /** Toast relies on a single #status-toast element existing in the DOM (any widget can render it). */
    function toast(message, ok = true) {
        const el = document.getElementById('status-toast');
        if (!el) return;
        el.textContent = message;
        el.style.background = ok ? 'var(--navy)' : 'var(--red)';
        el.style.opacity = '1';
        setTimeout(() => { el.style.opacity = '0'; }, 2800);
    }

    const statusToastMarkup = `<div id="status-toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;z-index:300;pointer-events:none;"></div>`;

    window.OpenCollabShared = { esc, cap, money, date, badge, statsGrid, skeleton, errorBox, toast, statusToastMarkup };
})();