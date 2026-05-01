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

<div id="action-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeActionModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:400px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 id="action-modal-title"
            style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">Update
            Status</h3>
        <p id="action-modal-desc" style="font-size:.85rem;color:var(--slate);margin-bottom:20px;"></p>

        <div class="oc-form-group">
            <label class="oc-label" for="action-reason">Reason for change</label>
            <textarea class="oc-input" id="action-reason" rows="3"
                      placeholder="Explain why this status is changing..."></textarea>
        </div>

        <div id="action-modal-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

        <div style="display:flex;gap:10px;">
            <button onclick="closeActionModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button id="action-confirm-btn" class="oc-btn" style="flex:1;">Confirm</button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    class ContributorsManager {
        #site;
        #token;

        #state = {
            query: '',
            status: '',
            page: 1,
            perPage: 25,
            results: [],
            actionId: null,
            actionType: null,
            pendingRole: null,
        };

        #debounceTimer = null;

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
        }

        init() {
            document.getElementById('search-input').addEventListener('input', (e) => {
                clearTimeout(this.#debounceTimer);
                this.#state.page = 1;
                this.#debounceTimer = setTimeout(() => {
                    this.#state.query = e.target.value.trim();
                    this.#load();
                }, 320);
            });
            this.#load();
        }

        setStatusFilter(status, btn) {
            this.#state.status = status;
            this.#state.page = 1;
            document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('filter-pill--active'));
            btn.classList.add('filter-pill--active');
            this.#renderTable(this.#state.results);
        }

        async #load() {
            this.#showState('loading');
            const params = new URLSearchParams({per_page: this.#state.perPage, page: this.#state.page});
            if (this.#state.query) params.set('q', this.#state.query);

            try {
                const res = await fetch(
                    `/api/${this.#site}/open-collab/admin/contributors?${params}`,
                    {headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'}}
                );
                if (!res.ok) {
                    this.#showState('error');
                    return;
                }
                const data = await res.json();
                const items = Array.isArray(data) ? data : (Array.isArray(data.data) ? data.data : []);
                this.#state.results = items;
                this.#renderTable(items);
                this.#renderPagination(data);
            } catch {
                this.#showState('error');
            }
        }

        #renderTable(items) {
            const filtered = this.#filterByStatus(items);
            const q = this.#state.query;
            document.getElementById('results-title').textContent = q ? `Results for "${q}"` : 'All Contributors';
            document.getElementById('results-count').textContent = filtered.length;

            if (!filtered.length) {
                this.#showState('empty');
                document.getElementById('empty-message').textContent = q ? `No contributors matching "${q}"` : 'No contributors yet';
                document.getElementById('empty-sub').textContent = q ? 'Try a different search term.' : 'Send invitations to add contributors.';
                return;
            }

            document.getElementById('contributors-tbody').innerHTML = filtered.map(c => this.#buildRow(c)).join('');
            this.#showState('table');
        }

        #filterByStatus(items) {
            if (!this.#state.status) return items;
            return items.filter(c => this.#state.status === 'active' ? c.is_active !== false : c.is_active === false);
        }

        #buildRow(c) {
            const isActive = c.is_active !== false;
            const initial = (c.name || 'C').charAt(0).toUpperCase();
            const joined = c.created_at
                ? new Date(c.created_at).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})
                : '–';
            const statusBadge = isActive
                ? '<span class="oc-badge oc-badge--published">Active</span>'
                : '<span class="oc-badge oc-badge--revoked">Inactive</span>';
            const actionBtn = isActive
                ? `<button onclick="manager.openActionModal(${c.id},'deactivate')" class="oc-btn oc-btn--ghost oc-btn--sm" style="border-color:#fecaca;color:var(--red);">Deactivate</button>`
                : `<button onclick="manager.openActionModal(${c.id},'reactivate')" class="oc-btn oc-btn--ghost oc-btn--sm" style="border-color:#bbf7d0;color:var(--green);">Reactivate</button>`;

            return `<tr id="contrib-row-${c.id}">
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:32px;height:32px;border-radius:50%;background:var(--navy);display:grid;place-items:center;font-weight:700;font-size:.8rem;color:var(--amber);flex-shrink:0;">${this.#esc(initial)}</div>
                    <div>
                        <div style="font-weight:500;color:var(--navy);">${this.#esc(c.name || '–')}</div>
                        <div style="font-size:.75rem;color:var(--slate);">${this.#esc(c.email || '')}</div>
                    </div>
                </div>
            </td>
            <td>${statusBadge}</td>
            <td>
                <select class="oc-input" style="font-size:.82rem;padding:4px 8px;height:auto;width:auto;"
                        onchange="manager.openActionModal(${c.id},'role',this.value)">
                    <option value="contributor" ${c.role === 'contributor' ? 'selected' : ''}>Contributor</option>
                    <option value="editor" ${c.role === 'editor' ? 'selected' : ''}>Editor</option>
                    <option value="admin" ${c.role === 'admin' ? 'selected' : ''}>Admin</option>
                </select>
            </td>
            <td style="font-size:.78rem;color:var(--slate);">${joined}</td>
            <td style="text-align:right;">
                <div style="display:flex;gap:6px;justify-content:flex-end;">
                    <a href="/${this.#esc(this.#site)}/open-collab/admin/contributors/${c.id}" class="oc-btn oc-btn--ghost oc-btn--sm">View</a>
                    ${actionBtn}
                </div>
            </td>
        </tr>`;
        }

        #renderPagination(data) {
            const bar = document.getElementById('pagination-bar');
            if (Array.isArray(data) || !data.total) {
                bar.style.display = 'none';
                return;
            }
            const total = data.total ?? 0;
            const totalPages = Math.ceil(total / this.#state.perPage);
            if (totalPages <= 1) {
                bar.style.display = 'none';
                return;
            }

            bar.style.display = 'flex';
            document.getElementById('pagination-info').textContent =
                `Showing ${Math.min((this.#state.page - 1) * this.#state.perPage + 1, total)}–${Math.min(this.#state.page * this.#state.perPage, total)} of ${total}`;
            document.getElementById('prev-btn').disabled = this.#state.page <= 1;
            document.getElementById('next-btn').disabled = this.#state.page >= totalPages;

            const pills = document.getElementById('page-pills');
            pills.innerHTML = '';
            const start = Math.max(1, this.#state.page - 2);
            const end = Math.min(totalPages, this.#state.page + 2);
            for (let p = start; p <= end; p++) {
                const btn = document.createElement('button');
                btn.textContent = p;
                btn.className = 'oc-btn oc-btn--ghost oc-btn--sm';
                if (p === this.#state.page) {
                    btn.style.background = 'var(--navy)';
                    btn.style.color = '#fff';
                    btn.style.borderColor = 'var(--navy)';
                }
                btn.addEventListener('click', () => {
                    this.#state.page = p;
                    this.#load();
                });
                pills.appendChild(btn);
            }
        }

        changePage(delta) {
            this.#state.page += delta;
            this.#load();
        }

        async sendInvite() {
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
                const res = await fetch(`/api/${this.#site}/open-collab/invitations`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json'
                    },
                    body: JSON.stringify({email}),
                });
                const data = await res.json();
                if (res.ok) {
                    document.getElementById('invite-email').value = '';
                    this.#showToast(`✓ Invitation sent to ${email}`);
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

        openActionModal(id, type, role = null) {
            this.#state.actionId = id;
            this.#state.actionType = type;
            this.#state.pendingRole = role;

            const title = document.getElementById('action-modal-title');
            const desc = document.getElementById('action-modal-desc');
            const btn = document.getElementById('action-confirm-btn');

            if (type === 'role') {
                title.textContent = 'Change Contributor Role';
                desc.textContent = `Changing role to "${role}". Please provide a reason.`;
                btn.className = 'oc-btn oc-btn--amber';
                btn.textContent = 'Update Role';
            } else {
                const isDeactivate = type === 'deactivate';
                title.textContent = isDeactivate ? 'Deactivate Account' : 'Reactivate Account';
                desc.textContent = isDeactivate
                    ? 'They will lose access immediately. Please provide a reason.'
                    : 'Access will be restored. Please provide a reason.';
                btn.className = isDeactivate ? 'oc-btn oc-btn--danger' : 'oc-btn oc-btn--amber';
                btn.textContent = isDeactivate ? 'Deactivate account' : 'Reactivate account';
            }

            btn.onclick = () => this.submitAction();
            document.getElementById('action-modal').style.display = 'grid';
            document.getElementById('action-reason').focus();
        }

        closeActionModal() {
            document.getElementById('action-modal').style.display = 'none';
            document.getElementById('action-reason').value = '';
            document.getElementById('action-modal-errors').style.display = 'none';
            this.#renderTable(this.#state.results);
        }

        async submitAction() {
            const reason = document.getElementById('action-reason').value.trim();
            const errBox = document.getElementById('action-modal-errors');
            const btn = document.getElementById('action-confirm-btn');
            errBox.style.display = 'none';

            if (!reason) {
                errBox.textContent = 'Please provide a reason.';
                errBox.style.display = 'block';
                return;
            }

            btn.disabled = true;
            const originalText = btn.textContent;
            btn.innerHTML = '<div class="oc-spinner"></div> Processing…';

            const {actionId, actionType, pendingRole} = this.#state;
            const url = actionType === 'role'
                ? `/api/${this.#site}/open-collab/admin/contributors/${actionId}/role`
                : `/api/${this.#site}/open-collab/admin/contributors/${actionId}/${actionType}`;
            const body = actionType === 'role'
                ? JSON.stringify({role: pendingRole, reason})
                : JSON.stringify({reason});

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json'
                    },
                    body,
                });

                if (res.ok) {
                    this.closeActionModal();
                    this.#showToast('Update successful');
                    const c = this.#state.results.find(x => x.id === actionId);
                    if (c) {
                        if (actionType === 'role') c.role = pendingRole;
                        else c.is_active = actionType === 'reactivate';
                    }
                    this.#renderTable(this.#state.results);
                } else {
                    const data = await res.json();
                    errBox.textContent = data.error || 'Request failed.';
                    errBox.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch {
                errBox.textContent = 'Network error.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }

        #showState(state) {
            const map = {
                loading: 'contributors-loading',
                empty: 'contributors-empty',
                error: 'contributors-error',
                table: 'contributors-table',
            };
            Object.entries(map).forEach(([k, id]) => {
                document.getElementById(id).style.display = k === state ? 'block' : 'none';
            });
            if (state !== 'table') document.getElementById('pagination-bar').style.display = 'none';
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

    const manager = new ContributorsManager(
        SITE,
        () => localStorage.getItem('oc_token') || ''
    );
    document.addEventListener('DOMContentLoaded', () => manager.init());
</script>
@endsection