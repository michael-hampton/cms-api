@section('logic')
<?php
/**
 * Template: open-collab/admin/escalations/index.php
 */
$pageTitle = 'Escalations';
$activeNav = 'escalations';
$breadcrumbs = [['label' => 'Escalations']];
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<div class="oc-card" style="padding:16px 20px;margin-bottom:14px;">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="oc-form-group" style="margin:0;min-width:160px;">
            <label class="oc-label" for="esc-filter-status">Status</label>
            <select class="oc-select" id="esc-filter-status">
                <option value="">All</option>
                <option value="open">Open</option>
                <option value="acknowledged">Acknowledged</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
                <option value="overdue">Overdue</option>
            </select>
        </div>
        <div class="oc-form-group" style="margin:0;min-width:160px;">
            <label class="oc-label" for="esc-filter-category">Category</label>
            <select class="oc-select" id="esc-filter-category">
                <option value="">All</option>
                <option value="copyright">Copyright</option>
                <option value="ai_generated">AI Generated</option>
                <option value="music_rights">Music Rights</option>
                <option value="brand_safety">Brand Safety</option>
                <option value="sponsored_content">Sponsored Content</option>
                <option value="affiliate_abuse">Affiliate Abuse</option>
                <option value="legal">Legal</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="oc-form-group" style="margin:0;min-width:160px;">
            <label class="oc-label" for="esc-filter-severity">Severity</label>
            <select class="oc-select" id="esc-filter-severity">
                <option value="">All</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
        </div>
        <button class="oc-btn oc-btn--primary oc-btn--sm" id="esc-apply-filters">Apply</button>
        <button class="oc-btn oc-btn--ghost oc-btn--sm" id="esc-reset-filters">Reset</button>
    </div>
</div>

<div class="oc-card" style="overflow:hidden;">
    <table class="oc-table" style="width:100%;">
        <thead>
        <tr>
            <th>Article</th>
            <th>Category</th>
            <th>Severity</th>
            <th>Assigned Team</th>
            <th>Assigned User</th>
            <th>Due Date</th>
            <th>Status</th>
            <th style="text-align:right;">Actions</th>
        </tr>
        </thead>
        <tbody id="esc-table-body">
        <tr><td colspan="8" style="padding:24px;color:var(--slate);">Loading…</td></tr>
        </tbody>
    </table>

    <div id="esc-empty-state" style="display:none;padding:64px 24px;text-align:center;color:var(--slate);">
        No escalations match these filters.
    </div>

    <div id="esc-error-state" style="display:none;padding:48px 24px;text-align:center;">
        <div style="color:var(--red);margin-bottom:14px;">Unable to load escalations.</div>
        <button class="oc-btn oc-btn--ghost oc-btn--sm" id="esc-retry-btn">Retry</button>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    class EscalationQueueManager {
        #site;
        #token;
        #filters = {};

        constructor(site, token) {
            this.#site = site;
            this.#token = token;

            document.getElementById('esc-apply-filters').addEventListener('click', () => { this.#readFilters(); this.load(); });
            document.getElementById('esc-reset-filters').addEventListener('click', () => {
                document.getElementById('esc-filter-status').value = '';
                document.getElementById('esc-filter-category').value = '';
                document.getElementById('esc-filter-severity').value = '';
                this.#filters = {};
                this.load();
            });
            document.getElementById('esc-retry-btn').addEventListener('click', () => this.load());

            this.load();
        }

        #readFilters() {
            const filters = {};
            const status = document.getElementById('esc-filter-status').value;
            const category = document.getElementById('esc-filter-category').value;
            const severity = document.getElementById('esc-filter-severity').value;
            if (status) filters.status = status;
            if (category) filters.category = category; // ASSUMED: forSite() supports category/severity filters — extend repository similarly to `status`
            if (severity) filters.severity = severity;
            this.#filters = filters;
        }

        async load() {
            const body = document.getElementById('esc-table-body');
            const empty = document.getElementById('esc-empty-state');
            const error = document.getElementById('esc-error-state');

            error.style.display = 'none';
            empty.style.display = 'none';
            body.innerHTML = `<tr><td colspan="8" style="padding:24px;color:var(--slate);">Loading…</td></tr>`;

            try {
                const params = new URLSearchParams(this.#filters);
                const res = await fetch(`/api/${this.#site}/open-collab/admin/escalations?${params.toString()}`, {
                    headers: { Authorization: `Bearer ${this.#token()}`, Accept: 'application/json' },
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const data = await res.json();
                const items = data.data ?? data;

                if (!items.length) {
                    body.innerHTML = '';
                    empty.style.display = 'block';
                    return;
                }

                body.innerHTML = items.map(e => this.#renderRow(e)).join('');
                this.#bindRowActions();
            } catch {
                body.innerHTML = '';
                error.style.display = 'block';
            }
        }

        #renderRow(e) {
            const actions = e.available_actions ?? []; // ASSUMED: EscalationResource gains the same permission-aware actions array

            return `
                <tr data-id="${e.id}">
                    <td data-label="Article">
                        <a href="/${this.#site}/open-collab/admin/moderation/${e.queue_entry_id}" style="color:var(--navy);font-weight:600;text-decoration:none;">
                            ${this.#escape(e.page_title ?? `Page #${e.page_id}`)}
                        </a>
                    </td>
                    <td data-label="Category">${this.#capitalise(e.category)}</td>
                    <td data-label="Severity"><span class="oc-badge oc-badge--risk-${e.severity}">${this.#capitalise(e.severity)}</span></td>
                    <td data-label="Assigned Team">${this.#escape(e.assigned_team)}</td>
                    <td data-label="Assigned User">${e.assigned_user_display_name ?? (e.assigned_user_id ? `User #${e.assigned_user_id}` : '—')}</td>
                    <td data-label="Due Date">${e.due_at ? this.#formatDate(e.due_at) : '—'}</td>
                    <td data-label="Status"><span class="oc-badge oc-badge--status-${e.status}">${this.#capitalise(e.status)}</span></td>
                    <td data-label="Actions" style="text-align:right;white-space:nowrap;">
                        ${actions.includes('acknowledge') ? `<button class="oc-btn oc-btn--ghost oc-btn--sm" data-ack="${e.id}">Acknowledge</button>` : ''}
                        ${actions.includes('resolve') ? `<button class="oc-btn oc-btn--primary oc-btn--sm" data-resolve="${e.id}">Resolve</button>` : ''}
                    </td>
                </tr>
            `;
        }

        #bindRowActions() {
            document.querySelectorAll('[data-ack]').forEach(btn =>
                btn.addEventListener('click', () => this.#acknowledge(btn.dataset.ack, btn))
            );
            document.querySelectorAll('[data-resolve]').forEach(btn =>
                btn.addEventListener('click', () => this.#promptResolve(btn.dataset.resolve, btn))
            );
        }

        async #acknowledge(id, btn) {
            btn.disabled = true;
            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/escalations/${id}/acknowledge`, {
                    method: 'POST',
                    headers: { Authorization: `Bearer ${this.#token()}`, Accept: 'application/json' },
                });
                if (!res.ok) throw new Error('failed');
                this.#showToast('Acknowledged');
                this.load();
            } catch {
                this.#showToast('Could not acknowledge', false);
                btn.disabled = false;
            }
        }

        async #promptResolve(id, btn) {
            const resolution = prompt('Resolution (e.g. "cleared", "confirmed_violation")');
            if (!resolution) return;
            const notes = prompt('Resolution notes (optional)') || undefined;

            btn.disabled = true;
            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/escalations/${id}/resolve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ resolution, notes }),
                });
                if (!res.ok) throw new Error('failed');
                this.#showToast('Escalation resolved');
                this.load();
            } catch {
                this.#showToast('Could not resolve escalation', false);
                btn.disabled = false;
            }
        }

        #capitalise(s) { return (s ?? '').replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase()); }
        #formatDate(iso) { return new Date(iso).toLocaleString(undefined, { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }); }
        #escape(str) { const d = document.createElement('div'); d.textContent = str ?? ''; return d.innerHTML; }
        #showToast(msg, ok = true) {
            const el = document.getElementById('status-toast');
            el.textContent = msg; el.style.background = ok ? 'var(--navy)' : 'var(--red)';
            el.style.opacity = '1'; setTimeout(() => { el.style.opacity = '0'; }, 3000);
        }
    }

    new EscalationQueueManager(SITE, () => localStorage.getItem('oc_token') || '');
</script>
@endsection