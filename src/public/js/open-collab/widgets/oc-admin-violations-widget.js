/**
 * oc-admin-violations-widget.js
 * Owns the admin-facing violations surface:
 *   - admin_violations_table (search/status/severity filters + list + resolve modal)
 *
 * Actions owned by this widget: resolving a violation.
 *
 * Resolve is only available when the surface context flags it (mirrors the old
 * $canResolveViolation server-side check, now passed through as
 * context.violations.can_resolve instead of being baked into the page).
 */
(() => {
    const {esc, cap, date, statsGrid, errorBox, toast, statusToastMarkup} = window.OpenCollabShared;

    const SEV_COLORS = {high: '#ef4444', medium: '#f97316', low: '#eab308'};
    const ACT_BADGES = {warning: 'oc-badge--waiting-approval', suspension: 'oc-badge--revoked', ban: 'oc-badge--revoked'};
    const ACT_LABELS = {warning: 'Warning', suspension: 'Suspended', ban: 'Banned'};

    class AdminViolationsWidget {
        static components = ['admin_violation_stats_grid', 'admin_violations_table'];

        constructor({site, api, context = {}, reload}) {
            this.site = site;
            this.api = api;
            this.canResolve = !!context?.violations?.can_resolve;
            this.reload = reload;
            this.state = {
                violations: [], statusFilter: 'all', severityFilter: 'all', query: '',
                pendingResolveId: null,
            };
            this.debounceTimer = null;
        }

        normalise(component, raw) {
            const items = Array.isArray(raw) ? raw : (raw.items ?? raw.data ?? []);

            if (component === 'admin_violation_stats_grid') {
                const open = items.filter(v => !v.resolved_at);
                return {items: [
                        {label: 'Total Violations', value: items.length, format: 'number', variant: 'accent', sub: 'All time'},
                        {label: 'Open', value: open.length, format: 'number', sub: 'Awaiting resolution'},
                        {label: 'High Severity (Open)', value: open.filter(v => v.severity === 'high').length, format: 'number', sub: 'Needs priority review'},
                        {label: 'Resolved', value: items.filter(v => !!v.resolved_at).length, format: 'number', variant: 'green', sub: 'Closed'},
                    ]};
            }

            if (component === 'admin_violations_table') {
                return {items};
            }

            return raw;
        }

        render(el, section, component, data) {
            if (component === 'admin_violation_stats_grid') {
                el.innerHTML = statsGrid(data.items ?? []);
                return;
            }
            if (component === 'admin_violations_table') {
                this.renderTable(el, section, data);
                return;
            }
            el.innerHTML = errorBox(`Admin violations widget cannot render: ${component}`);
        }

        renderTable(el, section, data) {
            this.state.violations = data.items ?? [];

            el.innerHTML = `
                ${statusToastMarkup}
                ${this.canResolve ? this.resolveModal() : ''}
                <div class="oc-card" style="margin-bottom:20px;padding:16px 20px;">
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <div style="position:relative;flex:1;min-width:200px;">
                            <svg viewBox="0 0 20 20" fill="currentColor" width="16" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--slate-light);pointer-events:none;"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                            <input class="oc-input" type="text" data-search-input placeholder="Search by contributor ID, type or reason…" style="padding-left:38px;" autocomplete="off">
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button class="filter-pill filter-pill--active" data-status="all">All</button>
                            <button class="filter-pill" data-status="open">Open</button>
                            <button class="filter-pill" data-status="resolved">Resolved</button>
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button class="sev-pill sev-pill--active" data-severity="all">All severities</button>
                            <button class="sev-pill" data-severity="high" style="color:#ef4444;">High</button>
                            <button class="sev-pill" data-severity="medium" style="color:#f97316;">Medium</button>
                            <button class="sev-pill" data-severity="low" style="color:#eab308;">Low</button>
                        </div>
                    </div>
                </div>
                <div class="oc-card">
                    <div class="oc-card__header">
                        <span class="oc-card__title" data-results-title>All Violations</span>
                        <span data-results-count style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
                    </div>
                    <div data-violations-empty style="display:none;padding:64px 24px;text-align:center;color:var(--slate);">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="36" style="opacity:.15;display:block;margin:0 auto 16px;color:var(--green);"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <div style="font-size:1.05rem;font-weight:600;color:var(--navy);" data-empty-message>No violations recorded</div>
                        <div style="font-size:.875rem;color:var(--slate);margin-top:4px;" data-empty-sub>Contributors are behaving well.</div>
                    </div>
                    <div data-violations-table-wrap style="display:none;overflow-x:auto;">
                        <table class="oc-table">
                            <thead><tr><th>Contributor</th><th>Type</th><th>Severity</th><th>Action</th><th>Date</th><th>Status</th><th></th></tr></thead>
                            <tbody data-violations-tbody></tbody>
                        </table>
                    </div>
                </div>
            `;

            const searchInput = el.querySelector('[data-search-input]');
            searchInput.value = this.state.query;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    this.state.query = e.target.value.trim().toLowerCase();
                    this.paintViolations(el);
                }, 300);
            });

            el.querySelectorAll('[data-status]').forEach(btn => btn.addEventListener('click', () => {
                this.state.statusFilter = btn.dataset.status;
                el.querySelectorAll('[data-status]').forEach(b => b.classList.remove('filter-pill--active'));
                btn.classList.add('filter-pill--active');
                this.paintViolations(el);
            }));

            el.querySelectorAll('[data-severity]').forEach(btn => btn.addEventListener('click', () => {
                this.state.severityFilter = btn.dataset.severity;
                el.querySelectorAll('[data-severity]').forEach(b => b.classList.remove('sev-pill--active'));
                btn.classList.add('sev-pill--active');
                this.paintViolations(el);
            }));

            if (this.canResolve) {
                el.querySelector('[data-resolve-cancel]').addEventListener('click', () => this.closeResolveModal(el));
                el.querySelector('[data-resolve-confirm]').addEventListener('click', () => this.submitResolve(el, section));
                el.querySelector('[data-resolve-modal]').addEventListener('click', e => { if (e.target === e.currentTarget) this.closeResolveModal(el); });
            }

            this.paintViolations(el);
        }

        resolveModal() {
            return `<div data-resolve-modal style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"><div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:440px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);"><h3 style="font-family:var(--font-display);font-size:1.15rem;color:var(--navy);margin-bottom:6px;">Resolve violation</h3><p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">Resolving will lift any associated suspension or ban if no other active violations remain.</p><input type="hidden" data-resolve-violation-id><div class="oc-form-group"><label class="oc-label oc-label--optional" for="resolve-notes">Resolution notes</label><textarea class="oc-textarea" data-resolve-notes id="resolve-notes" rows="3" placeholder="Optional notes…" style="min-height:72px;"></textarea></div><div data-resolve-errors class="oc-form-errors" style="display:none;margin-bottom:12px;"></div><div style="display:flex;gap:10px;"><button data-resolve-cancel class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button><button data-resolve-confirm class="oc-btn oc-btn--primary" style="flex:1;">Resolve</button></div></div></div>`;
        }

        paintViolations(el) {
            let filtered = this.state.violations;
            if (this.state.statusFilter === 'open') filtered = filtered.filter(v => !v.resolved_at);
            if (this.state.statusFilter === 'resolved') filtered = filtered.filter(v => v.resolved_at);
            if (this.state.severityFilter !== 'all') filtered = filtered.filter(v => v.severity === this.state.severityFilter);
            if (this.state.query) {
                filtered = filtered.filter(v =>
                    String(v.user_id).includes(this.state.query) ||
                    (v.type ?? '').toLowerCase().includes(this.state.query) ||
                    (v.reason ?? '').toLowerCase().includes(this.state.query)
                );
            }

            el.querySelector('[data-results-count]').textContent = filtered.length;
            el.querySelector('[data-results-title]').textContent =
                this.state.statusFilter === 'all' ? 'All Violations' : `${cap(this.state.statusFilter)} Violations`;

            const emptyBox = el.querySelector('[data-violations-empty]');
            const tableWrap = el.querySelector('[data-violations-table-wrap]');

            if (!filtered.length) {
                tableWrap.style.display = 'none';
                emptyBox.style.display = 'block';
                el.querySelector('[data-empty-message]').textContent =
                    this.state.query ? `No violations matching "${this.state.query}"` : 'No violations recorded';
                el.querySelector('[data-empty-sub]').textContent =
                    this.state.query ? 'Try a different search term.' : 'Contributors are behaving well.';
                return;
            }

            emptyBox.style.display = 'none';
            tableWrap.style.display = 'block';

            const rows = filtered.map(v => {
                const isResolved = !!v.resolved_at;
                const severity = v.severity ?? 'low';
                const action = v.action_taken ?? 'warning';
                const sevColor = SEV_COLORS[severity] ?? '#64748b';
                const actBadge = ACT_BADGES[action] ?? 'oc-badge--draft';
                const actLabel = ACT_LABELS[action] ?? cap(action);
                const createdAt = date(v.created_at);
                const actionCell = isResolved || !this.canResolve
                    ? `<div style="display:flex;gap:6px;justify-content:flex-end;"><a href="/${esc(this.site)}/open-collab/admin/contributors/${v.user_id}/violations" class="oc-btn oc-btn--ghost oc-btn--sm">Profile</a></div>`
                    : `<div style="display:flex;gap:6px;justify-content:flex-end;"><a href="/${esc(this.site)}/open-collab/admin/contributors/${v.user_id}/violations" class="oc-btn oc-btn--ghost oc-btn--sm">Profile</a><button data-open-resolve="${v.id}" class="oc-btn oc-btn--primary oc-btn--sm">Resolve</button></div>`;

                return `<tr>
                    <td><a href="/${esc(this.site)}/open-collab/admin/contributors/${v.user_id}" style="font-weight:500;color:var(--navy);text-decoration:none;">User #${v.user_id}</a></td>
                    <td style="font-size:.82rem;color:var(--navy);">${esc((v.type ?? '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()))}</td>
                    <td><span style="display:inline-flex;align-items:center;gap:5px;font-size:.78rem;font-weight:600;color:${sevColor};"><span style="width:6px;height:6px;border-radius:50%;background:currentColor;"></span>${cap(severity)}</span></td>
                    <td><span class="oc-badge ${actBadge}">${actLabel}</span></td>
                    <td style="font-size:.78rem;color:var(--slate);">${createdAt}</td>
                    <td>${isResolved ? '<span class="oc-badge oc-badge--published">Resolved</span>' : '<span class="oc-badge oc-badge--draft">Open</span>'}</td>
                    <td style="text-align:right;">${actionCell}</td>
                </tr>
                ${v.reason ? `<tr><td colspan="7" style="padding:0 16px 10px;font-size:.78rem;color:var(--slate);background:var(--cream-dark);"><strong>Reason:</strong> ${esc(v.reason)}</td></tr>` : ''}`;
            });

            el.querySelector('[data-violations-tbody]').innerHTML = rows.join('');

            if (this.canResolve) {
                el.querySelectorAll('[data-open-resolve]').forEach(btn => btn.addEventListener('click', () => this.openResolveModal(el, btn.dataset.openResolve)));
            }
        }

        openResolveModal(el, id) {
            this.state.pendingResolveId = id;
            el.querySelector('[data-resolve-notes]').value = '';
            el.querySelector('[data-resolve-errors]').style.display = 'none';
            el.querySelector('[data-resolve-modal]').style.display = 'grid';
        }

        closeResolveModal(el) {
            this.state.pendingResolveId = null;
            el.querySelector('[data-resolve-modal]').style.display = 'none';
        }

        // ─── Action owned by this widget ──────────────────────────────────────
        async submitResolve(el, section) {
            const id = this.state.pendingResolveId;
            const notes = el.querySelector('[data-resolve-notes]').value.trim();
            const errBox = el.querySelector('[data-resolve-errors]');
            const btn = el.querySelector('[data-resolve-confirm]');
            errBox.style.display = 'none';
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div>';
            try {
                await this.api.sendJson(`/api/${this.site}/open-collab/admin/violations/${id}/resolve`, {notes: notes || undefined});
                this.closeResolveModal(el);
                toast('✓ Violation resolved');
                this.reload(section, true);
            } catch (e) {
                errBox.textContent = e.message;
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Resolve';
            }
        }
    }

    window.OpenCollabAdminViolationsWidget = AdminViolationsWidget;
})();