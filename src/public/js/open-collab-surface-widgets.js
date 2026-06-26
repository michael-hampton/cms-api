(() => {
    const fmtMoney = (pence, currency = 'GBP') => {
        const symbol = String(currency).toUpperCase() === 'GBP' ? '£' : '$';
        return `${symbol}${((Number(pence || 0)) / 100).toFixed(2)}`;
    };

    const fmtDate = (value) => value
        ? new Date(value).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})
        : '—';

    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const cap = (value) => value ? String(value).charAt(0).toUpperCase() + String(value).slice(1) : '';

    const statClass = (variant) => variant ? ` oc-stat--${esc(variant)}` : '';

    const buttonClass = (variant) => {
        if (variant === 'amber') return 'oc-btn oc-btn--amber oc-btn--block';
        if (variant === 'ghost') return 'oc-btn oc-btn--ghost oc-btn--block';
        return 'oc-btn oc-btn--ghost oc-btn--block';
    };

    const statusBadgeClass = (status) => ({
        paid: 'oc-badge--published',
        approved: 'oc-badge--free',
        pending: 'oc-badge--waiting-approval',
        rejected: 'oc-badge--revoked',
        resolved: 'oc-badge--published',
        open: 'oc-badge--waiting-approval',
        refunded: 'oc-badge--revoked',
        succeeded: 'oc-badge--published',
    }[status] ?? 'oc-badge--draft');

    class OpenCollabSurfaceRenderer {
        constructor({surface, site, sections, token}) {
            this.surface = surface;
            this.site = site;
            this.sections = Array.isArray(sections) ? sections : [];
            this.token = token;
            this.state = {
                payoutFilter: 'all',
                disputeFilter: 'all',
                payoutData: [],
                disputeData: [],
                eligibleDisputeEntries: [],
                payoutBalance: 0,
                minimumPayout: 5000,
            };
        }

        init() {
            this.sections.forEach((section) => this.mount(section));
        }

        async mount(section) {
            const el = document.querySelector(`[data-surface-section="${CSS.escape(section.key)}"]`);
            if (!el) return;

            this.renderSkeleton(el, section);

            try {
                const payload = await this.fetchSection(section);
                const renderer = this.renderers()[section.component];

                if (!renderer) {
                    el.innerHTML = this.errorHtml(`No renderer registered for ${esc(section.component)}`);
                    return;
                }

                renderer.call(this, el, section, payload.data ?? {});
            } catch (error) {
                el.innerHTML = this.errorHtml('Could not load this section.');
            }
        }

        async fetchSection(section) {
            const res = await fetch(section.endpoint, {
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${this.token()}`,
                },
            });

            if (!res.ok) {
                throw new Error(`Section ${section.key} failed with ${res.status}`);
            }

            return res.json();
        }

        renderSkeleton(el, section) {
            el.innerHTML = `
                <div class="oc-card" style="animation:fadeSlideIn .4s ease;">
                    <div class="oc-card__header"><span class="oc-card__title">${esc(section.title)}</span></div>
                    <div class="oc-card__body oc-widget__loading">
                        <div class="oc-skeleton-line"></div>
                        <div class="oc-skeleton-line oc-skeleton-line--short"></div>
                    </div>
                </div>`;
        }

        errorHtml(message) {
            return `<div class="oc-card" style="padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">${esc(message)}</div>`;
        }

        renderers() {
            return {
                stats_grid: this.renderStatsGrid,
                payout_history_table: this.renderPayoutHistory,
                earnings_finance_table: this.renderEarningsFinance,
                disputes_table: this.renderDisputes,
            };
        }

        renderStatsGrid(el, section, data) {
            const items = data.items ?? [];
            el.innerHTML = `<div class="oc-stats" style="animation:fadeSlideIn .4s ease;margin-bottom:24px;">
                ${items.map((item) => `
                    <div class="oc-stat${statClass(item.variant)}">
                        <div class="oc-stat__label">${esc(item.label)}</div>
                        <div class="oc-stat__value">${item.format === 'money' ? fmtMoney(item.value) : esc(item.value ?? 0)}</div>
                        <div class="oc-stat__sub">${esc(item.sub ?? '')}</div>
                    </div>`).join('')}
            </div>`;
        }

        renderPayoutHistory(el, section, data) {
            this.state.payoutData = data.items ?? [];
            this.state.payoutBalance = Number(data.available_balance ?? 0);
            this.state.minimumPayout = Number(data.minimum_payout ?? 5000);

            el.innerHTML = `
                <div class="oc-grid-sidebar" style="align-items:start;">
                    <div class="oc-card" style="animation:fadeSlideIn .45s ease;">
                        <div class="oc-card__header">
                            <span class="oc-card__title" data-payout-title>Payout History</span>
                            <span data-payout-count style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
                        </div>
                        <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;">
                            ${['all','pending','approved','paid','rejected'].map((status, i) => `<button class="filter-pill${i === 0 ? ' filter-pill--active' : ''}" data-payout-filter="${status}">${cap(status)}</button>`).join('')}
                        </div>
                        <div data-payout-empty style="display:none;padding:48px 24px;text-align:center;color:var(--slate);">
                            <div style="font-weight:500;margin-bottom:6px;" data-payout-empty-title>No payout requests yet</div>
                            <div style="font-size:.85rem;" data-payout-empty-sub>Once your balance reaches £50.00, you can request a payout.</div>
                        </div>
                        <div data-payout-table-wrap style="display:none;overflow-x:auto;">
                            <table class="oc-table">
                                <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Reference</th><th></th></tr></thead>
                                <tbody data-payout-tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div style="position:sticky;top:calc(var(--header-h) + 20px);">
                        <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
                            <div class="oc-card__header"><span class="oc-card__title" style="font-size:.95rem;">Request Payout</span></div>
                            <div class="oc-card__body">
                                <div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px;text-align:center;">
                                    <div style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">Available now</div>
                                    <div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy);" data-payout-balance>—</div>
                                </div>
                                <div data-payout-errors class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
                                <div data-payout-form-wrap>
                                    <div class="oc-form-group">
                                        <label class="oc-label" for="payout-method">Payout method</label>
                                        <select class="oc-select" data-payout-method id="payout-method">
                                            <option value="bank_transfer">Bank transfer</option>
                                            <option value="paypal">PayPal</option>
                                            <option value="other">Other</option>
                                        </select>
                                        <div class="oc-help">Your payout details are configured in <a href="/contributor/settings#payment">Settings</a>.</div>
                                    </div>
                                    <button class="oc-btn oc-btn--amber oc-btn--block" data-payout-submit disabled>Request payout</button>
                                </div>
                                <div data-payout-minimum-note style="display:none;padding:16px;text-align:center;border:1.5px dashed var(--border);border-radius:var(--radius);">
                                    <div style="font-size:.85rem;font-weight:500;margin-bottom:4px;color:var(--navy);">Minimum not reached</div>
                                    <div style="font-size:.78rem;color:var(--slate);">You need at least <strong>${fmtMoney(this.state.minimumPayout)}</strong> to request a payout.</div>
                                </div>
                                <div style="font-size:.72rem;color:var(--slate);line-height:1.6;padding-top:12px;border-top:1px solid var(--border);margin-top:16px;">
                                    Payouts are processed manually by our team, typically within 2–5 business days after approval.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;

            el.querySelectorAll('[data-payout-filter]').forEach((button) => {
                button.addEventListener('click', () => {
                    this.state.payoutFilter = button.dataset.payoutFilter;
                    el.querySelectorAll('[data-payout-filter]').forEach((b) => b.classList.remove('filter-pill--active'));
                    button.classList.add('filter-pill--active');
                    this.paintPayoutRows(el);
                });
            });

            el.querySelector('[data-payout-submit]').addEventListener('click', () => this.requestPayout(el));
            this.paintPayoutBalance(el);
            this.paintPayoutRows(el);
        }

        paintPayoutBalance(el) {
            const balance = this.state.payoutBalance;
            const button = el.querySelector('[data-payout-submit]');
            el.querySelector('[data-payout-balance]').textContent = fmtMoney(balance);
            button.textContent = `Request ${fmtMoney(balance)}`;

            const canRequest = balance >= this.state.minimumPayout;
            button.disabled = !canRequest;
            el.querySelector('[data-payout-form-wrap]').style.display = canRequest ? 'block' : 'none';
            el.querySelector('[data-payout-minimum-note]').style.display = canRequest ? 'none' : 'block';
        }

        paintPayoutRows(el) {
            const filtered = this.state.payoutFilter === 'all'
                ? this.state.payoutData
                : this.state.payoutData.filter((p) => p.status === this.state.payoutFilter);

            el.querySelector('[data-payout-count]').textContent = filtered.length;
            el.querySelector('[data-payout-title]').textContent = this.state.payoutFilter === 'all' ? 'Payout History' : `${cap(this.state.payoutFilter)} Payouts`;

            if (!filtered.length) {
                el.querySelector('[data-payout-empty]').style.display = 'block';
                el.querySelector('[data-payout-table-wrap]').style.display = 'none';
                el.querySelector('[data-payout-empty-title]').textContent = this.state.payoutFilter !== 'all' ? `No ${this.state.payoutFilter} payouts` : 'No payout requests yet';
                el.querySelector('[data-payout-empty-sub]').textContent = this.state.payoutFilter !== 'all' ? '' : 'Once your balance reaches £50.00, you can request a payout.';
                return;
            }

            el.querySelector('[data-payout-empty]').style.display = 'none';
            el.querySelector('[data-payout-table-wrap]').style.display = 'block';
            el.querySelector('[data-payout-tbody]').innerHTML = filtered.map((p) => {
                const downloadBtn = ['paid', 'approved'].includes(p.status)
                    ? `<a href="/api/${esc(this.site)}/open-collab/payouts/${p.id}/statement" class="oc-btn oc-btn--ghost oc-btn--sm" download title="Download PDF">PDF</a>`
                    : '<span style="font-size:.75rem;color:var(--slate);">—</span>';
                const rejectionRow = p.rejection_reason
                    ? `<tr><td colspan="6" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Rejection reason:</strong> ${esc(p.rejection_reason)}</td></tr>`
                    : '';

                return `<tr>
                    <td style="white-space:nowrap;color:var(--slate);">${fmtDate(p.created_at)}</td>
                    <td style="font-weight:600;font-family:var(--font-display);font-size:1rem;color:var(--navy);">${fmtMoney(p.amount_pence ?? p.amount, p.currency)}</td>
                    <td style="color:var(--slate);font-size:.85rem;">${esc((p.method ?? '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()))}</td>
                    <td><span class="oc-badge ${statusBadgeClass(p.status)}">${cap(p.status)}</span></td>
                    <td style="color:var(--slate);font-size:.82rem;font-family:monospace;">${esc(p.reference ?? '—')}</td>
                    <td style="text-align:right;">${downloadBtn}</td>
                </tr>${rejectionRow}`;
            }).join('');
        }

        async requestPayout(el) {
            const button = el.querySelector('[data-payout-submit]');
            const errors = el.querySelector('[data-payout-errors]');
            const method = el.querySelector('[data-payout-method]').value;
            errors.style.display = 'none';
            button.disabled = true;
            button.innerHTML = '<div class="oc-spinner"></div> Submitting…';

            try {
                const res = await fetch(`/api/${this.site}/open-collab/payouts`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${this.token()}`},
                    body: JSON.stringify({method}),
                });
                const data = await res.json();

                if (!res.ok) {
                    errors.textContent = data.message || data.error || 'Request failed. Please try again.';
                    errors.style.display = 'block';
                    button.disabled = false;
                    button.textContent = 'Request payout';
                    return;
                }

                this.toast('✓ Payout request submitted. Our team will process it shortly.');
                const historySection = this.sections.find((section) => section.component === 'payout_history_table');
                if (historySection) this.mount(historySection);
            } catch {
                errors.textContent = 'Network error. Please try again.';
                errors.style.display = 'block';
                button.disabled = false;
                button.textContent = 'Request payout';
            }
        }

        renderEarningsFinance(el, section, data) {
            const transactions = data.transactions ?? [];
            const payouts = data.payouts ?? [];
            const breakdown = data.breakdown ?? [];
            const links = data.links ?? [];

            el.innerHTML = `
                <div class="oc-grid-sidebar" style="align-items:start;gap:24px;">
                    <div style="display:flex;flex-direction:column;gap:20px;">
                        ${this.transactionsCard(transactions)}
                        ${this.payoutHistoryCard(payouts)}
                    </div>
                    <div style="display:flex;flex-direction:column;gap:20px;position:sticky;top:calc(var(--header-h) + 20px);">
                        ${this.revenueBreakdownCard(breakdown)}
                        ${this.financeLinksCard(links)}
                    </div>
                </div>`;
        }

        transactionsCard(transactions) {
            if (!transactions.length) {
                return `<div class="oc-card" style="animation:fadeSlideIn .45s ease;"><div class="oc-card__header"><span class="oc-card__title">Transaction History</span><span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">0</span></div><div style="padding:40px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;">No transactions yet</div><div style="font-size:.82rem;margin-top:4px;">Publish a paid article and sales will appear here.</div></div></div>`;
            }

            return `<div class="oc-card" style="animation:fadeSlideIn .45s ease;">
                <div class="oc-card__header"><span class="oc-card__title">Transaction History</span><span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">${transactions.length}</span></div>
                <table class="oc-table"><thead><tr><th>Date</th><th>Article</th><th>Type</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead><tbody>
                    ${transactions.map((tx) => {
                        const isRefund = tx.status === 'refunded';
                        return `<tr>
                            <td style="white-space:nowrap;color:var(--slate);font-size:.78rem;">${fmtDate(tx.created_at)}</td>
                            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(tx.page_title ?? '–')}</td>
                            <td><span class="oc-badge ${isRefund ? 'oc-badge--revoked' : 'oc-badge--published'}" style="font-size:.65rem;">${isRefund ? 'Refund' : 'Sale'}</span></td>
                            <td style="text-align:right;font-weight:600;color:${isRefund ? 'var(--red)' : 'var(--green)'};">${isRefund ? '−' : '+'}${fmtMoney(tx.amount, tx.currency)}</td>
                            <td><span style="font-size:.75rem;color:var(--slate);">${cap(tx.status ?? 'succeeded')}</span></td>
                        </tr>`;
                    }).join('')}
                </tbody></table>
            </div>`;
        }

        payoutHistoryCard(payouts) {
            if (!payouts.length) {
                return `<div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__header"><span class="oc-card__title">Payout History</span><a href="/contributor/payouts" class="oc-btn oc-btn--ghost oc-btn--sm">Request payout</a></div><div style="padding:40px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;margin-bottom:4px;">No payouts yet</div><div style="font-size:.82rem;">Once your balance reaches the minimum threshold, you can <a href="/contributor/payouts" style="color:var(--navy);">request a payout</a>.</div></div></div>`;
            }

            return `<div class="oc-card" style="animation:fadeSlideIn .5s ease;">
                <div class="oc-card__header"><span class="oc-card__title">Payout History</span><a href="/contributor/payouts" class="oc-btn oc-btn--ghost oc-btn--sm">Request payout</a></div>
                <table class="oc-table"><thead><tr><th>Payout ID</th><th>Amount</th><th>Status</th><th>Date</th><th>Download</th></tr></thead><tbody>
                    ${payouts.map((payout) => {
                        const status = payout.status ?? 'pending';
                        const download = ['paid', 'approved'].includes(status)
                            ? `<a href="/api/${esc(this.site)}/open-collab/payouts/${payout.id}/statement" class="oc-btn oc-btn--ghost oc-btn--sm" download title="Download payout statement PDF">PDF</a>`
                            : '<span style="font-size:.75rem;color:var(--slate);">—</span>';
                        const rejection = payout.rejection_reason
                            ? `<tr><td colspan="5" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Declined reason:</strong> ${esc(payout.rejection_reason)}</td></tr>`
                            : '';
                        return `<tr><td style="font-family:monospace;font-size:.78rem;color:var(--slate);">PAY-${String(payout.id).padStart(6, '0')}</td><td style="font-weight:600;color:var(--navy);">${fmtMoney(payout.amount_pence ?? payout.amount, payout.currency)}</td><td><span class="oc-badge ${statusBadgeClass(status)}">${cap(status)}</span></td><td style="font-size:.78rem;color:var(--slate);">${fmtDate(payout.created_at)}</td><td>${download}</td></tr>${rejection}`;
                    }).join('')}
                </tbody></table>
            </div>`;
        }

        revenueBreakdownCard(breakdown) {
            return `<div class="oc-card" style="animation:fadeSlideIn .45s ease;">
                <div class="oc-card__header"><span class="oc-card__title" style="font-size:.9rem;">Revenue by Article</span></div>
                ${breakdown.length ? `<div style="padding:4px 0;">${breakdown.map((item) => `<div style="padding:12px 20px;border-bottom:1px solid var(--border);"><div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;"><span style="font-size:.82rem;color:var(--navy);font-weight:500;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${esc(item.title)}">${esc(item.title ?? 'Untitled')}</span><span style="font-size:.875rem;font-weight:700;color:var(--navy);">${fmtMoney(item.total)}</span></div><div style="height:3px;background:var(--border);border-radius:2px;overflow:hidden;"><div style="height:100%;width:${Number(item.percent || 0)}%;background:var(--amber);border-radius:2px;transition:width .4s;"></div></div></div>`).join('')}</div>` : '<div style="padding:24px;text-align:center;color:var(--slate);font-size:.85rem;">No revenue yet.</div>'}
            </div>`;
        }

        financeLinksCard(links) {
            return `<div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__body" style="padding:18px 20px;display:flex;flex-direction:column;gap:10px;"><div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">Finance links</div>${links.map((link) => `<a href="${esc(link.href)}" class="${buttonClass(link.variant)}">${esc(link.label)}</a>`).join('')}</div></div>`;
        }

        renderDisputes(el, section, data) {
            this.state.disputeData = data.items ?? [];
            this.state.eligibleDisputeEntries = data.eligible_entries ?? [];

            el.innerHTML = `
                ${this.disputeModalHtml()}
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
                    ${['all','open','resolved','rejected'].map((status, i) => `<button class="filter-pill${i === 0 ? ' filter-pill--active' : ''}" data-dispute-filter="${status}">${cap(status)}</button>`).join('')}
                    ${this.state.eligibleDisputeEntries.length ? '<button data-open-dispute-modal class="oc-btn oc-btn--primary oc-btn--sm" style="margin-left:auto;">Raise a dispute</button>' : ''}
                </div>
                <div class="oc-card"><div class="oc-card__header"><span class="oc-card__title" data-dispute-title>My Disputes</span><span data-dispute-count style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span></div><div data-disputes-empty style="display:none;padding:48px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;margin-bottom:6px;">No disputes</div><div style="font-size:.85rem;">If you believe there's an error in your earnings, you can raise a dispute above.</div></div><div data-disputes-list style="display:none;flex-direction:column;"></div></div>`;

            el.querySelectorAll('[data-dispute-filter]').forEach((button) => {
                button.addEventListener('click', () => {
                    this.state.disputeFilter = button.dataset.disputeFilter;
                    el.querySelectorAll('[data-dispute-filter]').forEach((b) => b.classList.remove('filter-pill--active'));
                    button.classList.add('filter-pill--active');
                    this.paintDisputeRows(el);
                });
            });

            el.querySelector('[data-open-dispute-modal]')?.addEventListener('click', () => this.openDisputeModal(el));
            el.querySelector('[data-dispute-cancel]')?.addEventListener('click', () => this.closeDisputeModal(el));
            el.querySelector('[data-dispute-submit]')?.addEventListener('click', () => this.submitDispute(el));
            el.querySelector('[data-dispute-ledger-select]')?.addEventListener('change', (event) => this.selectLedgerEntry(el, event.target));
            el.querySelector('[data-dispute-modal]')?.addEventListener('click', (event) => {
                if (event.target === event.currentTarget) this.closeDisputeModal(el);
            });

            this.paintDisputeRows(el);
        }

        disputeModalHtml() {
            return `<div data-dispute-modal style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"><div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:500px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);"><h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">Raise an earnings dispute</h3><p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">Tell us what's wrong with this earnings entry. Our team will review it within 2–3 business days.</p><input type="hidden" data-dispute-ledger-id><div class="oc-form-group"><label class="oc-label" for="dispute-ledger-select">Earnings entry</label><select class="oc-select" data-dispute-ledger-select id="dispute-ledger-select"><option value="">Select an earnings entry…</option>${this.state.eligibleDisputeEntries.map((entry) => `<option value="${entry.id}" data-amount="${fmtMoney(entry.amount, entry.currency)}" data-type="${esc(entry.type)}" data-date="${esc(entry.earned_at)}">#${entry.id} · ${esc(entry.type)} · ${fmtMoney(entry.amount, entry.currency)} · ${esc(entry.earned_at)}</option>`).join('')}</select>${this.state.eligibleDisputeEntries.length ? '' : '<div class="oc-help" style="color:var(--amber-dark,#b45309);">No eligible earnings entries found. Entries become disputable once they appear in your earnings ledger.</div>'}</div><div data-selected-entry-summary style="display:none;background:var(--cream-dark);border:1px solid var(--border);border-radius:6px;padding:12px 14px;margin-bottom:16px;font-size:.82rem;"></div><div class="oc-form-group"><label class="oc-label" for="dispute-reason">Reason <span style="color:var(--red);">*</span></label><textarea class="oc-textarea" data-dispute-reason id="dispute-reason" rows="4" style="min-height:100px;" placeholder="Describe the issue clearly — e.g. incorrect amount, missing payment, duplicate entry…" required></textarea><div class="oc-help">Minimum 10 characters. Be specific so we can investigate quickly.</div></div><div data-dispute-errors class="oc-form-errors" style="display:none;margin-bottom:12px;"></div><div style="display:flex;gap:10px;"><button data-dispute-cancel class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button><button data-dispute-submit class="oc-btn oc-btn--primary" style="flex:1;" ${this.state.eligibleDisputeEntries.length ? '' : 'disabled'}>Submit dispute</button></div></div></div>`;
        }

        paintDisputeRows(el) {
            const filtered = this.state.disputeFilter === 'all'
                ? this.state.disputeData
                : this.state.disputeData.filter((d) => d.status === this.state.disputeFilter);

            el.querySelector('[data-dispute-count]').textContent = filtered.length;
            el.querySelector('[data-dispute-title]').textContent = this.state.disputeFilter === 'all' ? 'My Disputes' : `${cap(this.state.disputeFilter)} Disputes`;

            if (!filtered.length) {
                el.querySelector('[data-disputes-empty]').style.display = 'block';
                el.querySelector('[data-disputes-list]').style.display = 'none';
                return;
            }

            const list = el.querySelector('[data-disputes-list]');
            el.querySelector('[data-disputes-empty]').style.display = 'none';
            list.style.display = 'flex';
            list.innerHTML = filtered.map((d, i) => {
                const isLast = i === filtered.length - 1;
                const label = d.status === 'open' ? 'Under review' : cap(d.status);
                const adminNotes = d.admin_notes
                    ? `<div style="font-size:.82rem;color:var(--navy);line-height:1.5;background:${d.status === 'resolved' ? '#f0fdf4' : '#fff9f9'};border:1px solid ${d.status === 'resolved' ? '#bbf7d0' : '#fecaca'};border-radius:6px;padding:10px 14px;margin-top:8px;"><strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:${d.status === 'resolved' ? 'var(--green)' : 'var(--red)'};display:block;margin-bottom:3px;">Admin response</strong>${esc(d.admin_notes)}</div>`
                    : (d.status === 'open' ? '<div style="font-size:.78rem;color:var(--slate);font-style:italic;margin-top:4px;">Our team is reviewing this — usually within 2–3 business days.</div>' : '');

                return `<div style="padding:18px 20px;${!isLast ? 'border-bottom:1px solid var(--border);' : ''}"><div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;"><span class="oc-badge ${statusBadgeClass(d.status)}">${label}</span><span style="font-size:.72rem;color:var(--slate);font-family:monospace;">Ledger #${d.earnings_ledger_id}</span><span style="font-size:.72rem;color:var(--slate-light);">${fmtDate(d.created_at)}</span></div><div style="font-size:.875rem;color:var(--navy);line-height:1.55;margin-bottom:4px;"><strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--slate);display:block;margin-bottom:3px;">Your reason</strong>${esc(d.reason ?? '')}</div>${adminNotes}</div>`;
            }).join('');
        }

        openDisputeModal(el) {
            el.querySelector('[data-dispute-ledger-id]').value = '';
            el.querySelector('[data-dispute-ledger-select]').value = '';
            el.querySelector('[data-dispute-reason]').value = '';
            el.querySelector('[data-selected-entry-summary]').style.display = 'none';
            el.querySelector('[data-dispute-errors]').style.display = 'none';
            el.querySelector('[data-dispute-modal]').style.display = 'grid';
        }

        closeDisputeModal(el) {
            el.querySelector('[data-dispute-modal]').style.display = 'none';
        }

        selectLedgerEntry(el, select) {
            const option = select.options[select.selectedIndex];
            const id = option.value;
            el.querySelector('[data-dispute-ledger-id]').value = id;
            const summary = el.querySelector('[data-selected-entry-summary]');
            if (!id) {
                summary.style.display = 'none';
                return;
            }
            summary.style.display = 'block';
            summary.innerHTML = `<div style="display:flex;gap:20px;flex-wrap:wrap;"><div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Amount</span><br><strong style="color:var(--navy);">${esc(option.dataset.amount || '—')}</strong></div><div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Type</span><br><strong style="color:var(--navy);">${esc(option.dataset.type || '—')}</strong></div><div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Date</span><br><strong style="color:var(--navy);">${esc(option.dataset.date || '—')}</strong></div></div>`;
        }

        async submitDispute(el) {
            const ledgerId = parseInt(el.querySelector('[data-dispute-ledger-id]').value);
            const reason = el.querySelector('[data-dispute-reason]').value.trim();
            const errors = el.querySelector('[data-dispute-errors]');
            const button = el.querySelector('[data-dispute-submit]');
            errors.style.display = 'none';

            if (!ledgerId || ledgerId <= 0) {
                errors.textContent = 'Please select an earnings entry to dispute.';
                errors.style.display = 'block';
                return;
            }

            if (reason.length < 10) {
                errors.textContent = 'Please provide a reason of at least 10 characters.';
                errors.style.display = 'block';
                return;
            }

            button.disabled = true;
            button.innerHTML = '<div class="oc-spinner"></div> Submitting…';

            try {
                const res = await fetch(`/api/${this.site}/open-collab/disputes`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', Authorization: `Bearer ${this.token()}`, Accept: 'application/json'},
                    body: JSON.stringify({earnings_ledger_id: ledgerId, reason}),
                });
                const data = await res.json();

                if (!res.ok) {
                    errors.textContent = data.error || data.message || 'Submission failed.';
                    errors.style.display = 'block';
                    button.disabled = false;
                    button.textContent = 'Submit dispute';
                    return;
                }

                this.closeDisputeModal(el);
                this.toast("✓ Dispute submitted — we'll review it shortly");
                const section = this.sections.find((candidate) => candidate.component === 'disputes_table');
                if (section) this.mount(section);
            } catch {
                errors.textContent = 'Network error. Please try again.';
                errors.style.display = 'block';
                button.disabled = false;
                button.textContent = 'Submit dispute';
            }
        }

        toast(message, ok = true) {
            const el = document.getElementById('status-toast');
            if (!el) return;
            el.textContent = message;
            el.style.background = ok ? 'var(--navy)' : 'var(--red)';
            el.style.opacity = '1';
            setTimeout(() => { el.style.opacity = '0'; }, 2800);
        }
    }

    window.OpenCollabSurfaceRenderer = OpenCollabSurfaceRenderer;
})();
