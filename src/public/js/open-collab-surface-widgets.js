(() => {
    // ─── Shared utilities ────────────────────────────────────────────────────────
    const esc    = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const cap    = (value) => value ? String(value).charAt(0).toUpperCase() + String(value).slice(1) : '';
    const money  = (pence, currency = 'GBP') => `${String(currency).toUpperCase() === 'GBP' ? '£' : '$'}${((Number(pence || 0)) / 100).toFixed(2)}`;
    const date   = (value) => value ? new Date(value).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}) : '—';
    const badge  = (status) => ({paid:'oc-badge--published',approved:'oc-badge--free',pending:'oc-badge--waiting-approval',rejected:'oc-badge--revoked',resolved:'oc-badge--published',open:'oc-badge--waiting-approval',refunded:'oc-badge--revoked'}[status] ?? 'oc-badge--draft');

    // ─── Surface Renderer Class ──────────────────────────────────────────────────
    class OpenCollabSurfaceRenderer {
        constructor({surface, site, sections, token, context = {}}) {
            this.surface = surface;
            this.site = site;
            this.sections = Array.isArray(sections) ? sections : [];
            this.token = token;
            this.context = context ?? {};
            this.state = {
                payoutFilter: 'all', disputeFilter: 'all', payouts: [], disputes: [], eligibleEntries: [], payoutBalance: 0, minPayout: 5000,
                adminDisputes: [], adminDisputeFilter: 'all', adminDisputeQuery: '', pendingResolveId: null, pendingRejectId: null,
                adminPayouts: [], adminPayoutFilter: 'all', adminPayoutQuery: '', pendingDeclineId: null, pendingPaidId: null
            };
            this.requestCache = new Map();
            this.debounceTimer = null;
        }

        init() {
            this.sections.forEach((section) => this.loadSection(section));
        }

        async loadSection(section, bypassCache = false) {
            const el = document.querySelector(`[data-surface-section="${CSS.escape(section.key)}"]`);
            if (!el) return;

            if (bypassCache) {
                this.requestCache.delete(section.endpoint);
            }

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
            const method = String(options.method ?? 'GET').toUpperCase();
            const cacheKey = method === 'GET' && !options.body ? url : null;

            if (cacheKey && this.requestCache.has(cacheKey)) {
                return this.requestCache.get(cacheKey);
            }

            const request = fetch(url, {
                ...options,
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${this.token()}`,
                    ...(options.headers ?? {}),
                },
            }).then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            });

            if (cacheKey) this.requestCache.set(cacheKey, request);

            try {
                return await request;
            } catch (e) {
                if (cacheKey) this.requestCache.delete(cacheKey);
                throw e;
            }
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

            if (section.component === 'admin_dispute_summary_stats') {
                const disputes = Array.isArray(raw) ? raw : (raw.items ?? []);
                return {items: [
                        {label: 'Open', value: disputes.filter(d => d.status === 'open').length, format: 'number', variant: 'accent', sub: 'Awaiting review'},
                        {label: 'Resolved', value: disputes.filter(d => d.status === 'resolved').length, format: 'number', variant: 'green', sub: 'Closed in favour'},
                        {label: 'Rejected', value: disputes.filter(d => d.status === 'rejected').length, format: 'number', sub: 'No action taken'},
                    ]};
            }

            if (section.component === 'admin_payout_summary_stats') {
                return {items: [
                        {label: 'Available', value: raw.available_to_withdraw ?? 0, format: 'money', variant: 'accent', sub: 'Settled, ready to pay'},
                        {label: 'Estimated', value: raw.estimated_balance ?? 0, format: 'money', sub: 'Visible, not payable yet'},
                        {label: 'Confirmed', value: raw.confirmed_balance ?? 0, format: 'money', sub: 'Approved, not settled'},
                        {label: 'Withdrawn', value: raw.withdrawn_balance ?? 0, format: 'money', variant: 'green', sub: 'Paid out'},
                        {label: 'Pending', value: raw.in_flight_payouts ?? 0, format: 'money', sub: 'Pending or approved payouts'},
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

            if (section.component === 'admin_dispute_stats_grid') {
                return { items: Array.isArray(raw) ? raw : (raw.items ?? []) };
            }

            if (section.component === 'admin_payout_stats_grid') {
                return { items: Array.isArray(raw) ? raw : (raw.items ?? []) };
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
                admin_dispute_stats_grid: this.adminDisputesTable,
                admin_payout_stats_grid: this.adminPayoutsTable,
                admin_dispute_summary_stats: this.statsGrid,
                admin_payout_summary_stats: this.statsGrid,
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

            el.innerHTML = `<div class="oc-grid-sidebar" style="align-items:start;"><div class="oc-card" style="animation:fadeSlideIn .45s ease;"><div class="oc-card__header"><span class="oc-card__title" data-payout-title>Payout History</span><span data-payout-count style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span></div><div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;">${['all','pending','approved','paid','rejected'].map((s,i)=>`<button class="filter-pill${i===0?' filter-pill--active':''}" data-payout-filter="${s}">${cap(s)}</button>`).join('')}</div><div data-payout-empty style="display:none;padding:48px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;margin-bottom:6px;" data-payout-empty-title>No payout requests yet</div><div style="font-size:.85rem;" data-payout-empty-sub>Once your balance reaches £50.00, you can request a payout.</div></div><div data-payout-table-wrap style="display:none;overflow-x:auto;"><table class="oc-table"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Reference</th><th></th></tr></thead><tbody data-payout-tbody></tbody></table></div></div><div style="position:sticky;top:calc(var(--header-h) + 20px);"><div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__header"><span class="oc-card__title" style="font-size:.95rem;">Request Payout</span></div><div class="oc-card__body"><div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px;text-align:center;"><div style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">Available now</div><div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy);" data-payout-balance>${money(this.state.payoutBalance)}</div></div><div data-payout-errors class="oc-form-errors" style="display:none;margin-bottom:12px;"></div><div data-payout-form-wrap><div class="oc-form-group"><label class="oc-label" for="payout-method">Payout method</label><select class="oc-select" data-payout-method id="payout-method"><option value="stripe">Bank transfer</option><option value="paypal">PayPal</option><option value="other">Other</option></select><div class="oc-help">Your payout details are configured in <a href="/contributor/settings#payment">Settings</a>.</div></div><button class="oc-btn oc-btn--amber oc-btn--block" data-payout-submit>Request ${money(this.state.payoutBalance)}</button></div><div data-payout-minimum-note style="display:none;padding:16px;text-align:center;border:1.5px dashed var(--border);border-radius:var(--radius);"><div style="font-size:.85rem;font-weight:500;margin-bottom:4px;color:var(--navy);">Minimum not reached</div><div style="font-size:.78rem;color:var(--slate);">You need at least <strong>${money(this.state.minPayout)}</strong> to request a payout.</div></div><div style="font-size:.72rem;color:var(--slate);line-height:1.6;padding-top:12px;border-top:1px solid var(--border);margin-top:16px;">Payouts are processed manually by our team, typically within 2–5 business days after approval.</div></div></div></div></div>`;
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

        // ─── Admin Disputes Panel Layout Renderer ──────────────────────────────────
        adminDisputesTable(el, section, data) {
            this.state.adminDisputes = data.items ?? [];
            el.innerHTML = `
                <div id="status-toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;z-index:300;pointer-events:none;"></div>
                <div id="resolve-modal" style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"><div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:500px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);"><h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">Resolve dispute</h3><p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">Add notes and optionally apply a ledger adjustment (positive = credit, negative = debit).</p><input type="hidden" id="resolve-dispute-id"><div class="oc-form-group"><label class="oc-label" for="resolve-notes">Admin notes <span style="color:var(--red);">*</span></label><textarea class="oc-textarea" id="resolve-notes" rows="3" style="min-height:80px;" placeholder="Explain your resolution decision…" required></textarea></div><div class="oc-form-group"><label class="oc-label oc-label--optional" for="resolve-adjustment">Adjustment amount (£)<span style="font-size:.72rem;color:var(--slate);font-weight:400;margin-left:4px;">— positive = credit, negative = debit</span></label><input class="oc-input" type="number" id="resolve-adjustment" step="0.01" placeholder="e.g. 5.00 or -2.50 — leave blank for no adjustment"></div><div class="oc-form-group" id="resolve-reason-group" style="display:none;"><label class="oc-label" for="resolve-adjustment-reason">Adjustment reason</label><input class="oc-input" type="text" id="resolve-adjustment-reason" placeholder="Reason for the ledger adjustment…"></div><div id="resolve-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div><div style="display:flex;gap:10px;"><button data-close-resolve class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button><button id="resolve-confirm-btn" class="oc-btn oc-btn--primary" style="flex:1;">Resolve dispute</button></div></div></div>
                <div id="reject-modal" style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"><div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:440px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);"><h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">Reject dispute</h3><p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">Explain why this dispute is being rejected.</p><input type="hidden" id="reject-dispute-id"><div class="oc-form-group"><label class="oc-label" for="reject-notes">Admin notes <span style="color:var(--red);">*</span></label><textarea class="oc-textarea" id="reject-notes" rows="3" style="min-height:80px;" placeholder="Explain why this dispute has been rejected…" required></textarea></div><div id="reject-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div><div style="display:flex;gap:10px;"><button data-close-reject class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button><button id="reject-confirm-btn" class="oc-btn oc-btn--danger" style="flex:1;">Reject dispute</button></div></div></div>
                <div class="oc-card" style="margin-bottom:20px;padding:16px 20px;"><div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;"><div style="position:relative;flex:1;min-width:200px;"><svg viewBox="0 0 20 20" fill="currentColor" width="16" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--slate-light);pointer-events:none;"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg><input class="oc-input" type="text" id="search-input" placeholder="Search by user ID or ledger ID…" style="padding-left:38px;" autocomplete="off"></div><div style="display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap;"><button class="filter-pill filter-pill--active" data-status="all">All</button><button class="filter-pill" data-status="open">Open</button><button class="filter-pill" data-status="resolved">Resolved</button><button class="filter-pill" data-status="rejected">Rejected</button></div></div></div>
                <div class="oc-card"><div class="oc-card__header"><span class="oc-card__title" id="results-title">All Disputes</span><span id="results-count" style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span></div><div id="disputes-empty" style="display:none;padding:48px 24px;text-align:center;color:var(--slate);"><svg viewBox="0 0 20 20" fill="currentColor" width="32" style="opacity:.2;display:block;margin:0 auto 12px;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg><div style="font-weight:500;margin-bottom:6px;" id="empty-message">No disputes found</div><div style="font-size:.85rem;" id="empty-sub">All earnings disputes will appear here.</div></div><div id="disputes-list" style="display:none;flex-direction:column;"></div></div>
            `;
            const searchInput = el.querySelector('#search-input');
            searchInput.value = this.state.adminDisputeQuery;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    this.state.adminDisputeQuery = e.target.value.trim().toLowerCase();
                    this.paintAdminDisputes(el, section);
                }, 300);
            });
            el.querySelector('#resolve-adjustment')?.addEventListener('input', function() {
                el.querySelector('#resolve-reason-group').style.display = this.value ? 'block' : 'none';
            });
            el.querySelectorAll('.filter-pill').forEach(btn => {
                if (btn.dataset.status === this.state.adminDisputeFilter) {
                    el.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
                    btn.classList.add('filter-pill--active');
                }
                btn.addEventListener('click', () => {
                    this.state.adminDisputeFilter = btn.dataset.status;
                    el.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
                    btn.classList.add('filter-pill--active');
                    this.paintAdminDisputes(el, section);
                });
            });
            el.querySelector('[data-close-resolve]').addEventListener('click', () => this.closeAdminModal(el, 'resolve'));
            el.querySelector('[data-close-reject]').addEventListener('click', () => this.closeAdminModal(el, 'reject'));
            el.querySelector('#resolve-confirm-btn').addEventListener('click', () => this.submitAdminResolve(el, section));
            el.querySelector('#reject-confirm-btn').addEventListener('click', () => this.submitAdminReject(el, section));
            this.paintAdminDisputes(el, section);
        }

        paintAdminDisputes(el, section) {
            let filtered = this.state.adminDisputes;
            if (this.state.adminDisputeFilter !== 'all') filtered = filtered.filter(d => d.status === this.state.adminDisputeFilter);
            if (this.state.adminDisputeQuery) {
                filtered = filtered.filter(d => String(d.user_id).includes(this.state.adminDisputeQuery) || String(d.earnings_ledger_id).includes(this.state.adminDisputeQuery) || (d.reason ?? '').toLowerCase().includes(this.state.adminDisputeQuery));
            }
            el.querySelector('#results-count').textContent = filtered.length;
            el.querySelector('#results-title').textContent = this.state.adminDisputeFilter === 'all' ? 'All Disputes' : `${cap(this.state.adminDisputeFilter)} Disputes`;
            const emptyBox = el.querySelector('#disputes-empty');
            const listBox = el.querySelector('#disputes-list');
            if (!filtered.length) {
                listBox.style.display = 'none'; emptyBox.style.display = 'block';
                el.querySelector('#empty-message').textContent = this.state.adminDisputeQuery ? `No disputes matching "${this.state.adminDisputeQuery}"` : 'No disputes found';
                return;
            }
            emptyBox.style.display = 'none'; listBox.style.display = 'flex';
            listBox.innerHTML = filtered.map((d, i) => {
                const isLast = i === filtered.length - 1;
                const statusBadge = { open: 'oc-badge--waiting-approval', resolved: 'oc-badge--published', rejected: 'oc-badge--revoked' }[d.status] ?? 'oc-badge--draft';
                const actionsHtml = d.status === 'open' ? `<button data-btn-resolve="${d.id}" class="oc-btn oc-btn--primary oc-btn--sm">Resolve</button><button data-btn-reject="${d.id}" class="oc-btn oc-btn--ghost oc-btn--sm" style="border-color:#fecaca;color:var(--red);margin-top:4px;">Reject</button>` : `<span class="oc-badge ${statusBadge}" style="font-size:.65rem;">${cap(d.status)}</span>`;
                const adminNotesHtml = d.admin_notes ? `<div style="margin-top:8px;padding:8px 12px;font-size:.78rem;background:${d.status === 'resolved' ? '#f0fdf4' : '#fff9f9'};border:1px solid ${d.status === 'resolved' ? '#bbf7d0' : '#fecaca'};border-radius:6px;color:var(--navy);"><strong>Admin response:</strong> ${esc(d.admin_notes)}</div>` : '';
                return `<div style="padding:18px 20px;${!isLast ? 'border-bottom:1px solid var(--border);' : ''}"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;"><div style="flex:1;min-width:0;"><div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;"><a href="/${esc(this.site)}/open-collab/admin/contributors/${d.user_id}" style="font-weight:600;color:var(--navy);text-decoration:none;">User #${d.user_id}</a><span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);border-radius:10px;padding:2px 8px;font-family:monospace;">Ledger #${d.earnings_ledger_id}</span></div><div style="font-size:.875rem;color:var(--navy);line-height:1.55;background:var(--cream-dark);border:1px solid var(--border);border-radius:6px;padding:10px 14px;">${esc(d.reason ?? '')}</div>${adminNotesHtml}</div><div>${actionsHtml}</div></div></div>`;
            }).join('');
            listBox.querySelectorAll('[data-btn-resolve]').forEach(btn => btn.addEventListener('click', () => this.openAdminModal(el, 'resolve', btn.dataset.btnResolve)));
            listBox.querySelectorAll('[data-btn-reject]').forEach(btn => btn.addEventListener('click', () => this.openAdminModal(el, 'reject', btn.dataset.btnReject)));
        }

        // ─── Admin Payouts Panel Layout Renderer ───────────────────────────────────
        adminPayoutsTable(el, section, data) {
            this.state.adminPayouts = data.items ?? [];
            el.innerHTML = `
                <div id="status-toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;z-index:300;pointer-events:none;"></div>
                <div id="decline-modal" style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"><div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:460px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);"><h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">Decline payout</h3><p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">Provide a reason — this will be visible to the contributor.</p><input type="hidden" id="decline-payout-id"><div class="oc-form-group"><label class="oc-label" for="decline-reason">Reason <span style="color:var(--red);">*</span></label><textarea class="oc-textarea" id="decline-reason" rows="3" style="min-height:80px;" placeholder="e.g. Missing bank details, incorrect payment method…" required></textarea></div><div id="decline-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div><div style="display:flex;gap:10px;"><button data-close-decline class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button><button id="decline-confirm-btn" class="oc-btn oc-btn--danger" style="flex:1;">Decline payout</button></div></div></div>
                <div id="paid-modal" style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"><div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:460px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);"><h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">Mark as paid</h3><p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">Record the payment reference for the audit trail.</p><input type="hidden" id="paid-payout-id"><div class="oc-form-group"><label class="oc-label oc-label--optional" for="paid-reference">Payment reference</label><input class="oc-input" type="text" id="paid-reference" placeholder="e.g. BACS ref, transaction ID…"></div><div class="oc-form-group"><label class="oc-label oc-label--optional" for="paid-notes">Notes</label><textarea class="oc-textarea" id="paid-notes" rows="2" style="min-height:60px;" placeholder="Any internal notes…"></textarea></div><div id="paid-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div><div style="display:flex;gap:10px;"><button data-close-paid class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button><button id="paid-confirm-btn" class="oc-btn oc-btn--primary" style="flex:1;">Confirm paid</button></div></div></div>
                <div class="oc-stats" style="margin-bottom:24px;">
                    <div class="oc-stat oc-stat--accent"><div class="oc-stat__label">Pending Review</div><div class="oc-stat__value" id="stat-pending">—</div><div class="oc-stat__sub">Awaiting approval</div></div>
                    <div class="oc-stat"><div class="oc-stat__label">Pending Amount</div><div class="oc-stat__value" id="stat-pending-amount">—</div><div class="oc-stat__sub">Total in queue</div></div>
                    <div class="oc-stat"><div class="oc-stat__label">Total Payouts</div><div class="oc-stat__value" id="stat-total">—</div><div class="oc-stat__sub">All time</div></div>
                </div>
                <div class="oc-card" style="margin-bottom:20px;padding:16px 20px;"><div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;"><div style="position:relative;flex:1;min-width:200px;"><svg viewBox="0 0 20 20" fill="currentColor" width="16" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--slate-light);pointer-events:none;"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg><input class="oc-input" type="text" id="search-input" placeholder="Search by contributor ID or reference…" style="padding-left:38px;" autocomplete="off"></div><div style="display:flex;gap:6px;flex-wrap:wrap;"><button class="filter-pill filter-pill--active" data-filter="all">All</button><button class="filter-pill" data-filter="pending">Pending</button><button class="filter-pill" data-filter="approved">Approved</button><button class="filter-pill" data-filter="paid">Paid</button><button class="filter-pill" data-filter="rejected">Rejected</button></div><a href="/${esc(this.site)}/open-collab/admin/payouts/scheduled" class="oc-btn oc-btn--ghost oc-btn--sm" style="flex-shrink:0;"><svg viewBox="0 0 20 20" fill="currentColor" width="13" style="margin-right:4px;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg> View schedule</a></div></div>
                <div id="pending-card" style="display:none;margin-bottom:24px;border-left:3px solid var(--amber);" class="oc-card"><div class="oc-card__header"><span class="oc-card__title">Pending Approval</span><span id="pending-card-count" style="font-size:.72rem;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-weight:600;">0 pending</span></div><div style="overflow-x:auto;"><table class="oc-table"><thead><tr><th>ID</th><th>Contributor</th><th>Amount</th><th>Currency</th><th>Method</th><th>Requested</th><th>Actions</th></tr></thead><tbody id="pending-tbody"></tbody></table></div></div>
                <div class="oc-card"><div class="oc-card__header"><span class="oc-card__title" id="results-title">All Payouts</span><span id="results-count" style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span></div><div id="payouts-empty" style="display:none;padding:48px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;" id="empty-message">No payouts yet</div><div style="font-size:.85rem;margin-top:4px;" id="empty-sub"></div></div><div id="payouts-table-wrap" style="display:none;overflow-x:auto;"><table class="oc-table"><thead><tr><th>ID</th><th>Contributor</th><th>Amount</th><th>Currency</th><th>Status</th><th>Finance Summary</th><th>Created</th><th>Actions</th></tr></thead><tbody id="payouts-tbody"></tbody></table></div></div>
                <div class="oc-card" style="margin-top:24px;"><div class="oc-card__header"><span class="oc-card__title">Recent Stripe Webhook Events</span></div><div id="stripe-events-loading" style="padding:16px;color:var(--slate);">Loading events…</div><div id="stripe-events-empty" style="display:none;padding:16px;color:var(--slate);">No Stripe webhook events yet.</div><div id="stripe-events-wrap" style="display:none;overflow-x:auto;"><table class="oc-table"><thead><tr><th>Event ID</th><th>Type</th><th>Processed</th><th>Failed</th><th>Error</th></tr></thead><tbody id="stripe-events-tbody"></tbody></table></div></div>
            `;
            const searchInput = el.querySelector('#search-input');
            searchInput.value = this.state.adminPayoutQuery;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    this.state.adminPayoutQuery = e.target.value.trim().toLowerCase();
                    this.paintAdminPayouts(el, section);
                }, 300);
            });
            el.querySelectorAll('.filter-pill').forEach(btn => {
                if (btn.dataset.filter === this.state.adminPayoutFilter) {
                    el.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
                    btn.classList.add('filter-pill--active');
                }
                btn.addEventListener('click', () => {
                    this.state.adminPayoutFilter = btn.dataset.filter;
                    el.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
                    btn.classList.add('filter-pill--active');
                    this.paintAdminPayouts(el, section);
                });
            });
            el.querySelector('[data-close-decline]').addEventListener('click', () => this.closeAdminModal(el, 'decline'));
            el.querySelector('[data-close-paid]').addEventListener('click', () => this.closeAdminModal(el, 'paid'));
            el.querySelector('#decline-confirm-btn').addEventListener('click', () => this.submitAdminDecline(el, section));
            el.querySelector('#paid-confirm-btn').addEventListener('click', () => this.submitAdminMarkPaid(el, section));
            this.paintAdminPayouts(el, section);
            this.loadAdminWebhookEvents(el);
        }

        paintAdminPayouts(el, section) {
            const pendingItems = this.state.adminPayouts.filter(p => p.status === 'pending');
            const pendingTotal = pendingItems.reduce((s, p) => s + (p.amount_pence ?? p.amount ?? 0), 0);
            el.querySelector('#stat-pending').textContent = pendingItems.length;
            el.querySelector('#stat-pending-amount').textContent = money(pendingTotal);
            el.querySelector('#stat-total').textContent = this.state.adminPayouts.length;

            let filtered = this.state.adminPayoutFilter !== 'all' ? this.state.adminPayouts.filter(p => p.status === this.state.adminPayoutFilter) : [...this.state.adminPayouts];
            if (this.state.adminPayoutQuery) {
                filtered = filtered.filter(p => String(p.user_id).includes(this.state.adminPayoutQuery) || (p.reference ?? '').toLowerCase().includes(this.state.adminPayoutQuery));
            }
            el.querySelector('#results-count').textContent = filtered.length;
            el.querySelector('#results-title').textContent = this.state.adminPayoutFilter === 'all' ? 'All Payouts' : `${cap(this.state.adminPayoutFilter)} Payouts`;

            const pendingCard = el.querySelector('#pending-card');
            if (pendingItems.length > 0 && ['all', 'pending'].includes(this.state.adminPayoutFilter)) {
                pendingCard.style.display = 'block';
                el.querySelector('#pending-card-count').textContent = `${pendingItems.length} pending`;
                el.querySelector('#pending-tbody').innerHTML = pendingItems.map(p => `<tr><td>PAY-${String(p.id).padStart(6, '0')}</td><td><a href="/admin/contributors/${p.user_id}" style="font-weight:500;color:var(--navy);text-decoration:none;">User #${p.user_id}</a></td><td style="font-weight:600;color:var(--navy);">${money(p.amount_pence ?? p.amount, p.currency)}</td><td>${esc(p.currency)}</td><td>${esc((p.method ?? '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()))}</td><td>${date(p.created_at)}</td><td><div style="display:flex;gap:6px;flex-wrap:wrap;"><button data-approve="${p.id}" class="oc-btn oc-btn--primary oc-btn--sm">Approve</button><button data-decline="${p.id}" class="oc-btn oc-btn--ghost oc-btn--sm" style="border-color:#fecaca;color:var(--red);">Decline</button></div></td></tr>`).join('');
            } else {
                pendingCard.style.display = 'none';
            }

            const emptyBox = el.querySelector('#payouts-empty');
            const tableWrap = el.querySelector('#payouts-table-wrap');
            if (!filtered.length) {
                tableWrap.style.display = 'none'; emptyBox.style.display = 'block';
                el.querySelector('#empty-message').textContent = this.state.adminPayoutQuery ? `No payouts matching "${this.state.adminPayoutQuery}"` : 'No payouts yet';
                return;
            }
            emptyBox.style.display = 'none'; tableWrap.style.display = 'block';
            el.querySelector('#payouts-tbody').innerHTML = filtered.map(p => {
                const status = p.status ?? 'pending';
                const statusCls = { paid: 'oc-badge--published', approved: 'oc-badge--free', pending: 'oc-badge--waiting-approval', rejected: 'oc-badge--revoked' }[status] ?? 'oc-badge--draft';
                const pdfBtn = ['paid', 'approved'].includes(status) ? `<a href="/api/${esc(this.site)}/open-collab/admin/payouts/${p.id}/statement" class="oc-btn oc-btn--ghost oc-btn--sm" download>PDF</a>` : '';
                const markPaidBtn = (status === 'approved' && p.method !== 'stripe') ? `<button data-paid="${p.id}" class="oc-btn oc-btn--primary oc-btn--sm">Mark paid</button>` : '';
                const retryBtn = (status === 'failed' && p.method === 'stripe') ? `<button data-retry="${p.id}" class="oc-btn oc-btn--ghost oc-btn--sm">Retry</button>` : '';
                const rejectionRow = p.rejection_reason ? `<tr><td colspan="8" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Decline reason:</strong> ${esc(p.rejection_reason)}</td></tr>` : '';

                // Fixed: Stripe internal API transfer/payout status tracking data rows re-integrated inside standard render loop maps
                const failureReason = p.provider_response_json?.error || p.provider_response_json?.reason || '';
                const failureRow = failureReason ? `<tr><td colspan="8" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Stripe failure:</strong> ${esc(failureReason)}</td></tr>` : '';

                return `<tr><td style="font-family:monospace;font-size:.78rem;color:var(--slate);">PAY-${String(p.id).padStart(6, '0')}</td><td><a href="/admin/contributors/${p.user_id}" style="font-weight:500;color:var(--navy);text-decoration:none;">User #${p.user_id}</a></td><td style="font-weight:600;">${money(p.amount_pence ?? p.amount, p.currency)}</td><td>${esc(p.currency)}</td><td><span class="oc-badge ${statusCls}">${cap(status)}</span></td><td>${this.getAdminPayoutFinanceSummary(p)}</td><td>${date(p.created_at)}</td><td><div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">${retryBtn}${markPaidBtn}${pdfBtn}</div></td></tr>${rejectionRow}${failureRow}`;
            }).join('');

            el.querySelectorAll('[data-approve]').forEach(btn => btn.addEventListener('click', () => this.submitAdminPayoutApprove(el, section, btn.dataset.approve, btn)));
            el.querySelectorAll('[data-decline]').forEach(btn => btn.addEventListener('click', () => this.openAdminModal(el, 'decline', btn.dataset.decline)));
            el.querySelectorAll('[data-paid]').forEach(btn => btn.addEventListener('click', () => this.openAdminModal(el, 'paid', btn.dataset.paid)));
            el.querySelectorAll('[data-retry]').forEach(btn => btn.addEventListener('click', () => this.submitAdminPayoutRetry(el, section, btn.dataset.retry, btn)));
        }

        getAdminPayoutFinanceSummary(p) {
            const batch = p.batch_id ? `Batch #${esc(p.batch_id)}` : 'Manual / no batch';
            const ledgerCount = Number(p.ledger_entry_count || 0);
            const ledger = ledgerCount > 0 ? `${ledgerCount} ledger ${ledgerCount === 1 ? 'entry' : 'entries'}` : 'No ledger link';
            const deductions = Number(p.deductions_total_pence || 0);
            const deductionsText = deductions > 0 ? `Deductions ${money(deductions, p.currency)}` : 'No deductions';
            const providerRef = p.provider_transfer_id || p.provider_payout_id || p.provider_status || p.provider || null;
            const provider = providerRef ? `Provider ${esc(providerRef)}` : 'No provider ref';
            const key = p.idempotency_key ? esc(p.idempotency_key) : 'No idempotency key';
            return `<div style="display:flex;flex-direction:column;gap:3px;font-size:.74rem;color:var(--slate);line-height:1.35;min-width:190px;"><div><strong style="color:var(--navy);">${batch}</strong></div><div>${ledger}</div><div style="color:${deductions > 0 ? 'var(--red)' : 'var(--slate)'};">${deductionsText}</div><div>${provider}</div><div style="font-family:monospace;font-size:.68rem;max-width:220px;white-space:normal;word-break:break-all;">${key}</div></div>`;
        }

        // ─── Modal Actions Central Manager ─────────────────────────────────────────
        openAdminModal(el, type, id) {
            if (type === 'resolve') {
                this.state.pendingResolveId = id;
                el.querySelector('#resolve-notes').value = ''; el.querySelector('#resolve-adjustment').value = ''; el.querySelector('#resolve-adjustment-reason').value = '';
                el.querySelector('#resolve-reason-group').style.display = 'none'; el.querySelector('#resolve-errors').style.display = 'none';
                el.querySelector('#resolve-modal').style.display = 'grid'; el.querySelector('#resolve-notes').focus();
            } else if (type === 'reject') {
                this.state.pendingRejectId = id;
                el.querySelector('#reject-notes').value = ''; el.querySelector('#reject-errors').style.display = 'none';
                el.querySelector('#reject-modal').style.display = 'grid'; el.querySelector('#reject-notes').focus();
            } else if (type === 'decline') {
                this.state.pendingDeclineId = id;
                el.querySelector('#decline-reason').value = ''; el.querySelector('#decline-errors').style.display = 'none';
                el.querySelector('#decline-modal').style.display = 'grid'; el.querySelector('#decline-reason').focus();
            } else if (type === 'paid') {
                this.state.pendingPaidId = id;
                el.querySelector('#paid-reference').value = ''; el.querySelector('#paid-notes').value = ''; el.querySelector('#paid-errors').style.display = 'none';
                el.querySelector('#paid-modal').style.display = 'grid'; el.querySelector('#paid-reference').focus();
            }
        }

        closeAdminModal(el, type) {
            if (type === 'resolve') { this.state.pendingResolveId = null; el.querySelector('#resolve-modal').style.display = 'none'; }
            else if (type === 'reject') { this.state.pendingRejectId = null; el.querySelector('#reject-modal').style.display = 'none'; }
            else if (type === 'decline') { this.state.pendingDeclineId = null; el.querySelector('#decline-modal').style.display = 'none'; }
            else if (type === 'paid') { this.state.pendingPaidId = null; el.querySelector('#paid-modal').style.display = 'none'; }
        }

        // ─── Admin API Mutating Operations ─────────────────────────────────────────
        async submitAdminResolve(el, section) {
            const id = this.state.pendingResolveId;
            const notes = el.querySelector('#resolve-notes').value.trim();
            const adjRaw = el.querySelector('#resolve-adjustment').value.trim();
            const adjReason = el.querySelector('#resolve-adjustment-reason').value.trim();
            const errBox = el.querySelector('#resolve-errors');
            const btn = el.querySelector('#resolve-confirm-btn');
            errBox.style.display = 'none';
            if (!notes) { errBox.textContent = 'Admin notes are required.'; errBox.style.display = 'block'; return; }
            let adjustmentAmount = null;
            if (adjRaw !== '') {
                const parsed = parseFloat(adjRaw);
                if (isNaN(parsed)) { errBox.textContent = 'Adjustment amount must be a valid number.'; errBox.style.display = 'block'; return; }
                adjustmentAmount = Math.round(parsed * 100);
            }
            if (adjustmentAmount !== null && !adjReason) { errBox.textContent = 'An adjustment reason is required when applying an adjustment.'; errBox.style.display = 'block'; return; }
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div> Resolving…';
            const payload = { admin_notes: notes };
            if (adjustmentAmount !== null) { payload.adjustment_amount = adjustmentAmount; payload.adjustment_reason = adjReason; }
            try {
                const res = await fetch(`/api/${this.site}/open-collab/admin/disputes/${id}/resolve`, { method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${this.token()}`, Accept: 'application/json' }, body: JSON.stringify(payload) });
                if (!res.ok) { const data = await res.json(); throw new Error(data.error || data.message || 'Resolve failed.'); }
                this.closeAdminModal(el, 'resolve'); this.toast('✓ Dispute resolved'); this.loadSection(section, true);
            } catch (e) { errBox.textContent = e.message; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = 'Resolve dispute'; }
        }

        async submitAdminReject(el, section) {
            const id = this.state.pendingRejectId;
            const notes = el.querySelector('#reject-notes').value.trim();
            const errBox = el.querySelector('#reject-errors');
            const btn = el.querySelector('#reject-confirm-btn');
            errBox.style.display = 'none';
            if (!notes) { errBox.textContent = 'Admin notes are required.'; errBox.style.display = 'block'; return; }
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div> Rejecting…';
            try {
                const res = await fetch(`/api/${this.site}/open-collab/admin/disputes/${id}/reject`, { method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${this.token()}`, Accept: 'application/json' }, body: JSON.stringify({ admin_notes: notes }) });
                if (!res.ok) { const data = await res.json(); throw new Error(data.error || data.message || 'Reject failed.'); }
                this.closeAdminModal(el, 'reject'); this.toast('Dispute rejected'); this.loadSection(section, true);
            } catch (e) { errBox.textContent = e.message; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = 'Reject dispute'; }
        }

        async submitAdminPayoutApprove(el, section, id, btn) {
            if (!confirm('Approve this payout request?')) return;
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div>';
            try {
                await this.fetchJson(`/api/${this.site}/open-collab/admin/payouts/${id}/approve`, { method: 'POST' });
                this.toast('✓ Payout approved'); this.loadSection(section, true);
            } catch (e) { this.toast(e.message || 'Approval failed', false); btn.disabled = false; btn.textContent = 'Approve'; }
        }

        async submitAdminDecline(el, section) {
            const id = this.state.pendingDeclineId;
            const reason = el.querySelector('#decline-reason').value.trim();
            const errBox = el.querySelector('#decline-errors');
            const btn = el.querySelector('#decline-confirm-btn');
            errBox.style.display = 'none';
            if (!reason) { errBox.textContent = 'A reason is required.'; errBox.style.display = 'block'; return; }
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div> Declining…';
            try {
                const res = await fetch(`/api/${this.site}/open-collab/admin/payouts/${id}/reject`, { method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${this.token()}`, Accept: 'application/json' }, body: JSON.stringify({ reason }) });
                if (!res.ok) { const data = await res.json(); throw new Error(data.error || data.message || 'Decline failed.'); }
                this.closeAdminModal(el, 'decline'); this.toast('Payout declined'); this.loadSection(section, true);
            } catch (e) { errBox.textContent = e.message; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = 'Decline payout'; }
        }

        async submitAdminMarkPaid(el, section) {
            const id = this.state.pendingPaidId;
            const reference = el.querySelector('#paid-reference').value.trim();
            const notes = el.querySelector('#paid-notes').value.trim();
            const errBox = el.querySelector('#paid-errors');
            const btn = el.querySelector('#paid-confirm-btn');
            errBox.style.display = 'none';
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div> Saving…';
            try {
                const res = await fetch(`/api/${this.site}/open-collab/admin/payouts/${id}/paid`, { method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${this.token()}`, Accept: 'application/json' }, body: JSON.stringify({ reference: reference || undefined, notes: notes || undefined }) });
                if (!res.ok) { const data = await res.json(); throw new Error(data.error || data.message || 'Failed.'); }
                this.closeAdminModal(el, 'paid'); this.toast('✓ Payout marked as paid'); this.loadSection(section, true);
            } catch (e) { errBox.textContent = e.message; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = 'Confirm paid'; }
        }

        async submitAdminPayoutRetry(el, section, id, btn) {
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div>';
            try {
                await this.fetchJson(`/api/${this.site}/open-collab/admin/payouts/${id}/retry`, { method: 'POST' });
                this.toast('Retry queued'); this.loadSection(section, true);
            } catch (e) { this.toast(e.message || 'Retry failed', false); btn.disabled = false; btn.textContent = 'Retry'; }
        }

        async loadAdminWebhookEvents(el) {
            const loading = el.querySelector('#stripe-events-loading');
            const empty = el.querySelector('#stripe-events-empty');
            const wrap = el.querySelector('#stripe-events-wrap');
            try {
                const rows = await this.fetchJson(`/api/${this.site}/open-collab/admin/stripe-webhooks`);
                const events = Array.isArray(rows?.data) ? rows.data : (Array.isArray(rows) ? rows : []);
                if (events.length === 0) { loading.style.display = 'none'; empty.style.display = 'block'; return; }
                el.querySelector('#stripe-events-tbody').innerHTML = events.map((event) => `<tr><td style="font-family:monospace;font-size:.78rem;">${esc(event.stripe_event_id)}</td><td>${esc(event.type)}</td><td>${date(event.processed_at)}</td><td>${date(event.failed_at)}</td><td style="max-width:280px;white-space:normal;">${esc(event.error_message || '')}</td></tr>`).join('');
                loading.style.display = 'none'; wrap.style.display = 'block';
            } catch { loading.textContent = 'Unable to load Stripe webhook history.'; }
        }
    }

    window.OpenCollabSurfaceRenderer = OpenCollabSurfaceRenderer;
})();