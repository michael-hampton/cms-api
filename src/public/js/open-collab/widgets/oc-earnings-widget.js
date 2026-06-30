/**
 * oc-earnings-widget.js
 * Owns everything for the contributor "earnings" surface:
 *   - earnings_stats_grid   (lifetime / available / withdrawn / in-progress)
 *   - earnings_finance_table (transactions + payouts + revenue breakdown + links)
 *
 * This widget is read-only — it has no mutating actions of its own, it only
 * renders data and links out to the payouts/disputes widgets.
 */
(() => {
    const {esc, cap, money, date, statsGrid, errorBox} = window.OpenCollabShared;

    class EarningsWidget {
        static components = ['earnings_stats_grid', 'earnings_finance_table'];

        constructor({site, context = {}}) {
            this.site = site;
            this.context = context;
        }

        normalise(component, raw) {
            if (component === 'earnings_stats_grid') {
                return {items: [
                        {label: 'Lifetime Earnings', value: raw.total ?? 0, format: 'money', variant: 'accent', sub: 'Gross revenue all time'},
                        {label: 'Available Balance', value: raw.available_to_withdraw ?? raw.available ?? raw.pending ?? 0, format: 'money', variant: 'green', sub: 'Ready to withdraw'},
                        {label: 'Total Paid Out', value: raw.withdrawn ?? 0, format: 'money', sub: 'Received to date'},
                        ...(Number(raw.pending_payouts ?? 0) > 0 ? [{label: 'In Progress', value: raw.pending_payouts, format: 'money', sub: 'Pending or approved'}] : []),
                    ]};
            }

            if (component === 'earnings_finance_table') {
                return {
                    transactions: raw.transactions ?? [],
                    breakdown: raw.breakdown ?? [],
                    payouts: this.context?.earnings?.payouts ?? [],
                    links: [
                        {label: 'Request a payout', href: '/contributor/payouts', variant: 'amber'},
                        {label: 'Earnings disputes', href: '/contributor/disputes', variant: 'ghost'},
                        {label: 'Payout method settings', href: '/contributor/settings#payment', variant: 'ghost'},
                    ],
                    total: raw.total ?? 0,
                };
            }

            return raw;
        }

        render(el, section, component, data) {
            if (component === 'earnings_stats_grid') {
                el.innerHTML = statsGrid(data.items ?? []);
                return;
            }
            if (component === 'earnings_finance_table') {
                this.renderFinanceTable(el, data);
                return;
            }
            el.innerHTML = errorBox(`Earnings widget cannot render: ${component}`);
        }

        renderFinanceTable(el, data) {
            const transactions = data.transactions ?? [];
            const payouts = data.payouts ?? [];
            const total = Number(data.total ?? 0);
            const breakdown = (data.breakdown ?? []).map(item => ({...item, percent: total > 0 ? Math.min(100, Math.round(Number(item.total || 0) / total * 100)) : 0}));
            el.innerHTML = `<div class="oc-grid-sidebar" style="align-items:start;gap:24px;"><div style="display:flex;flex-direction:column;gap:20px;">${this.transactionsCard(transactions)}${this.payoutsCard(payouts)}</div><div style="display:flex;flex-direction:column;gap:20px;position:sticky;top:calc(var(--header-h) + 20px);">${this.breakdownCard(breakdown)}${this.linksCard(data.links ?? [])}</div></div>`;
        }

        transactionsCard(rows) {
            if (!rows.length) return `<div class="oc-card" style="animation:fadeSlideIn .45s ease;"><div class="oc-card__header"><span class="oc-card__title">Transaction History</span><span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">0</span></div><div style="padding:40px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;">No transactions yet</div><div style="font-size:.82rem;margin-top:4px;">Publish a paid article and sales will appear here.</div></div></div>`;
            return `<div class="oc-card" style="animation:fadeSlideIn .45s ease;"><div class="oc-card__header"><span class="oc-card__title">Transaction History</span><span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">${rows.length}</span></div><table class="oc-table"><thead><tr><th>Date</th><th>Article</th><th>Type</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead><tbody>${rows.map(tx => { const isRefund = tx.status === 'refunded'; return `<tr><td style="white-space:nowrap;color:var(--slate);font-size:.78rem;">${date(tx.created_at)}</td><td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(tx.page_title ?? tx.title ?? '–')}</td><td><span class="oc-badge ${isRefund ? 'oc-badge--revoked' : 'oc-badge--published'}" style="font-size:.65rem;">${isRefund ? 'Refund' : 'Sale'}</span></td><td style="text-align:right;font-weight:600;color:${isRefund ? 'var(--red)' : 'var(--green)'};">${isRefund ? '−' : '+'}${money(tx.amount, tx.currency)}</td><td><span style="font-size:.75rem;color:var(--slate);">${cap(tx.status ?? 'succeeded')}</span></td></tr>`; }).join('')}</tbody></table></div>`;
        }

        payoutsCard(rows) {
            const {badge} = window.OpenCollabShared;
            if (!rows.length) return `<div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__header"><span class="oc-card__title">Payout History</span><a href="/contributor/payouts" class="oc-btn oc-btn--ghost oc-btn--sm">Request payout</a></div><div style="padding:40px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;margin-bottom:4px;">No payouts yet</div><div style="font-size:.82rem;">Once your balance reaches the minimum threshold, you can <a href="/contributor/payouts" style="color:var(--navy);">request a payout</a>.</div></div></div>`;
            return `<div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__header"><span class="oc-card__title">Payout History</span><a href="/contributor/payouts" class="oc-btn oc-btn--ghost oc-btn--sm">Request payout</a></div><table class="oc-table"><thead><tr><th>Payout ID</th><th>Amount</th><th>Status</th><th>Date</th><th>Download</th></tr></thead><tbody>${rows.map(p => `<tr><td style="font-family:monospace;font-size:.78rem;color:var(--slate);">PAY-${String(p.id).padStart(6,'0')}</td><td style="font-weight:600;color:var(--navy);">${money(p.amount_pence ?? p.amount, p.currency)}</td><td><span class="oc-badge ${badge(p.status)}">${cap(p.status)}</span></td><td style="font-size:.78rem;color:var(--slate);">${date(p.created_at)}</td><td>${['paid','approved'].includes(p.status) ? `<a href="/api/${esc(this.site)}/open-collab/payouts/${p.id}/statement" class="oc-btn oc-btn--ghost oc-btn--sm" download title="Download payout statement PDF">PDF</a>` : '<span style="font-size:.75rem;color:var(--slate);">—</span>'}</td></tr>${p.rejection_reason ? `<tr><td colspan="5" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Declined reason:</strong> ${esc(p.rejection_reason)}</td></tr>` : ''}`).join('')}</tbody></table></div>`;
        }

        breakdownCard(rows) {
            return `<div class="oc-card" style="animation:fadeSlideIn .45s ease;"><div class="oc-card__header"><span class="oc-card__title" style="font-size:.9rem;">Revenue by Article</span></div>${rows.length ? `<div style="padding:4px 0;">${rows.map(item => `<div style="padding:12px 20px;border-bottom:1px solid var(--border);"><div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;"><span style="font-size:.82rem;color:var(--navy);font-weight:500;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${esc(item.title)}">${esc(item.title ?? 'Untitled')}</span><span style="font-size:.875rem;font-weight:700;color:var(--navy);">${money(item.total)}</span></div><div style="height:3px;background:var(--border);border-radius:2px;overflow:hidden;"><div style="height:100%;width:${Number(item.percent || 0)}%;background:var(--amber);border-radius:2px;transition:width .4s;"></div></div></div>`).join('')}</div>` : '<div style="padding:24px;text-align:center;color:var(--slate);font-size:.85rem;">No revenue yet.</div>'}</div>`;
        }

        linksCard(links) {
            return `<div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__body" style="padding:18px 20px;display:flex;flex-direction:column;gap:10px;"><div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">Finance links</div>${links.map(link => `<a href="${esc(link.href)}" class="oc-btn oc-btn--${link.variant === 'amber' ? 'amber' : 'ghost'} oc-btn--block">${esc(link.label)}</a>`).join('')}</div></div>`;
        }
    }

    window.OpenCollabEarningsWidget = EarningsWidget;
})();