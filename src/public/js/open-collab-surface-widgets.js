(() => {
    const esc = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const cap = (value) => value ? String(value).charAt(0).toUpperCase() + String(value).slice(1) : '';
    const money = (pence, currency = 'GBP') => `${String(currency).toUpperCase() === 'GBP' ? '£' : '$'}${((Number(pence || 0)) / 100).toFixed(2)}`;
    const date = (value) => value ? new Date(value).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}) : '—';
    const badge = (status) => ({paid:'oc-badge--published',approved:'oc-badge--free',pending:'oc-badge--waiting-approval',rejected:'oc-badge--revoked',resolved:'oc-badge--published',open:'oc-badge--waiting-approval',refunded:'oc-badge--revoked'}[status] ?? 'oc-badge--draft');

    class OpenCollabSurfaceRenderer {
        constructor({surface, site, sections, token, context = {}}) {
            this.surface = surface;
            this.site = site;
            this.sections = Array.isArray(sections) ? sections : [];
            this.token = token;
            this.context = context ?? {};
            this.state = { payoutFilter: 'all', disputeFilter: 'all', payouts: [], disputes: [], eligibleEntries: [], payoutBalance: 0, minPayout: 5000 };
        }

        init() {
            this.sections.forEach((section) => this.loadSection(section));
        }

        async loadSection(section) {
            const el = document.querySelector(`[data-surface-section="${CSS.escape(section.key)}"]`);
            if (!el) return;
            this.skeleton(el, section);

            try {
                const payload = await this.fetchJson(section.endpoint);
                const data = this.normalisePayload(section, payload);
                const renderer = this.renderers()[section.component];

                if (!renderer) {
                    el.innerHTML = this.error(`Renderer not found: ${section.component}`);
                    return;
                }

                renderer.call(this, el, section, data);
            } catch (e) {
                console.error('Surface section failed', section, e);
                el.innerHTML = this.error('Could not load this section.');
            }
        }

        async fetchJson(url, options = {}) {
            const res = await fetch(url, {
                ...options,
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${this.token()}`,
                    ...(options.headers ?? {}),
                },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        }

        normalisePayload(section, payload) {
            const raw = payload?.data ?? payload;

            if (section.component === 'earnings_stats_grid') {
                return {items: [
                    {label: 'Lifetime Earnings', value: raw.total ?? 0, format: 'money', variant: 'accent', sub: 'Gross revenue all time'},
                    {label: 'Available Balance', value: raw.available_to_withdraw ?? raw.available ?? raw.pending ?? 0, format: 'money', variant: 'green', sub: 'Ready to withdraw'},
                    {label: 'Total Paid Out', value: raw.withdrawn ?? 0, format: 'money', sub: 'Received to date'},
                    ...(Number(raw.pending_payouts ?? 0) > 0 ? [{label: 'In Progress', value: raw.pending_payouts, format: 'money', sub: 'Pending or approved'}] : []),
                ]};
            }

            if (section.component === 'payout_stats_grid') {
                return {items: [
                    {label: 'Available to Withdraw', value: raw.available_to_withdraw ?? raw.available ?? raw.pending ?? 0, format: 'money', variant: 'accent', sub: 'Settled minus deductions'},
                    {label: 'Estimated', value: raw.estimated ?? 0, format: 'money', sub: 'Visible, not payable yet'},
                    {label: 'Confirmed', value: raw.confirmed ?? 0, format: 'money', sub: 'Approved, not settled'},
                    {label: 'Withdrawn', value: raw.withdrawn ?? 0, format: 'money', variant: 'green', sub: 'Paid out'},
                    {label: 'Deductions', value: raw.open_liabilities ?? 0, format: 'money', sub: 'Open liabilities'},
                    {label: 'Pending Payouts', value: raw.pending_payouts ?? 0, format: 'money', sub: 'Pending or approved'},
                ]};
            }

            if (section.component === 'dispute_stats_grid') {
                const disputes = Array.isArray(raw) ? raw : [];
                return {items: [
                    {label: 'Open Disputes', value: disputes.filter(d => d.status === 'open').length, format: 'number', variant: 'accent', sub: 'Under review'},
                    {label: 'Resolved', value: disputes.filter(d => d.status === 'resolved').length, format: 'number', variant: 'green', sub: 'Closed in your favour'},
                    {label: 'Rejected', value: disputes.filter(d => d.status === 'rejected').length, format: 'number', sub: 'No action taken'},
                ]};
            }

            if (section.component === 'payout_history_table') {
                return {items: Array.isArray(raw) ? raw : (raw.items ?? [])};
            }

            if (section.component === 'disputes_table') {
                return {
                    items: Array.isArray(raw) ? raw : (raw.items ?? []),
                    eligible_entries: this.context?.disputes?.eligible_entries ?? raw.eligible_entries ?? [],
                };
            }

            if (section.component === 'earnings_finance_table') {
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

        renderers() {
            return {
                earnings_stats_grid: this.statsGrid,
                payout_stats_grid: this.statsGrid,
                dispute_stats_grid: this.statsGrid,
                payout_history_table: this.payoutHistory,
                earnings_finance_table: this.earningsFinance,
                disputes_table: this.disputesTable,
            };
        }

        skeleton(el, section) {
            el.innerHTML = `<div class="oc-card" style="animation:fadeSlideIn .4s ease;"><div class="oc-card__header"><span class="oc-card__title">${esc(section.title)}</span></div><div class="oc-card__body oc-widget__loading"><div class="oc-skeleton-line"></div><div class="oc-skeleton-line oc-skeleton-line--short"></div></div></div>`;
        }

        error(message) {
            return `<div class="oc-card" style="padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">${esc(message)}</div>`;
        }

        statsGrid(el, section, data) {
            el.innerHTML = `<div class="oc-stats" style="animation:fadeSlideIn .4s ease;margin-bottom:24px;">${(data.items ?? []).map(item => `<div class="oc-stat${item.variant ? ` oc-stat--${esc(item.variant)}` : ''}"><div class="oc-stat__label">${esc(item.label)}</div><div class="oc-stat__value">${item.format === 'money' ? money(item.value) : esc(item.value ?? 0)}</div><div class="oc-stat__sub">${esc(item.sub ?? '')}</div></div>`).join('')}</div>`;
        }

        async payoutHistory(el, section, data) {
            this.state.payouts = data.items ?? [];
            this.state.minPayout = 5000;
            try {
                const balancePayload = await this.fetchJson(`/api/${this.site}/open-collab/payouts/balance`);
                const balance = balancePayload?.data ?? balancePayload ?? {};
                this.state.payoutBalance = Number(balance.balance_pence ?? balance.available_to_withdraw ?? 0);
            } catch {
                this.state.payoutBalance = 0;
            }

            el.innerHTML = `<div class="oc-grid-sidebar" style="align-items:start;"><div class="oc-card" style="animation:fadeSlideIn .45s ease;"><div class="oc-card__header"><span class="oc-card__title" data-payout-title>Payout History</span><span data-payout-count style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span></div><div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;">${['all','pending','approved','paid','rejected'].map((s,i)=>`<button class="filter-pill${i===0?' filter-pill--active':''}" data-payout-filter="${s}">${cap(s)}</button>`).join('')}</div><div data-payout-empty style="display:none;padding:48px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;margin-bottom:6px;" data-payout-empty-title>No payout requests yet</div><div style="font-size:.85rem;" data-payout-empty-sub>Once your balance reaches £50.00, you can request a payout.</div></div><div data-payout-table-wrap style="display:none;overflow-x:auto;"><table class="oc-table"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Reference</th><th></th></tr></thead><tbody data-payout-tbody></tbody></table></div></div><div style="position:sticky;top:calc(var(--header-h) + 20px);"><div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__header"><span class="oc-card__title" style="font-size:.95rem;">Request Payout</span></div><div class="oc-card__body"><div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px;text-align:center;"><div style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">Available now</div><div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy);" data-payout-balance>${money(this.state.payoutBalance)}</div></div><div data-payout-errors class="oc-form-errors" style="display:none;margin-bottom:12px;"></div><div data-payout-form-wrap><div class="oc-form-group"><label class="oc-label" for="payout-method">Payout method</label><select class="oc-select" data-payout-method id="payout-method"><option value="bank_transfer">Bank transfer</option><option value="paypal">PayPal</option><option value="other">Other</option></select><div class="oc-help">Your payout details are configured in <a href="/contributor/settings#payment">Settings</a>.</div></div><button class="oc-btn oc-btn--amber oc-btn--block" data-payout-submit>Request ${money(this.state.payoutBalance)}</button></div><div data-payout-minimum-note style="display:none;padding:16px;text-align:center;border:1.5px dashed var(--border);border-radius:var(--radius);"><div style="font-size:.85rem;font-weight:500;margin-bottom:4px;color:var(--navy);">Minimum not reached</div><div style="font-size:.78rem;color:var(--slate);">You need at least <strong>${money(this.state.minPayout)}</strong> to request a payout.</div></div><div style="font-size:.72rem;color:var(--slate);line-height:1.6;padding-top:12px;border-top:1px solid var(--border);margin-top:16px;">Payouts are processed manually by our team, typically within 2–5 business days after approval.</div></div></div></div></div>`;
            el.querySelectorAll('[data-payout-filter]').forEach(button => button.addEventListener('click', () => { this.state.payoutFilter = button.dataset.payoutFilter; el.querySelectorAll('[data-payout-filter]').forEach(b => b.classList.remove('filter-pill--active')); button.classList.add('filter-pill--active'); this.paintPayouts(el); }));
            el.querySelector('[data-payout-submit]').addEventListener('click', () => this.requestPayout(el, section));
            this.paintPayoutRequestState(el);
            this.paintPayouts(el);
        }

        paintPayoutRequestState(el) {
            const canRequest = this.state.payoutBalance >= this.state.minPayout;
            el.querySelector('[data-payout-submit]').disabled = !canRequest;
            el.querySelector('[data-payout-form-wrap]').style.display = canRequest ? 'block' : 'none';
            el.querySelector('[data-payout-minimum-note]').style.display = canRequest ? 'none' : 'block';
        }

        paintPayouts(el) {
            const filtered = this.state.payoutFilter === 'all' ? this.state.payouts : this.state.payouts.filter(p => p.status === this.state.payoutFilter);
            el.querySelector('[data-payout-count]').textContent = filtered.length;
            el.querySelector('[data-payout-title]').textContent = this.state.payoutFilter === 'all' ? 'Payout History' : `${cap(this.state.payoutFilter)} Payouts`;
            el.querySelector('[data-payout-empty]').style.display = filtered.length ? 'none' : 'block';
            el.querySelector('[data-payout-table-wrap]').style.display = filtered.length ? 'block' : 'none';
            if (!filtered.length) return;
            el.querySelector('[data-payout-tbody]').innerHTML = filtered.map(p => `<tr><td style="white-space:nowrap;color:var(--slate);">${date(p.created_at)}</td><td style="font-weight:600;font-family:var(--font-display);font-size:1rem;color:var(--navy);">${money(p.amount_pence ?? p.amount, p.currency)}</td><td style="color:var(--slate);font-size:.85rem;">${esc((p.method ?? '').replace('_',' ').replace(/\b\w/g,l=>l.toUpperCase()))}</td><td><span class="oc-badge ${badge(p.status)}">${cap(p.status)}</span></td><td style="color:var(--slate);font-size:.82rem;font-family:monospace;">${esc(p.reference ?? '—')}</td><td style="text-align:right;">${['paid','approved'].includes(p.status) ? `<a href="/api/${esc(this.site)}/open-collab/payouts/${p.id}/statement" class="oc-btn oc-btn--ghost oc-btn--sm" download title="Download PDF">PDF</a>` : '<span style="font-size:.75rem;color:var(--slate);">—</span>'}</td></tr>${p.rejection_reason ? `<tr><td colspan="6" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Rejection reason:</strong> ${esc(p.rejection_reason)}</td></tr>` : ''}`).join('');
        }

        async requestPayout(el, section) {
            const button = el.querySelector('[data-payout-submit]');
            const errors = el.querySelector('[data-payout-errors]');
            button.disabled = true;
            button.innerHTML = '<div class="oc-spinner"></div> Submitting…';
            errors.style.display = 'none';
            try {
                const res = await fetch(`/api/${this.site}/open-collab/payouts`, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${this.token()}`}, body: JSON.stringify({method: el.querySelector('[data-payout-method]').value})});
                const payload = await res.json();
                if (!res.ok) throw new Error(payload.message || payload.error || 'Request failed. Please try again.');
                this.toast('✓ Payout request submitted. Our team will process it shortly.');
                this.loadSection(section);
            } catch (e) {
                errors.textContent = e.message;
                errors.style.display = 'block';
                button.disabled = false;
                button.textContent = `Request ${money(this.state.payoutBalance)}`;
            }
        }

        earningsFinance(el, section, data) {
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
            if (!rows.length) return `<div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__header"><span class="oc-card__title">Payout History</span><a href="/contributor/payouts" class="oc-btn oc-btn--ghost oc-btn--sm">Request payout</a></div><div style="padding:40px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;margin-bottom:4px;">No payouts yet</div><div style="font-size:.82rem;">Once your balance reaches the minimum threshold, you can <a href="/contributor/payouts" style="color:var(--navy);">request a payout</a>.</div></div></div>`;
            return `<div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__header"><span class="oc-card__title">Payout History</span><a href="/contributor/payouts" class="oc-btn oc-btn--ghost oc-btn--sm">Request payout</a></div><table class="oc-table"><thead><tr><th>Payout ID</th><th>Amount</th><th>Status</th><th>Date</th><th>Download</th></tr></thead><tbody>${rows.map(p => `<tr><td style="font-family:monospace;font-size:.78rem;color:var(--slate);">PAY-${String(p.id).padStart(6,'0')}</td><td style="font-weight:600;color:var(--navy);">${money(p.amount_pence ?? p.amount, p.currency)}</td><td><span class="oc-badge ${badge(p.status)}">${cap(p.status)}</span></td><td style="font-size:.78rem;color:var(--slate);">${date(p.created_at)}</td><td>${['paid','approved'].includes(p.status) ? `<a href="/api/${esc(this.site)}/open-collab/payouts/${p.id}/statement" class="oc-btn oc-btn--ghost oc-btn--sm" download title="Download payout statement PDF">PDF</a>` : '<span style="font-size:.75rem;color:var(--slate);">—</span>'}</td></tr>${p.rejection_reason ? `<tr><td colspan="5" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Declined reason:</strong> ${esc(p.rejection_reason)}</td></tr>` : ''}`).join('')}</tbody></table></div>`;
        }

        breakdownCard(rows) {
            return `<div class="oc-card" style="animation:fadeSlideIn .45s ease;"><div class="oc-card__header"><span class="oc-card__title" style="font-size:.9rem;">Revenue by Article</span></div>${rows.length ? `<div style="padding:4px 0;">${rows.map(item => `<div style="padding:12px 20px;border-bottom:1px solid var(--border);"><div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;"><span style="font-size:.82rem;color:var(--navy);font-weight:500;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${esc(item.title)}">${esc(item.title ?? 'Untitled')}</span><span style="font-size:.875rem;font-weight:700;color:var(--navy);">${money(item.total)}</span></div><div style="height:3px;background:var(--border);border-radius:2px;overflow:hidden;"><div style="height:100%;width:${Number(item.percent || 0)}%;background:var(--amber);border-radius:2px;transition:width .4s;"></div></div></div>`).join('')}</div>` : '<div style="padding:24px;text-align:center;color:var(--slate);font-size:.85rem;">No revenue yet.</div>'}</div>`;
        }

        linksCard(links) {
            return `<div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__body" style="padding:18px 20px;display:flex;flex-direction:column;gap:10px;"><div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">Finance links</div>${links.map(link => `<a href="${esc(link.href)}" class="oc-btn oc-btn--${link.variant === 'amber' ? 'amber' : 'ghost'} oc-btn--block">${esc(link.label)}</a>`).join('')}</div></div>`;
        }

        disputesTable(el, section, data) {
            this.state.disputes = data.items ?? [];
            this.state.eligibleEntries = data.eligible_entries ?? [];
            el.innerHTML = `${this.disputeModal()}<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap;">${['all','open','resolved','rejected'].map((s,i)=>`<button class="filter-pill${i===0?' filter-pill--active':''}" data-dispute-filter="${s}">${cap(s)}</button>`).join('')}${this.state.eligibleEntries.length ? '<button data-open-dispute-modal class="oc-btn oc-btn--primary oc-btn--sm" style="margin-left:auto;">Raise a dispute</button>' : ''}</div><div class="oc-card"><div class="oc-card__header"><span class="oc-card__title" data-dispute-title>My Disputes</span><span data-dispute-count style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span></div><div data-disputes-empty style="display:none;padding:48px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;margin-bottom:6px;">No disputes</div><div style="font-size:.85rem;">If you believe there's an error in your earnings, you can raise a dispute above.</div></div><div data-disputes-list style="display:none;flex-direction:column;"></div></div>`;
            el.querySelectorAll('[data-dispute-filter]').forEach(button => button.addEventListener('click', () => { this.state.disputeFilter = button.dataset.disputeFilter; el.querySelectorAll('[data-dispute-filter]').forEach(b => b.classList.remove('filter-pill--active')); button.classList.add('filter-pill--active'); this.paintDisputes(el); }));
            el.querySelector('[data-open-dispute-modal]')?.addEventListener('click', () => this.openDisputeModal(el));
            el.querySelector('[data-dispute-cancel]')?.addEventListener('click', () => this.closeDisputeModal(el));
            el.querySelector('[data-dispute-submit]')?.addEventListener('click', () => this.submitDispute(el, section));
            el.querySelector('[data-dispute-ledger-select]')?.addEventListener('change', e => this.selectLedgerEntry(el, e.target));
            el.querySelector('[data-dispute-modal]')?.addEventListener('click', e => { if (e.target === e.currentTarget) this.closeDisputeModal(el); });
            this.paintDisputes(el);
        }

        disputeModal() {
            return `<div data-dispute-modal style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"><div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:500px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);"><h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">Raise an earnings dispute</h3><p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">Tell us what's wrong with this earnings entry. Our team will review it within 2–3 business days.</p><input type="hidden" data-dispute-ledger-id><div class="oc-form-group"><label class="oc-label" for="dispute-ledger-select">Earnings entry</label><select class="oc-select" data-dispute-ledger-select id="dispute-ledger-select"><option value="">Select an earnings entry…</option>${this.state.eligibleEntries.map(e => `<option value="${e.id}" data-amount="${money(e.amount, e.currency)}" data-type="${esc(e.type)}" data-date="${esc(e.earned_at)}">#${e.id} · ${esc(e.type)} · ${money(e.amount, e.currency)} · ${esc(e.earned_at)}</option>`).join('')}</select>${this.state.eligibleEntries.length ? '' : '<div class="oc-help" style="color:var(--amber-dark,#b45309);">No eligible earnings entries found. Entries become disputable once they appear in your earnings ledger.</div>'}</div><div data-selected-entry-summary style="display:none;background:var(--cream-dark);border:1px solid var(--border);border-radius:6px;padding:12px 14px;margin-bottom:16px;font-size:.82rem;"></div><div class="oc-form-group"><label class="oc-label" for="dispute-reason">Reason <span style="color:var(--red);">*</span></label><textarea class="oc-textarea" data-dispute-reason id="dispute-reason" rows="4" style="min-height:100px;" placeholder="Describe the issue clearly — e.g. incorrect amount, missing payment, duplicate entry…" required></textarea><div class="oc-help">Minimum 10 characters. Be specific so we can investigate quickly.</div></div><div data-dispute-errors class="oc-form-errors" style="display:none;margin-bottom:12px;"></div><div style="display:flex;gap:10px;"><button data-dispute-cancel class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button><button data-dispute-submit class="oc-btn oc-btn--primary" style="flex:1;" ${this.state.eligibleEntries.length ? '' : 'disabled'}>Submit dispute</button></div></div></div>`;
        }

        paintDisputes(el) {
            const filtered = this.state.disputeFilter === 'all' ? this.state.disputes : this.state.disputes.filter(d => d.status === this.state.disputeFilter);
            el.querySelector('[data-dispute-count]').textContent = filtered.length;
            el.querySelector('[data-dispute-title]').textContent = this.state.disputeFilter === 'all' ? 'My Disputes' : `${cap(this.state.disputeFilter)} Disputes`;
            el.querySelector('[data-disputes-empty]').style.display = filtered.length ? 'none' : 'block';
            el.querySelector('[data-disputes-list]').style.display = filtered.length ? 'flex' : 'none';
            el.querySelector('[data-disputes-list]').innerHTML = filtered.map((d,i) => `<div style="padding:18px 20px;${i < filtered.length - 1 ? 'border-bottom:1px solid var(--border);' : ''}"><div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;"><span class="oc-badge ${badge(d.status)}">${d.status === 'open' ? 'Under review' : cap(d.status)}</span><span style="font-size:.72rem;color:var(--slate);font-family:monospace;">Ledger #${d.earnings_ledger_id}</span><span style="font-size:.72rem;color:var(--slate-light);">${date(d.created_at)}</span></div><div style="font-size:.875rem;color:var(--navy);line-height:1.55;margin-bottom:4px;"><strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--slate);display:block;margin-bottom:3px;">Your reason</strong>${esc(d.reason ?? '')}</div>${d.admin_notes ? `<div style="font-size:.82rem;color:var(--navy);line-height:1.5;background:${d.status === 'resolved' ? '#f0fdf4' : '#fff9f9'};border:1px solid ${d.status === 'resolved' ? '#bbf7d0' : '#fecaca'};border-radius:6px;padding:10px 14px;margin-top:8px;"><strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:${d.status === 'resolved' ? 'var(--green)' : 'var(--red)'};display:block;margin-bottom:3px;">Admin response</strong>${esc(d.admin_notes)}</div>` : (d.status === 'open' ? '<div style="font-size:.78rem;color:var(--slate);font-style:italic;margin-top:4px;">Our team is reviewing this — usually within 2–3 business days.</div>' : '')}</div>`).join('');
        }

        openDisputeModal(el) { el.querySelector('[data-dispute-modal]').style.display = 'grid'; }
        closeDisputeModal(el) { el.querySelector('[data-dispute-modal]').style.display = 'none'; }
        selectLedgerEntry(el, select) {
            const option = select.options[select.selectedIndex];
            el.querySelector('[data-dispute-ledger-id]').value = option.value;
            const summary = el.querySelector('[data-selected-entry-summary]');
            if (!option.value) { summary.style.display = 'none'; return; }
            summary.style.display = 'block';
            summary.innerHTML = `<div style="display:flex;gap:20px;flex-wrap:wrap;"><div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Amount</span><br><strong style="color:var(--navy);">${esc(option.dataset.amount || '—')}</strong></div><div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Type</span><br><strong style="color:var(--navy);">${esc(option.dataset.type || '—')}</strong></div><div><span style="color:var(--slate);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;">Date</span><br><strong style="color:var(--navy);">${esc(option.dataset.date || '—')}</strong></div></div>`;
        }

        async submitDispute(el, section) {
            const ledgerId = parseInt(el.querySelector('[data-dispute-ledger-id]').value);
            const reason = el.querySelector('[data-dispute-reason]').value.trim();
            const errors = el.querySelector('[data-dispute-errors]');
            const button = el.querySelector('[data-dispute-submit]');
            errors.style.display = 'none';
            if (!ledgerId || ledgerId <= 0) { errors.textContent = 'Please select an earnings entry to dispute.'; errors.style.display = 'block'; return; }
            if (reason.length < 10) { errors.textContent = 'Please provide a reason of at least 10 characters.'; errors.style.display = 'block'; return; }
            button.disabled = true;
            button.innerHTML = '<div class="oc-spinner"></div> Submitting…';
            try {
                const res = await fetch(`/api/${this.site}/open-collab/disputes`, {method: 'POST', headers: {'Content-Type': 'application/json', Authorization: `Bearer ${this.token()}`, Accept: 'application/json'}, body: JSON.stringify({earnings_ledger_id: ledgerId, reason})});
                const payload = await res.json();
                if (!res.ok) throw new Error(payload.error || payload.message || 'Submission failed.');
                this.closeDisputeModal(el);
                this.toast("✓ Dispute submitted — we'll review it shortly");
                this.loadSection(section);
            } catch (e) {
                errors.textContent = e.message;
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
