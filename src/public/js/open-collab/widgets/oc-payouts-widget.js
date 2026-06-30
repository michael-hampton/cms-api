/**
 * oc-payouts-widget.js
 * Owns the contributor-facing payouts surface:
 *   - payout_stats_grid
 *   - payout_history_table (history list + "request payout" form/action)
 *
 * Actions owned by this widget: requesting a payout.
 */
(() => {
    const {esc, cap, money, date, badge, statsGrid, errorBox, toast} = window.OpenCollabShared;

    class PayoutsWidget {
        static components = ['payout_stats_grid', 'payout_history_table'];

        constructor({site, api, reload}) {
            this.site = site;
            this.api = api;
            this.reload = reload; // (section, bypassCache) => void — re-fetches & re-renders this section
            this.state = {payoutFilter: 'all', payouts: [], payoutBalance: 0, minPayout: 5000};
        }

        normalise(component, raw) {
            if (component === 'payout_stats_grid') {
                return {items: [
                        {label: 'Available to Withdraw', value: raw.available_to_withdraw ?? raw.available ?? raw.pending ?? 0, format: 'money', variant: 'accent', sub: 'Settled minus deductions'},
                        {label: 'Estimated', value: raw.estimated ?? 0, format: 'money', sub: 'Visible, not payable yet'},
                        {label: 'Confirmed', value: raw.confirmed ?? 0, format: 'money', sub: 'Approved, not settled'},
                        {label: 'Withdrawn', value: raw.withdrawn ?? 0, format: 'money', variant: 'green', sub: 'Paid out'},
                        {label: 'Deductions', value: raw.open_liabilities ?? 0, format: 'money', sub: 'Open liabilities'},
                        {label: 'Pending Payouts', value: raw.pending_payouts ?? 0, format: 'money', sub: 'Pending or approved'},
                    ]};
            }
            if (component === 'payout_history_table') {
                return {items: Array.isArray(raw) ? raw : (raw.items ?? [])};
            }
            return raw;
        }

        async render(el, section, component, data) {
            if (component === 'payout_stats_grid') {
                el.innerHTML = statsGrid(data.items ?? []);
                return;
            }
            if (component === 'payout_history_table') {
                await this.renderHistory(el, section, data);
                return;
            }
            el.innerHTML = errorBox(`Payouts widget cannot render: ${component}`);
        }

        async renderHistory(el, section, data) {
            this.state.payouts = data.items ?? [];
            this.state.minPayout = 5000;
            try {
                const balancePayload = await this.api.fetchJson(`/api/${this.site}/open-collab/payouts/balance`);
                const balance = balancePayload?.data ?? balancePayload ?? {};
                this.state.payoutBalance = Number(balance.balance_pence ?? balance.available_to_withdraw ?? 0);
            } catch {
                this.state.payoutBalance = 0;
            }

            el.innerHTML = `<div class="oc-grid-sidebar" style="align-items:start;"><div class="oc-card" style="animation:fadeSlideIn .45s ease;"><div class="oc-card__header"><span class="oc-card__title" data-payout-title>Payout History</span><span data-payout-count style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span></div><div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;">${['all','pending','approved','paid','rejected'].map((s,i)=>`<button class="filter-pill${i===0?' filter-pill--active':''}" data-payout-filter="${s}">${cap(s)}</button>`).join('')}</div><div data-payout-empty style="display:none;padding:48px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;margin-bottom:6px;" data-payout-empty-title>No payout requests yet</div><div style="font-size:.85rem;" data-payout-empty-sub>Once your balance reaches £50.00, you can request a payout.</div></div><div data-payout-table-wrap style="display:none;overflow-x:auto;"><table class="oc-table"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Reference</th><th></th></tr></thead><tbody data-payout-tbody></tbody></table></div></div><div style="position:sticky;top:calc(var(--header-h) + 20px);"><div class="oc-card" style="animation:fadeSlideIn .5s ease;"><div class="oc-card__header"><span class="oc-card__title" style="font-size:.95rem;">Request Payout</span></div><div class="oc-card__body"><div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px;text-align:center;"><div style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">Available now</div><div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--navy);" data-payout-balance>${money(this.state.payoutBalance)}</div></div><div data-payout-errors class="oc-form-errors" style="display:none;margin-bottom:12px;"></div><div data-payout-form-wrap><div class="oc-form-group"><label class="oc-label" for="payout-method">Payout method</label><select class="oc-select" data-payout-method id="payout-method"><option value="stripe">Bank transfer</option><option value="paypal">PayPal</option><option value="other">Other</option></select><div class="oc-help">Your payout details are configured in <a href="/contributor/settings#payment">Settings</a>.</div></div><button class="oc-btn oc-btn--amber oc-btn--block" data-payout-submit>Request ${money(this.state.payoutBalance)}</button></div><div data-payout-minimum-note style="display:none;padding:16px;text-align:center;border:1.5px dashed var(--border);border-radius:var(--radius);"><div style="font-size:.85rem;font-weight:500;margin-bottom:4px;color:var(--navy);">Minimum not reached</div><div style="font-size:.78rem;color:var(--slate);">You need at least <strong>${money(this.state.minPayout)}</strong> to request a payout.</div></div><div style="font-size:.72rem;color:var(--slate);line-height:1.6;padding-top:12px;border-top:1px solid var(--border);margin-top:16px;">Payouts are processed manually by our team, typically within 2–5 business days after approval.</div></div></div></div></div>`;

            el.querySelectorAll('[data-payout-filter]').forEach(button => button.addEventListener('click', () => {
                this.state.payoutFilter = button.dataset.payoutFilter;
                el.querySelectorAll('[data-payout-filter]').forEach(b => b.classList.remove('filter-pill--active'));
                button.classList.add('filter-pill--active');
                this.paintPayouts(el);
            }));
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

        // ─── Action owned by this widget ──────────────────────────────────────
        async requestPayout(el, section) {
            const button = el.querySelector('[data-payout-submit]');
            const errors = el.querySelector('[data-payout-errors]');
            button.disabled = true;
            button.innerHTML = '<div class="oc-spinner"></div> Submitting…';
            errors.style.display = 'none';
            try {
                await this.api.sendJson(`/api/${this.site}/open-collab/payouts`, {method: el.querySelector('[data-payout-method]').value});
                toast('✓ Payout request submitted. Our team will process it shortly.');
                this.api.bust(`/api/${this.site}/open-collab/payouts/balance`);
                this.reload(section, true);
            } catch (e) {
                errors.textContent = e.message;
                errors.style.display = 'block';
                button.disabled = false;
                button.textContent = `Request ${money(this.state.payoutBalance)}`;
            }
        }
    }

    window.OpenCollabPayoutsWidget = PayoutsWidget;
})();