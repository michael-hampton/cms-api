@section('logic')
<?php
/**
 * Template: open-collab/admin/violations/index.php
 * Variables:
 *   $site        — string
 *   $currentUser — AuthenticatedUser
 *
 * Data is loaded client-side via ViolationController::index and ::resolve.
 * The contributor endpoint GET /admin/contributors/{id}/violations is used
 * once a contributor is selected from the search results.
 */
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<!-- Resolve modal -->
<div id="resolve-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeResolveModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:440px;width:92%;
                box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.15rem;color:var(--navy);margin-bottom:6px;">
            Resolve violation
        </h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            Resolving will lift any associated suspension or ban if no other active violations remain.
        </p>
        <input type="hidden" id="resolve-violation-id">
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="resolve-notes">Resolution notes</label>
            <textarea class="oc-textarea" id="resolve-notes" rows="3"
                      placeholder="Optional notes…" style="min-height:72px;"></textarea>
        </div>
        <div id="resolve-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button onclick="closeResolveModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitResolve()" class="oc-btn oc-btn--primary" style="flex:1;" id="resolve-confirm-btn">
                Resolve
            </button>
        </div>
    </div>
</div>

<!-- Filter / search bar -->
<div class="oc-card" style="margin-bottom:20px;padding:16px 20px;">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div style="position:relative;flex:1;min-width:200px;">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16"
                 style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--slate-light);pointer-events:none;">
                <path fill-rule="evenodd"
                      d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                      clip-rule="evenodd"/>
            </svg>
            <input class="oc-input" type="text" id="search-input"
                   placeholder="Search by contributor ID, type or reason…"
                   style="padding-left:38px;"
                   autocomplete="off">
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button class="filter-pill filter-pill--active" onclick="setStatusFilter('all', this)">All</button>
            <button class="filter-pill" onclick="setStatusFilter('open', this)">Open</button>
            <button class="filter-pill" onclick="setStatusFilter('resolved', this)">Resolved</button>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button class="sev-pill sev-pill--active" onclick="setSeverityFilter('all', this)">All severities</button>
            <button class="sev-pill" onclick="setSeverityFilter('high', this)" style="color:#ef4444;">High</button>
            <button class="sev-pill" onclick="setSeverityFilter('medium', this)" style="color:#f97316;">Medium</button>
            <button class="sev-pill" onclick="setSeverityFilter('low', this)" style="color:#eab308;">Low</button>
        </div>
    </div>
</div>

<style>
    .filter-pill, .sev-pill {
        padding: 4px 12px;
        border-radius: 20px;
        border: 1.5px solid var(--border);
        background: #fff;
        font-size: .75rem;
        font-weight: 500;
        color: var(--slate);
        cursor: pointer;
        transition: background .15s, color .15s, border-color .15s;
    }

    .filter-pill:hover, .sev-pill:hover {
        border-color: var(--navy);
    }

    .filter-pill--active {
        background: var(--navy);
        color: #fff !important;
        border-color: var(--navy);
    }

    .sev-pill--active {
        background: var(--navy);
        color: #fff !important;
        border-color: var(--navy);
    }
</style>

<!-- Results card -->
<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title" id="results-title">All Violations</span>
        <span id="results-count"
              style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                     padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
    </div>

    <div id="violations-loading" style="padding:48px 24px;text-align:center;color:var(--slate);">
        <div class="oc-spinner" style="margin:0 auto 12px;"></div>
        Loading violations…
    </div>

    <div id="violations-empty" style="display:none;padding:64px 24px;text-align:center;color:var(--slate);">
        <svg viewBox="0 0 20 20" fill="currentColor" width="36"
             style="opacity:.15;display:block;margin:0 auto 16px;color:var(--green);">
            <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"/>
        </svg>
        <div style="font-size:1.05rem;font-weight:600;color:var(--navy);" id="empty-message">No violations recorded
        </div>
        <div style="font-size:.875rem;color:var(--slate);margin-top:4px;" id="empty-sub">Contributors are behaving
            well.
        </div>
    </div>

    <div id="violations-error"
         style="display:none;padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">
        Failed to load violations.
        <button onclick="loadViolations()" class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-left:8px;">Retry
        </button>
    </div>

    <div id="violations-table-wrap" style="display:none;overflow-x:auto;">
        <table class="oc-table">
            <thead>
            <tr>
                <th>Contributor</th>
                <th>Type</th>
                <th>Severity</th>
                <th>Action</th>
                <th>Date</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody id="violations-tbody"></tbody>
        </table>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    class ViolationsManager {
        #site;
        #token;
        #state = {
            all: [],
            statusFilter: 'all',
            severityFilter: 'all',
            query: '',
            pendingResolveId: null,
        };
        #debounceTimer = null;

        static #SEV_COLORS = {high: '#ef4444', medium: '#f97316', low: '#eab308'};
        static #ACT_BADGES = {
            warning: 'oc-badge--waiting-approval',
            suspension: 'oc-badge--revoked',
            ban: 'oc-badge--revoked'
        };
        static #ACT_LABELS = {warning: 'Warning', suspension: 'Suspended', ban: 'Banned'};

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
        }

        init() {
            document.getElementById('search-input').addEventListener('input', (e) => {
                clearTimeout(this.#debounceTimer);
                this.#debounceTimer = setTimeout(() => {
                    this.#state.query = e.target.value.trim().toLowerCase();
                    this.#render();
                }, 300);
            });
            this.#load();
        }

        setStatusFilter(status, btn) {
            this.#state.statusFilter = status;
            document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
            btn.classList.add('filter-pill--active');
            this.#render();
        }

        setSeverityFilter(sev, btn) {
            this.#state.severityFilter = sev;
            document.querySelectorAll('.sev-pill').forEach(b => b.classList.remove('sev-pill--active'));
            btn.classList.add('sev-pill--active');
            this.#render();
        }

        async #load() {
            this.#showState('loading');
            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/violations`, {
                    headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
                });
                if (!res.ok) {
                    this.#showState('error');
                    return;
                }
                const data = await res.json();
                this.#state.all = Array.isArray(data) ? data : (data.data ?? []);
                this.#render();
            } catch {
                this.#showState('error');
            }
        }

        #render() {
            let filtered = this.#state.all;
            if (this.#state.statusFilter === 'open') filtered = filtered.filter(v => !v.resolved_at);
            if (this.#state.statusFilter === 'resolved') filtered = filtered.filter(v => v.resolved_at);
            if (this.#state.severityFilter !== 'all') filtered = filtered.filter(v => v.severity === this.#state.severityFilter);
            if (this.#state.query) {
                filtered = filtered.filter(v =>
                    String(v.user_id).includes(this.#state.query) ||
                    (v.type ?? '').toLowerCase().includes(this.#state.query) ||
                    (v.reason ?? '').toLowerCase().includes(this.#state.query)
                );
            }

            document.getElementById('results-count').textContent = filtered.length;
            document.getElementById('results-title').textContent =
                this.#state.statusFilter === 'all' ? 'All Violations' : `${this.#cap(this.#state.statusFilter)} Violations`;

            if (!filtered.length) {
                this.#showState('empty');
                document.getElementById('empty-message').textContent =
                    this.#state.query ? `No violations matching "${this.#state.query}"` : 'No violations recorded';
                document.getElementById('empty-sub').textContent =
                    this.#state.query ? 'Try a different search term.' : 'Contributors are behaving well.';
                return;
            }

            const rows = filtered.map(v => {
                const isResolved = !!v.resolved_at;
                const severity = v.severity ?? 'low';
                const action = v.action_taken ?? 'warning';
                const sevColor = ViolationsManager.#SEV_COLORS[severity] ?? '#64748b';
                const actBadge = ViolationsManager.#ACT_BADGES[action] ?? 'oc-badge--draft';
                const actLabel = ViolationsManager.#ACT_LABELS[action] ?? this.#cap(action);
                const createdAt = v.created_at
                    ? new Date(v.created_at).toLocaleDateString('en-GB', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric'
                    })
                    : '—';
                const actionCell = isResolved
                    ? `<div style="display:flex;gap:6px;justify-content:flex-end;"><a href="/${this.#esc(this.#site)}/open-collab/admin/contributors/${v.user_id}/violations" class="oc-btn oc-btn--ghost oc-btn--sm">Profile</a></div>`
                    : `<div style="display:flex;gap:6px;justify-content:flex-end;"><a href="/${this.#esc(this.#site)}/open-collab/admin/contributors/${v.user_id}/violations" class="oc-btn oc-btn--ghost oc-btn--sm">Profile</a><button onclick="manager.openResolveModal(${v.id})" class="oc-btn oc-btn--primary oc-btn--sm">Resolve</button></div>`;

                return `<tr>
                <td><a href="/${this.#esc(this.#site)}/open-collab/admin/contributors/${v.user_id}" style="font-weight:500;color:var(--navy);text-decoration:none;">User #${v.user_id}</a></td>
                <td style="font-size:.82rem;color:var(--navy);">${this.#esc((v.type ?? '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()))}</td>
                <td><span style="display:inline-flex;align-items:center;gap:5px;font-size:.78rem;font-weight:600;color:${sevColor};"><span style="width:6px;height:6px;border-radius:50%;background:currentColor;"></span>${this.#cap(severity)}</span></td>
                <td><span class="oc-badge ${actBadge}">${actLabel}</span></td>
                <td style="font-size:.78rem;color:var(--slate);">${createdAt}</td>
                <td>${isResolved ? '<span class="oc-badge oc-badge--published">Resolved</span>' : '<span class="oc-badge oc-badge--draft">Open</span>'}</td>
                <td style="text-align:right;">${actionCell}</td>
            </tr>
            ${v.reason ? `<tr><td colspan="7" style="padding:0 16px 10px;font-size:.78rem;color:var(--slate);background:var(--cream-dark);"><strong>Reason:</strong> ${this.#esc(v.reason)}</td></tr>` : ''}`;
            });

            document.getElementById('violations-tbody').innerHTML = rows.join('');
            this.#showState('table');
        }

        openResolveModal(id) {
            this.#state.pendingResolveId = id;
            document.getElementById('resolve-notes').value = '';
            document.getElementById('resolve-errors').style.display = 'none';
            document.getElementById('resolve-modal').style.display = 'grid';
        }

        closeResolveModal() {
            this.#state.pendingResolveId = null;
            document.getElementById('resolve-modal').style.display = 'none';
        }

        async submitResolve() {
            const id = this.#state.pendingResolveId;
            const notes = document.getElementById('resolve-notes').value.trim();
            const errBox = document.getElementById('resolve-errors');
            const btn = document.getElementById('resolve-confirm-btn');
            errBox.style.display = 'none';
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div>';

            const res = await fetch(`/api/${this.#site}/open-collab/admin/violations/${id}/resolve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${this.#token()}`,
                    Accept: 'application/json'
                },
                body: JSON.stringify({notes: notes || undefined}),
            });

            if (res.ok) {
                this.closeResolveModal();
                this.#showToast('✓ Violation resolved');
                const v = this.#state.all.find(x => x.id === parseInt(id));
                if (v) {
                    v.resolved_at = new Date().toISOString();
                    v.resolution_notes = notes;
                }
                this.#render();
            } else {
                const data = await res.json();
                errBox.textContent = data.error || 'Failed to resolve.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Resolve';
            }
        }

        #showState(state) {
            document.getElementById('violations-loading').style.display = state === 'loading' ? 'block' : 'none';
            document.getElementById('violations-empty').style.display = state === 'empty' ? 'block' : 'none';
            document.getElementById('violations-error').style.display = state === 'error' ? 'block' : 'none';
            document.getElementById('violations-table-wrap').style.display = state === 'table' ? 'block' : 'none';
        }

        #cap(str) {
            return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
        }

        #esc(str) {
            if (str == null) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        #showToast(msg, ok = true) {
            const el = document.getElementById('status-toast');
            el.textContent = msg;
            el.style.background = ok ? 'var(--navy)' : 'var(--red)';
            el.style.opacity = '1';
            setTimeout(() => {
                el.style.opacity = '0';
            }, 2800);
        }
    }

    const manager = new ViolationsManager(SITE, () => localStorage.getItem('oc_token') || '');
    document.addEventListener('DOMContentLoaded', () => manager.init());
    const setStatusFilter = (status, btn) => manager.setStatusFilter(status, btn);
    const submitResolve = () => manager.submitResolve();
    const setSeverityFilter = (status, btn) => manager.setSeverityFilter(status, btn);

</script>
@endsection