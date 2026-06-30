/**
 * oc-disputes-widget.js
 * Owns the contributor-facing disputes surface:
 *   - dispute_stats_grid
 *   - disputes_table (list + "raise a dispute" modal/action)
 *
 * Actions owned by this widget: submitting a new dispute.
 */
(() => {
    const {esc, cap, date, money, badge, statsGrid, errorBox, toast} = window.OpenCollabShared;

    class DisputesWidget {
        static components = ['dispute_stats_grid', 'disputes_table'];

        constructor({site, api, context = {}, reload}) {
            this.site = site;
            this.api = api;
            this.context = context;
            this.reload = reload;
            this.state = {disputeFilter: 'all', disputes: [], eligibleEntries: []};
        }

        normalise(component, raw) {
            if (component === 'dispute_stats_grid') {
                const disputes = Array.isArray(raw) ? raw : [];
                return {items: [
                        {label: 'Open Disputes', value: disputes.filter(d => d.status === 'open').length, format: 'number', variant: 'accent', sub: 'Under review'},
                        {label: 'Resolved', value: disputes.filter(d => d.status === 'resolved').length, format: 'number', variant: 'green', sub: 'Closed in your favour'},
                        {label: 'Rejected', value: disputes.filter(d => d.status === 'rejected').length, format: 'number', sub: 'No action taken'},
                    ]};
            }
            if (component === 'disputes_table') {
                return {
                    items: Array.isArray(raw) ? raw : (raw.items ?? []),
                    eligible_entries: this.context?.disputes?.eligible_entries ?? raw.eligible_entries ?? [],
                };
            }
            return raw;
        }

        render(el, section, component, data) {
            if (component === 'dispute_stats_grid') {
                el.innerHTML = statsGrid(data.items ?? []);
                return;
            }
            if (component === 'disputes_table') {
                this.renderTable(el, section, data);
                return;
            }
            el.innerHTML = errorBox(`Disputes widget cannot render: ${component}`);
        }

        renderTable(el, section, data) {
            this.state.disputes = data.items ?? [];
            this.state.eligibleEntries = data.eligible_entries ?? [];

            el.innerHTML = `${this.disputeModal()}<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap;">${['all','open','resolved','rejected'].map((s,i)=>`<button class="filter-pill${i===0?' filter-pill--active':''}" data-dispute-filter="${s}">${cap(s)}</button>`).join('')}${this.state.eligibleEntries.length ? '<button data-open-dispute-modal class="oc-btn oc-btn--primary oc-btn--sm" style="margin-left:auto;">Raise a dispute</button>' : ''}</div><div class="oc-card"><div class="oc-card__header"><span class="oc-card__title" data-dispute-title>My Disputes</span><span data-dispute-count style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span></div><div data-disputes-empty style="display:none;padding:48px 24px;text-align:center;color:var(--slate);"><div style="font-weight:500;margin-bottom:6px;">No disputes</div><div style="font-size:.85rem;">If you believe there's an error in your earnings, you can raise a dispute above.</div></div><div data-disputes-list style="display:none;flex-direction:column;"></div></div>`;

            el.querySelectorAll('[data-dispute-filter]').forEach(button => button.addEventListener('click', () => {
                this.state.disputeFilter = button.dataset.disputeFilter;
                el.querySelectorAll('[data-dispute-filter]').forEach(b => b.classList.remove('filter-pill--active'));
                button.classList.add('filter-pill--active');
                this.paintDisputes(el);
            }));
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

        // ─── Action owned by this widget ──────────────────────────────────────
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
                await this.api.sendJson(`/api/${this.site}/open-collab/disputes`, {earnings_ledger_id: ledgerId, reason});
                this.closeDisputeModal(el);
                toast("✓ Dispute submitted — we'll review it shortly");
                this.reload(section, true);
            } catch (e) {
                errors.textContent = e.message;
                errors.style.display = 'block';
                button.disabled = false;
                button.textContent = 'Submit dispute';
            }
        }
    }

    window.OpenCollabDisputesWidget = DisputesWidget;
})();