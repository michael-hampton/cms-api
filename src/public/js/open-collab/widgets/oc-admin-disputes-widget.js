/**
 * oc-admin-disputes-widget.js
 * Owns the admin-facing disputes surface:
 *   - admin_dispute_summary_stats
 *   - admin_dispute_stats_grid (search/filter list + resolve/reject modals)
 *
 * Actions owned by this widget: resolving a dispute (with optional ledger
 * adjustment) and rejecting a dispute.
 */
(() => {
    const {esc, cap, date, statsGrid, errorBox, toast, statusToastMarkup} = window.OpenCollabShared;

    class AdminDisputesWidget {
        static components = ['admin_dispute_summary_stats', 'admin_dispute_stats_grid'];

        constructor({site, api, reload}) {
            this.site = site;
            this.api = api;
            this.reload = reload;
            this.state = {
                adminDisputes: [], adminDisputeFilter: 'all', adminDisputeQuery: '',
                pendingResolveId: null, pendingRejectId: null,
            };
            this.debounceTimer = null;
        }

        normalise(component, raw) {
            if (component === 'admin_dispute_summary_stats') {
                const disputes = Array.isArray(raw) ? raw : (raw.items ?? []);
                return {items: [
                        {label: 'Open', value: disputes.filter(d => d.status === 'open').length, format: 'number', variant: 'accent', sub: 'Awaiting review'},
                        {label: 'Resolved', value: disputes.filter(d => d.status === 'resolved').length, format: 'number', variant: 'green', sub: 'Closed in favour'},
                        {label: 'Rejected', value: disputes.filter(d => d.status === 'rejected').length, format: 'number', sub: 'No action taken'},
                    ]};
            }
            if (component === 'admin_dispute_stats_grid') {
                return {items: Array.isArray(raw) ? raw : (raw.items ?? [])};
            }
            return raw;
        }

        render(el, section, component, data) {
            if (component === 'admin_dispute_summary_stats') {
                el.innerHTML = statsGrid(data.items ?? []);
                return;
            }
            if (component === 'admin_dispute_stats_grid') {
                this.renderTable(el, section, data);
                return;
            }
            el.innerHTML = errorBox(`Admin disputes widget cannot render: ${component}`);
        }

        renderTable(el, section, data) {
            this.state.adminDisputes = data.items ?? [];
            el.innerHTML = `
                ${statusToastMarkup}
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
            el.querySelector('[data-close-resolve]').addEventListener('click', () => this.closeModal(el, 'resolve'));
            el.querySelector('[data-close-reject]').addEventListener('click', () => this.closeModal(el, 'reject'));
            el.querySelector('#resolve-confirm-btn').addEventListener('click', () => this.submitResolve(el, section));
            el.querySelector('#reject-confirm-btn').addEventListener('click', () => this.submitReject(el, section));

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
            listBox.querySelectorAll('[data-btn-resolve]').forEach(btn => btn.addEventListener('click', () => this.openModal(el, 'resolve', btn.dataset.btnResolve)));
            listBox.querySelectorAll('[data-btn-reject]').forEach(btn => btn.addEventListener('click', () => this.openModal(el, 'reject', btn.dataset.btnReject)));
        }

        // ─── Modal open/close ──────────────────────────────────────────────────
        openModal(el, type, id) {
            if (type === 'resolve') {
                this.state.pendingResolveId = id;
                el.querySelector('#resolve-notes').value = ''; el.querySelector('#resolve-adjustment').value = ''; el.querySelector('#resolve-adjustment-reason').value = '';
                el.querySelector('#resolve-reason-group').style.display = 'none'; el.querySelector('#resolve-errors').style.display = 'none';
                el.querySelector('#resolve-modal').style.display = 'grid'; el.querySelector('#resolve-notes').focus();
            } else if (type === 'reject') {
                this.state.pendingRejectId = id;
                el.querySelector('#reject-notes').value = ''; el.querySelector('#reject-errors').style.display = 'none';
                el.querySelector('#reject-modal').style.display = 'grid'; el.querySelector('#reject-notes').focus();
            }
        }

        closeModal(el, type) {
            if (type === 'resolve') { this.state.pendingResolveId = null; el.querySelector('#resolve-modal').style.display = 'none'; }
            else if (type === 'reject') { this.state.pendingRejectId = null; el.querySelector('#reject-modal').style.display = 'none'; }
        }

        // ─── Actions owned by this widget ─────────────────────────────────────
        async submitResolve(el, section) {
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
                await this.api.sendJson(`/api/${this.site}/open-collab/admin/disputes/${id}/resolve`, payload);
                this.closeModal(el, 'resolve'); toast('✓ Dispute resolved'); this.reload(section, true);
            } catch (e) { errBox.textContent = e.message; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = 'Resolve dispute'; }
        }

        async submitReject(el, section) {
            const id = this.state.pendingRejectId;
            const notes = el.querySelector('#reject-notes').value.trim();
            const errBox = el.querySelector('#reject-errors');
            const btn = el.querySelector('#reject-confirm-btn');
            errBox.style.display = 'none';
            if (!notes) { errBox.textContent = 'Admin notes are required.'; errBox.style.display = 'block'; return; }
            btn.disabled = true; btn.innerHTML = '<div class="oc-spinner"></div> Rejecting…';
            try {
                await this.api.sendJson(`/api/${this.site}/open-collab/admin/disputes/${id}/reject`, { admin_notes: notes });
                this.closeModal(el, 'reject'); toast('Dispute rejected'); this.reload(section, true);
            } catch (e) { errBox.textContent = e.message; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = 'Reject dispute'; }
        }
    }

    window.OpenCollabAdminDisputesWidget = AdminDisputesWidget;
})();