(() => {
    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const money = (pence, currency = 'GBP') => `${String(currency).toUpperCase() === 'GBP' ? '£' : '$'}${((Number(pence || 0)) / 100).toFixed(2)}`;

    function statsGrid(items) {
        return `<div class="oc-stats" style="animation:fadeSlideIn .4s ease;margin-bottom:24px;">
            ${items.map((item) => `
                <div class="oc-stat${item.variant ? ` oc-stat--${esc(item.variant)}` : ''}">
                    <div class="oc-stat__label">${esc(item.label)}</div>
                    <div class="oc-stat__value">${item.format === 'money' ? money(item.value) : esc(item.value ?? 0)}</div>
                    <div class="oc-stat__sub">${esc(item.sub ?? '')}</div>
                </div>
            `).join('')}
        </div>`;
    }

    async function fetchJson(url) {
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${localStorage.getItem('oc_token') || ''}`,
            },
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        return res.json();
    }

    function insertStatsHost(surface) {
        const host = document.createElement('section');
        host.dataset.surfaceSection = surface.sections[0]?.key ?? surface.surface;
        host.innerHTML = `<div class="oc-card" style="animation:fadeSlideIn .4s ease;margin-bottom:24px;"><div class="oc-card__body oc-widget__loading"><div class="oc-skeleton-line"></div><div class="oc-skeleton-line oc-skeleton-line--short"></div></div></div>`;

        const legacyStats = document.querySelector('.oc-stats');
        if (surface.surface === 'admin.payouts.index' && legacyStats) {
            legacyStats.style.display = 'none';
            legacyStats.parentNode.insertBefore(host, legacyStats);
            return host;
        }

        const filterCard = document.querySelector('.oc-card[style*="margin-bottom:20px"]');
        if (filterCard) {
            filterCard.parentNode.insertBefore(host, filterCard);
            return host;
        }

        const main = document.getElementById('main-content');
        main?.prepend(host);
        return host;
    }

    function normaliseAdminPayoutStats(payload) {
        const rows = Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : []);
        const pending = rows.filter((row) => row.status === 'pending');
        const approved = rows.filter((row) => row.status === 'approved');
        const paid = rows.filter((row) => row.status === 'paid');
        const rejected = rows.filter((row) => row.status === 'rejected');
        const pendingTotal = pending.reduce((sum, row) => sum + Number(row.amount_pence ?? row.amount ?? 0), 0);
        const approvedTotal = approved.reduce((sum, row) => sum + Number(row.amount_pence ?? row.amount ?? 0), 0);

        return [
            {label: 'Pending Review', value: pending.length, format: 'number', variant: 'accent', sub: 'Awaiting approval'},
            {label: 'Pending Amount', value: pendingTotal, format: 'money', sub: 'Total in queue'},
            {label: 'Approved Amount', value: approvedTotal, format: 'money', sub: 'Waiting to be paid'},
            {label: 'Paid', value: paid.length, format: 'number', variant: 'green', sub: 'Completed payouts'},
            {label: 'Rejected', value: rejected.length, format: 'number', sub: 'Declined requests'},
            {label: 'Total Payouts', value: rows.length, format: 'number', sub: 'All time'},
        ];
    }

    function normaliseAdminDisputeStats(payload) {
        const rows = Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : []);

        return [
            {label: 'Open Disputes', value: rows.filter((row) => row.status === 'open').length, format: 'number', variant: 'accent', sub: 'Needs review'},
            {label: 'Resolved', value: rows.filter((row) => row.status === 'resolved').length, format: 'number', variant: 'green', sub: 'Closed in favour'},
            {label: 'Rejected', value: rows.filter((row) => row.status === 'rejected').length, format: 'number', sub: 'Rejected by admin'},
            {label: 'Total Disputes', value: rows.length, format: 'number', sub: 'All time'},
        ];
    }

    async function renderAdminSurfaceStats() {
        const surface = window.OPEN_COLLAB_ADMIN_SURFACE;
        if (!surface || !Array.isArray(surface.sections) || surface.sections.length === 0) {
            return;
        }

        const section = surface.sections[0];
        const host = insertStatsHost(surface);

        try {
            const payload = await fetchJson(section.endpoint);
            const items = section.component === 'admin_payout_stats_grid'
                ? normaliseAdminPayoutStats(payload)
                : normaliseAdminDisputeStats(payload);

            host.innerHTML = statsGrid(items);
        } catch (error) {
            host.innerHTML = `<div class="oc-card" style="padding:24px;text-align:center;color:var(--red);font-size:.875rem;">Could not load admin stats.</div>`;
        }
    }

    document.addEventListener('DOMContentLoaded', renderAdminSurfaceStats);
})();
