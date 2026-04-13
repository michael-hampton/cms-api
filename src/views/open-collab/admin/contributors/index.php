@section('logic')
<?php
/**
 * Template: open-collab/admin/contributors/index.php
 * Variables:
 *   $site        — string
 *   $currentUser — AuthenticatedUser
 *
 * All data is loaded via JS/API — no server-side $contributors variable needed.
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

<!-- Search + Invite bar -->
<div class="oc-card" style="margin-bottom:20px;padding:16px 20px;">
    <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <div style="position:relative;flex:1;min-width:200px;">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16"
                 style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--slate-light);pointer-events:none;">
                <path fill-rule="evenodd"
                      d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                      clip-rule="evenodd"/>
            </svg>
            <input class="oc-input" type="text" id="search-input"
                   placeholder="Search by name or email…"
                   style="padding-left:38px;"
                   autocomplete="off">
        </div>
        <!-- Status filter pills -->
        <div style="display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap;" id="status-filters">
            <button class="filter-pill filter-pill--active" data-status=""
                    onclick="setStatusFilter('', this)">All
            </button>
            <button class="filter-pill" data-status="active"
                    onclick="setStatusFilter('active', this)">Active
            </button>
            <button class="filter-pill" data-status="inactive"
                    onclick="setStatusFilter('inactive', this)">Inactive
            </button>
        </div>
    </div>
</div>

<style>
    .filter-pill {
        padding: 5px 14px;
        border-radius: 20px;
        border: 1.5px solid var(--border);
        background: #fff;
        font-size: .78rem;
        font-weight: 500;
        color: var(--slate);
        cursor: pointer;
        transition: background .15s, color .15s, border-color .15s;
        white-space: nowrap;
    }

    .filter-pill:hover {
        border-color: var(--navy);
        color: var(--navy);
    }

    .filter-pill--active {
        background: var(--navy);
        color: #fff;
        border-color: var(--navy);
    }
</style>

<!-- Invite new contributor -->
<div class="oc-card" style="margin-bottom:20px;">
    <div class="oc-card__header">
        <span class="oc-card__title">Invite Contributor</span>
    </div>
    <div class="oc-card__body">
        <div id="invite-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div class="oc-form-group" style="flex:1;min-width:200px;margin-bottom:0;">
                <label class="oc-label" for="invite-email">Email address</label>
                <input class="oc-input" type="email" id="invite-email" placeholder="contributor@example.com">
            </div>
            <button onclick="sendInvite()" class="oc-btn oc-btn--amber" id="invite-btn">
                Send invitation
            </button>
        </div>
    </div>
</div>

<!-- Results -->
<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title" id="results-title">Contributors</span>
        <span id="results-count"
              style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                     padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
    </div>

    <!-- Loading state -->
    <div id="contributors-loading" style="padding:48px 24px;text-align:center;color:var(--slate);">
        <div class="oc-spinner" style="margin:0 auto 12px;"></div>
        Loading contributors…
    </div>

    <!-- Empty state -->
    <div id="contributors-empty" style="display:none;padding:48px 24px;text-align:center;color:var(--slate);">
        <svg viewBox="0 0 20 20" fill="currentColor" width="32"
             style="opacity:.2;display:block;margin:0 auto 12px;">
            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
        </svg>
        <div style="font-weight:500;margin-bottom:6px;" id="empty-message">No contributors found</div>
        <div style="font-size:.85rem;" id="empty-sub">Send invitations to add contributors.</div>
    </div>

    <!-- Error state -->
    <div id="contributors-error"
         style="display:none;padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">
        Failed to load contributors.
        <button onclick="loadContributors()" class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-left:8px;">Retry
        </button>
    </div>

    <!-- Table -->
    <div id="contributors-table" style="display:none;overflow-x:auto;">
        <table class="oc-table">
            <thead>
            <tr>
                <th>Contributor</th>
                <th>Status</th>
                <th>Role</th>
                <th>Joined</th>
                <th></th>
            </tr>
            </thead>
            <tbody id="contributors-tbody"></tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="pagination-bar"
         style="display:none;padding:12px 20px;border-top:1px solid var(--border);
                display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <span id="pagination-info" style="font-size:.78rem;color:var(--slate);"></span>
        <div style="display:flex;gap:6px;">
            <button id="prev-btn" onclick="changePage(-1)"
                    class="oc-btn oc-btn--ghost oc-btn--sm" disabled>← Previous
            </button>
            <span id="page-pills" style="display:flex;gap:4px;"></span>
            <button id="next-btn" onclick="changePage(1)"
                    class="oc-btn oc-btn--ghost oc-btn--sm">Next →
            </button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    // ── State ─────────────────────────────────────────────────────────────────
    let currentQuery = '';
    let currentStatus = '';   // '' | 'active' | 'inactive'
    let currentPage = 1;
    const PER_PAGE = 25;
    let debounceTimer = null;
    let lastResults = [];   // cached for client-side status filter fallback

    // ── Bootstrap ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('search-input').addEventListener('input', function () {
            clearTimeout(debounceTimer);
            currentPage = 1;
            debounceTimer = setTimeout(() => {
                currentQuery = this.value.trim();
                loadContributors();
            }, 320);
        });

        loadContributors();
    });

    // ── Filter pills ──────────────────────────────────────────────────────────
    function setStatusFilter(status, btn) {
        currentStatus = status;
        currentPage = 1;
        document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
        btn.classList.add('filter-pill--active');
        renderTable(lastResults);   // re-filter client-side (no extra API call needed)
    }

    // ── Load from API ─────────────────────────────────────────────────────────
    async function loadContributors() {
        showState('loading');

        const params = new URLSearchParams({per_page: PER_PAGE, page: currentPage});
        if (currentQuery) params.set('q', currentQuery);

        try {
            const res = await fetch(
                `/api/${SITE}/open-collab/admin/contributors?${params}`,
                {headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'}}
            );

            if (!res.ok) {
                showState('error');
                return;
            }

            const data = await res.json();
            // Support both flat array and paginated { data: [...], total, ... }
            const items = Array.isArray(data)
                ? data
                : (Array.isArray(data.data) ? data.data : []);

            lastResults = items;
            renderTable(items);
            renderPagination(data);
        } catch {
            showState('error');
        }
    }

    // ── Render table with optional client-side status filter ──────────────────
    function renderTable(items) {
        const filtered = filterByStatus(items);
        const count = filtered.length;

        const q = currentQuery;
        document.getElementById('results-title').textContent =
            q ? `Results for "${q}"` : 'All Contributors';
        document.getElementById('results-count').textContent = count;

        if (!count) {
            showState('empty');
            document.getElementById('empty-message').textContent =
                q ? `No contributors matching "${q}"` : 'No contributors yet';
            document.getElementById('empty-sub').textContent =
                q ? 'Try a different search term.' : 'Send invitations to add contributors.';
            return;
        }

        const tbody = document.getElementById('contributors-tbody');
        tbody.innerHTML = filtered.map(c => buildRow(c)).join('');
        showState('table');
    }

    function filterByStatus(items) {
        if (!currentStatus) return items;
        return items.filter(c =>
            currentStatus === 'active'
                ? (c.is_active !== false)
                : (c.is_active === false)
        );
    }

    function buildRow(c) {
        const isActive = c.is_active !== false;
        const initial = (c.name || 'C').charAt(0).toUpperCase();
        const joined = c.created_at
            ? new Date(c.created_at).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})
            : '–';

        const statusBadge = isActive
            ? '<span class="oc-badge oc-badge--published">Active</span>'
            : '<span class="oc-badge oc-badge--revoked">Inactive</span>';

        const actionBtn = isActive
            ? `<button onclick="deactivate(${c.id}, this)"
                       class="oc-btn oc-btn--ghost oc-btn--sm"
                       style="border-color:#fecaca;color:var(--red);">Deactivate</button>`
            : `<button onclick="reactivate(${c.id}, this)"
                       class="oc-btn oc-btn--ghost oc-btn--sm"
                       style="border-color:#bbf7d0;color:var(--green);">Reactivate</button>`;

        return `<tr id="contrib-row-${c.id}">
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:32px;height:32px;border-radius:50%;background:var(--navy);
                                display:grid;place-items:center;font-weight:700;font-size:.8rem;
                                color:var(--amber);flex-shrink:0;">${escHtml(initial)}</div>
                    <div>
                        <div style="font-weight:500;color:var(--navy);">${escHtml(c.name || '–')}</div>
                        <div style="font-size:.75rem;color:var(--slate);">${escHtml(c.email || '')}</div>
                    </div>
                </div>
            </td>
            <td>${statusBadge}</td>
            <td style="font-size:.82rem;color:var(--slate);">${escHtml(c.role || 'contributor')}</td>
            <td style="font-size:.78rem;color:var(--slate);">${joined}</td>
            <td style="text-align:right;">
                <div style="display:flex;gap:6px;justify-content:flex-end;">
                    <a href="/${escHtml(SITE)}/open-collab/admin/contributors/${c.id}"
                       class="oc-btn oc-btn--ghost oc-btn--sm">View</a>
                    ${actionBtn}
                </div>
            </td>
        </tr>`;
    }

    // ── Pagination ────────────────────────────────────────────────────────────
    function renderPagination(data) {
        // If the API returned flat array or no pagination metadata, hide pagination
        if (Array.isArray(data) || !data.total) {
            document.getElementById('pagination-bar').style.display = 'none';
            return;
        }

        const total = data.total ?? 0;
        const totalPages = Math.ceil(total / PER_PAGE);

        if (totalPages <= 1) {
            document.getElementById('pagination-bar').style.display = 'none';
            return;
        }

        document.getElementById('pagination-bar').style.display = 'flex';
        document.getElementById('pagination-info').textContent =
            `Showing ${Math.min((currentPage - 1) * PER_PAGE + 1, total)}–${Math.min(currentPage * PER_PAGE, total)} of ${total}`;

        document.getElementById('prev-btn').disabled = currentPage <= 1;
        document.getElementById('next-btn').disabled = currentPage >= totalPages;

        // Page number pills (show up to 5 around current)
        const pills = document.getElementById('page-pills');
        pills.innerHTML = '';
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(totalPages, currentPage + 2);
        for (let p = start; p <= end; p++) {
            const btn = document.createElement('button');
            btn.textContent = p;
            btn.className = 'oc-btn oc-btn--ghost oc-btn--sm';
            if (p === currentPage) {
                btn.style.background = 'var(--navy)';
                btn.style.color = '#fff';
                btn.style.borderColor = 'var(--navy)';
            }
            btn.addEventListener('click', () => {
                currentPage = p;
                loadContributors();
            });
            pills.appendChild(btn);
        }
    }

    function changePage(delta) {
        currentPage += delta;
        loadContributors();
    }

    // ── UI state helpers ──────────────────────────────────────────────────────
    function showState(state) {
        const states = {
            loading: 'contributors-loading', empty: 'contributors-empty',
            error: 'contributors-error', table: 'contributors-table'
        };
        Object.entries(states).forEach(([k, id]) => {
            document.getElementById(id).style.display = k === state ? (k === 'table' ? 'block' : 'block') : 'none';
        });
        if (state !== 'table') {
            document.getElementById('pagination-bar').style.display = 'none';
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────
    async function sendInvite() {
        const email = document.getElementById('invite-email').value.trim();
        const errBox = document.getElementById('invite-errors');
        const btn = document.getElementById('invite-btn');
        errBox.style.display = 'none';

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errBox.textContent = 'A valid email address is required.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner oc-spinner--dark"></div> Sending…';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/invitations`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN()}`,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({email}),
            });
            const data = await res.json();
            if (res.ok) {
                document.getElementById('invite-email').value = '';
                showToast('✓ Invitation sent to ' + email);
            } else {
                errBox.textContent = data.error || data.message || 'Failed to send invitation.';
                errBox.style.display = 'block';
            }
        } catch {
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Send invitation';
        }
    }

    async function deactivate(id, btn) {
        if (!confirm('Deactivate this contributor? They will lose access immediately.')) return;
        btn.disabled = true;
        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/contributors/${id}/deactivate`, {
                method: 'POST',
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });
            if (res.ok) {
                showToast('Contributor deactivated');
                // Patch local cache and re-render without API call
                const c = lastResults.find(x => x.id === id);
                if (c) c.is_active = false;
                renderTable(lastResults);
            } else {
                showToast('Deactivation failed', false);
                btn.disabled = false;
            }
        } catch {
            showToast('Network error', false);
            btn.disabled = false;
        }
    }

    async function reactivate(id, btn) {
        btn.disabled = true;
        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/contributors/${id}/reactivate`, {
                method: 'POST',
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });
            if (res.ok) {
                showToast('Contributor reactivated');
                const c = lastResults.find(x => x.id === id);
                if (c) c.is_active = true;
                renderTable(lastResults);
            } else {
                showToast('Reactivation failed', false);
                btn.disabled = false;
            }
        } catch {
            showToast('Network error', false);
            btn.disabled = false;
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────
    function escHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showToast(msg, ok = true) {
        const el = document.getElementById('status-toast');
        el.textContent = msg;
        el.style.background = ok ? 'var(--navy)' : 'var(--red)';
        el.style.opacity = '1';
        setTimeout(() => {
            el.style.opacity = '0';
        }, 2800);
    }
</script>
@endsection