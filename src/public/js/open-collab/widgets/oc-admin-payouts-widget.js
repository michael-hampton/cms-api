/**
 * oc-admin-payouts-widget.js
 * Owns the admin-facing payouts surface:
 *   - admin_payout_summary_stats
 *   - admin_payout_stats_grid (search/filter table, pending-approval panel,
 *     Stripe webhook event log)
 *
 * Actions owned by this widget: approve, decline, mark-paid, retry.
 */
(() => {
    const {esc, cap, date, money, statsGrid, errorBox, toast, statusToastMarkup} = window.OpenCollabShared;

    class AdminPayoutsWidget {
        static components = ['admin_payout_summary_stats', 'admin_payout_stats_grid'];

        constructor({site, api, reload}) {
            this.site = site;
            this.api = api;
            this.reload = reload;
            this.state = {
                adminPayouts: [], adminPayoutFilter: 'all', adminPayoutQuery: '',
                pendingDeclineId: null, pendingPaidId: null,
            };
            this.debounceTimer = null;
        }

        normalise(component, raw) {
            if (component === 'admin_payout_summary_stats') {
                return {items: [
                        {label: 'Available', value: raw.available_to_withdraw ?? 0, format: 'money', variant: 'accent', sub: 'Settled, ready to pay'},
                        {label: 'Estimated', value: raw.estimated_balance ?? 0, format: 'money', sub: 'Visible, not payable yet'},
                        {label: 'Confirmed', value: raw.confirmed_balance ?? 0, format: 'money', sub: 'Approved, not settled'},
                        {label: 'Withdrawn', value: raw.withdrawn_balance ?? 0, format: 'money', variant: 'green', sub: 'Paid out'},
                        {label: 'Pending', value: raw.in_flight_payouts ?? 0, format: 'money', sub: 'Pending or approved payouts'},
                    ]};
            }
            if (component === 'admin_payout_stats_grid') {
                return {items: Array.isArray(raw) ? raw : (raw.items ?? [])};
            }
            return raw;
        }

        render(el, section, component, data) {
            if (component === 'admin_payout_summary_stats') {
                el.innerHTML = statsGrid(data.items ?? []);
                return;
            }
            if (component === 'admin_payout_stats_grid') {
                this.renderTable(el, section, data);
                return;
            }
            el.innerHTML = errorBox(`Admin payouts widget cannot render: ${component}`);
        }

        renderTable(el, section, data) {
            this.state.adminPayouts = data.items ?? [];
            el.innerHTML = `
                ${statusToastMarkup}
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
            el.querySelector('[data-close-decline]').addEventListener('click', () => this.closeModal(el, 'decline'));
            el.querySelector('[data-close-paid]').addEventListener('click', () => this.closeModal(el, 'paid'));
            el.querySelector('#decline-confirm-btn').addEventListener('click', () => this.submitDecline(el, section));
            el.querySelector('#paid-confirm-btn').addEventListener('click', () => this.submitMarkPaid(el, section));

            this.paintAdminPayouts(el, section);
            this.loadWebhookEvents(el);
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

                const failureReason = p.provider_response_json?.error || p.provider_response_json?.reason || '';
                const failureRow = failureReason ? `<tr><td colspan="8" style="padding:0 16px 10px;font-size:.78rem;color:var(--red);background:#fff9f9;"><strong>Stripe failure:</strong> ${esc(failureReason)}</td></tr>` : '';

                return `<tr><td style="font-family:monospace;font-size:.78rem;color:var(--slate);">PAY-${String(p.id).padStart(6, '0')}</td><td><a href="/admin/contributors/${p.user_id}" style="font-weight:500;color:var(--navy);text-decoration:none;">User #${p.user_id}</a></td><td style="font-weight:600;">${money(p.amount_pence ?? p.amount, p.currency)}</td><td>${esc(p.currency)}</td><td><span class="oc-badge ${statusCls}">${cap(status)}</span></td><td>${this.financeSummary(p)}</td><td>${date(p.created_at)}</td><td><div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">${retryBtn}${markPaidBtn}${pdfBtn}</div></td></tr>${rejectionRow}${failureRow}`;
            }).join('');

            el.querySelectorAll('[data-approve]').forEach(btn => btn.addEventListener('click', () => this.submitApprove(el, section, btn.dataset.approve, btn)));
            el.querySelectorAll('[data-decline]').forEach(btn => btn.addEventListener('click', () => this.openModal(el, 'decline', btn.dataset.decline)));
            el.querySelectorAll('[data-paid]').forEach(btn => btn.addEventListener('click', () => this.openModal(el, 'paid', btn.dataset.paid)));
            el.querySelectorAll('[data-retry]').forEach(btn => btn.addEventListener('click', () => this.submitRetry(el, section, btn.dataset.retry, btn)));
        }

        financeSummary(p) {
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

        // ─── Modal open/close ──────────────────────────────────────────────────
        openModal(el, type, id) {
            if (type === 'decline') {
                this.state.pendingDeclineId = id;
                el.querySelector('#decline-reason').value = ''; el.querySelector('#decline-errors').style.display = 'none';
                el.querySelector('#decline-modal').style.display = 'grid'; el.querySelector('#decline-reason').focus();
            } else if (type === 'paid') {
                this.state.pendingPaidId = id;
                el.querySelector('#paid-reference').value = ''; el.querySelector('#paid-notes').value = ''; el.querySelector('#paid-errors').style.display = 'none';
                el.querySelector('#paid-modal').style.display = 'grid'; el.querySelector('#paid-reference').focus();
            }
        }

        closeModal(el, type) {
            if (type === 'decline') { this.state.pendingDeclineId = null; el.querySelector('#decline-modal').style.display = 'none'; }
            else if (type === 'paid') { this.state.pendingPaidId = null; el.querySelector('#paid-modal').style.display = 'none'; }
        }

        // ─── Actions owned by this widget ─────────────────────────────────────
        async submitApprove(el, section, id, btn) {
            if (!confirm('Approve this payout request?')) return;
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div>';
            try {
                await this.api.fetchJson(`/api/${this.site}/open-collab/admin/payouts/${id}/approve`, { method: 'POST' });
                toast('✓ Payout approved'); this.reload(section, true);
            } catch (e) { toast(e.message || 'Approval failed', false); btn.disabled = false; btn.textContent = 'Approve'; }
        }

        async submitDecline(el, section) {
            const id = this.state.pendingDeclineId;
            const reason = el.querySelector('#decline-reason').value.trim();
            const errBox = el.querySelector('#decline-errors');
            const btn = el.querySelector('#decline-confirm-btn');
            errBox.style.display = 'none';
            if (!reason) { errBox.textContent = 'A reason is required.'; errBox.style.display = 'block'; return; }
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div> Declining…';
            try {
                await this.api.sendJson(`/api/${this.site}/open-collab/admin/payouts/${id}/reject`, { reason });
                this.closeModal(el, 'decline'); toast('Payout declined'); this.reload(section, true);
            } catch (e) { errBox.textContent = e.message; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = 'Decline payout'; }
        }

        async submitMarkPaid(el, section) {
            const id = this.state.pendingPaidId;
            const reference = el.querySelector('#paid-reference').value.trim();
            const notes = el.querySelector('#paid-notes').value.trim();
            const errBox = el.querySelector('#paid-errors');
            const btn = el.querySelector('#paid-confirm-btn');
            errBox.style.display = 'none';
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div> Saving…';
            try {
                await this.api.sendJson(`/api/${this.site}/open-collab/admin/payouts/${id}/paid`, { reference: reference || undefined, notes: notes || undefined });
                this.closeModal(el, 'paid'); toast('✓ Payout marked as paid'); this.reload(section, true);
            } catch (e) { errBox.textContent = e.message; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = 'Confirm paid'; }
        }

        async submitRetry(el, section, id, btn) {
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div>';
            try {
                await this.api.fetchJson(`/api/${this.site}/open-collab/admin/payouts/${id}/retry`, { method: 'POST' });
                toast('Retry queued'); this.reload(section, true);
            } catch (e) { toast(e.message || 'Retry failed', false); btn.disabled = false; btn.textContent = 'Retry'; }
        }

        async loadWebhookEvents(el) {
            const loading = el.querySelector('#stripe-events-loading');
            const empty = el.querySelector('#stripe-events-empty');
            const wrap = el.querySelector('#stripe-events-wrap');
            try {
                const rows = await this.api.fetchJson(`/api/${this.site}/open-collab/admin/stripe-webhooks`);
                const events = Array.isArray(rows?.data) ? rows.data : (Array.isArray(rows) ? rows : []);
                if (events.length === 0) { loading.style.display = 'none'; empty.style.display = 'block'; return; }
                el.querySelector('#stripe-events-tbody').innerHTML = events.map((event) => `<tr><td style="font-family:monospace;font-size:.78rem;">${esc(event.stripe_event_id)}</td><td>${esc(event.type)}</td><td>${date(event.processed_at)}</td><td>${date(event.failed_at)}</td><td style="max-width:280px;white-space:normal;">${esc(event.error_message || '')}</td></tr>`).join('');
                loading.style.display = 'none'; wrap.style.display = 'block';
            } catch { loading.textContent = 'Unable to load Stripe webhook history.'; }
        }
    }

    window.OpenCollabAdminPayoutsWidget = AdminPayoutsWidget;
})();