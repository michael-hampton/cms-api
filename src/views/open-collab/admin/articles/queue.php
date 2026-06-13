@section('logic')
<?php
/**
 * Template: open-collab/admin/moderation/index.php
 * Variables:
 *   $site        — string
 *   $currentUser — AuthenticatedUser
 *
 * All data is loaded client-side via the moderation queue API:
 *   GET /api/{site}/open-collab/admin/moderation
 *   POST /api/{site}/open-collab/admin/moderation/{id}/claim
 *   POST /api/{site}/open-collab/admin/moderation/{id}/release
 *
 * Permission-aware buttons rely on `available_actions` returned per
 * queue entry by ModerationQueueEntryResource — the server is the
 * source of truth, this view never hardcodes role checks.
 */

$pageTitle = 'Moderation Queue';
$activeNav = 'moderation';
$breadcrumbs = [['label' => 'Moderation Queue']];
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<!-- Filters -->
<div class="oc-card" style="padding:16px 20px;margin-bottom:14px;">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">

        <div class="oc-form-group" style="margin:0;min-width:160px;">
            <label class="oc-label" for="mq-filter-status">Status</label>
            <select class="oc-select" id="mq-filter-status">
                <option value="">All</option>
                <option value="queued">Queued</option>
                <option value="claimed">Claimed</option>
                <option value="in_review">In Review</option>
                <option value="changes_requested">Changes Requested</option>
                <option value="escalated">Escalated</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <div class="oc-form-group" style="margin:0;min-width:160px;">
            <label class="oc-label" for="mq-filter-risk">Risk Level</label>
            <select class="oc-select" id="mq-filter-risk">
                <option value="">All</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
        </div>

        <div class="oc-form-group" style="margin:0;min-width:180px;">
            <label class="oc-label" for="mq-filter-assignment">Assignment</label>
            <select class="oc-select" id="mq-filter-assignment">
                <option value="">All</option>
                <option value="mine">Assigned to me</option>
                <option value="unassigned">Unassigned</option>
                <option value="others">Assigned to others</option>
            </select>
        </div>

        <div class="oc-form-group" style="margin:0;min-width:140px;">
            <label class="oc-label" for="mq-filter-content-type">Content Type</label>
            <select class="oc-select" id="mq-filter-content-type">
                <option value="">All</option>
                <option value="article" selected>Article</option>
                <option value="gallery" disabled>Gallery</option>
                <option value="video" disabled>Video</option>
                <option value="other" disabled>Other</option>
            </select>
        </div>

        <div class="oc-form-group" style="margin:0;flex:1;min-width:220px;">
            <label class="oc-label" for="mq-search">Search</label>
            <input type="text" class="oc-input" id="mq-search"
                   placeholder="Title, contributor, or page ID…">
        </div>

        <button class="oc-btn oc-btn--primary oc-btn--sm" id="mq-apply-filters">Apply</button>
        <button class="oc-btn oc-btn--ghost oc-btn--sm" id="mq-reset-filters">Reset</button>
    </div>
</div>

<!-- Queue table -->
<div class="oc-card" style="overflow:hidden;">
    <table class="oc-table" style="width:100%;">
        <thead>
        <tr>
            <th>Status</th>
            <th>Risk</th>
            <th>Title</th>
            <th>Contributor</th>
            <th>Submitted</th>
            <th>Assigned</th>
            <th>Priority</th>
            <th>SLA</th>
            <th style="text-align:right;">Actions</th>
        </tr>
        </thead>
        <tbody id="mq-table-body">
        <tr id="mq-skeleton-row">
            <td colspan="9" style="padding:0;">
                <div style="padding:14px 20px;">
                    <div class="oc-skeleton" style="height:14px;width:60%;margin-bottom:8px;"></div>
                    <div class="oc-skeleton" style="height:14px;width:40%;"></div>
                </div>
            </td>
        </tr>
        </tbody>
    </table>

    <div id="mq-empty-state" style="display:none;padding:64px 24px;text-align:center;">
        <div style="font-size:1.05rem;font-weight:600;color:var(--navy);margin-bottom:6px;">
            No content currently requires moderation.
        </div>
    </div>

    <div id="mq-error-state" style="display:none;padding:48px 24px;text-align:center;">
        <div style="font-size:.95rem;color:var(--red);margin-bottom:14px;">
            Unable to load moderation queue.
        </div>
        <button class="oc-btn oc-btn--ghost oc-btn--sm" id="mq-retry-btn">Retry</button>
    </div>

    <!-- Pagination -->
    <div style="display:flex;justify-content:space-between;align-items:center;
                padding:12px 20px;border-top:1px solid var(--border);font-size:.8rem;color:var(--slate);">
        <span id="mq-pagination-summary"></span>
        <div style="display:flex;gap:8px;">
            <button class="oc-btn oc-btn--ghost oc-btn--sm" id="mq-prev-page">Previous</button>
            <button class="oc-btn oc-btn--ghost oc-btn--sm" id="mq-next-page">Next</button>
        </div>
    </div>
</div>

<style>
    .oc-badge--status-queued { background:#e0f2fe; color:#075985; }
    .oc-badge--status-claimed { background:#ede9fe; color:#5b21b6; }
    .oc-badge--status-in_review { background:#fef3c7; color:#92400e; }
    .oc-badge--status-changes_requested { background:#ffedd5; color:#9a3412; }
    .oc-badge--status-escalated { background:#fee2e2; color:#991b1b; }
    .oc-badge--status-approved { background:#dcfce7; color:#166534; }
    .oc-badge--status-rejected { background:#f1f5f9; color:#475569; }

    .oc-badge--risk-low { background:#f1f5f9; color:#475569; }
    .oc-badge--risk-medium { background:#fef3c7; color:#92400e; }
    .oc-badge--risk-high { background:#ffedd5; color:#9a3412; }
    .oc-badge--risk-critical { background:#fee2e2; color:#991b1b; }
    .oc-badge--risk-none { background:#f1f5f9; color:#94a3b8; }

    .oc-badge--sla-on_track { background:#dcfce7; color:#166534; }
    .oc-badge--sla-due_soon { background:#fef3c7; color:#92400e; }
    .oc-badge--sla-overdue { background:#fee2e2; color:#991b1b; }

    .oc-skeleton {
        background:linear-gradient(90deg, var(--cream-dark) 25%, #f0eee9 50%, var(--cream-dark) 75%);
        background-size:200% 100%;
        animation:oc-skeleton-shimmer 1.4s infinite;
        border-radius:4px;
    }
    @keyframes oc-skeleton-shimmer { 0% {background-position:200% 0;} 100% {background-position:-200% 0;} }

    @media (max-width: 768px) {
        .oc-table thead { display:none; }
        .oc-table, .oc-table tbody, .oc-table tr, .oc-table td { display:block; width:100%; }
        .oc-table tr { border:1px solid var(--border); border-radius:8px; margin-bottom:10px; padding:10px 14px; }
        .oc-table td { border:none; padding:4px 0; }
        .oc-table td[data-label]::before {
            content: attr(data-label);
            font-weight:600; color:var(--slate); display:block; font-size:.7rem; text-transform:uppercase;
        }
    }
</style>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const CURRENT_USER_ID = <?= (int)($currentUser->id ?? 0) ?>;

    class ModerationQueueManager {
        #site;
        #token;
        #page = 1;
        #perPage = 25;
        #filters = {};
        #pollHandle = null;

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
            this.#bindFilterControls();
            this.load();

            // Lightweight refresh so claims by other moderators surface
            // without a full reload — not full real-time, just a poll.
            this.#pollHandle = setInterval(() => this.load({ silent: true }), 30000);
        }

        #bindFilterControls() {
            document.getElementById('mq-apply-filters').addEventListener('click', () => {
                this.#page = 1;
                this.#readFiltersFromForm();
                this.load();
            });

            document.getElementById('mq-reset-filters').addEventListener('click', () => {
                document.getElementById('mq-filter-status').value = '';
                document.getElementById('mq-filter-risk').value = '';
                document.getElementById('mq-filter-assignment').value = '';
                document.getElementById('mq-search').value = '';
                this.#filters = {};
                this.#page = 1;
                this.load();
            });

            document.getElementById('mq-search').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    this.#page = 1;
                    this.#readFiltersFromForm();
                    this.load();
                }
            });

            document.getElementById('mq-retry-btn').addEventListener('click', () => this.load());
            document.getElementById('mq-prev-page').addEventListener('click', () => this.#changePage(-1));
            document.getElementById('mq-next-page').addEventListener('click', () => this.#changePage(1));
        }

        #readFiltersFromForm() {
            const filters = {};

            const status = document.getElementById('mq-filter-status').value;
            if (status) filters.status = status;

            const risk = document.getElementById('mq-filter-risk').value;
            if (risk) filters.severity = risk;

            const assignment = document.getElementById('mq-filter-assignment').value;
            if (assignment === 'mine') filters.assigned_to = CURRENT_USER_ID;
            if (assignment === 'unassigned') filters.unassigned = 1;
            if (assignment === 'others') filters.assigned_to_not = CURRENT_USER_ID; // ASSUMED: API supports this; otherwise filter client-side below

            const search = document.getElementById('mq-search').value.trim();
            if (search) filters.search = search; // ASSUMED: backend index() supports a `search` filter on title/contributor/page id

            this.#filters = filters;
        }

        #changePage(delta) {
            const next = this.#page + delta;
            if (next < 1) return;
            this.#page = next;
            this.load();
        }

        async load({ silent = false } = {}) {
            const body = document.getElementById('mq-table-body');
            const empty = document.getElementById('mq-empty-state');
            const error = document.getElementById('mq-error-state');

            if (!silent) {
                error.style.display = 'none';
                empty.style.display = 'none';
                body.innerHTML = this.#skeletonRows();
            }

            try {
                const params = new URLSearchParams({
                    ...this.#filters,
                    page: this.#page,
                    per_page: this.#perPage,
                });

                const res = await fetch(`/api/${this.#site}/open-collab/admin/moderation?${params.toString()}`, {
                    headers: { Authorization: `Bearer ${this.#token()}`, Accept: 'application/json' },
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const data = await res.json();
                const entries = data.data ?? data; // tolerate either {data:[...]} or [...]
                const meta = data.meta ?? null;

                let filtered = entries;
                if (this.#filters.assigned_to_not) {
                    filtered = filtered.filter(e =>
                        e.assigned_to_user_id && e.assigned_to_user_id !== this.#filters.assigned_to_not
                    );
                }

                if (filtered.length === 0) {
                    body.innerHTML = '';
                    empty.style.display = 'block';
                } else {
                    empty.style.display = 'none';
                    body.innerHTML = filtered.map(e => this.#renderRow(e)).join('');
                    this.#bindRowActions();
                }

                this.#renderPagination(meta, filtered.length);
            } catch (err) {
                if (!silent) {
                    body.innerHTML = '';
                    error.style.display = 'block';
                }
            }
        }

        #renderPagination(meta, count) {
            const summary = document.getElementById('mq-pagination-summary');
            const prev = document.getElementById('mq-prev-page');
            const next = document.getElementById('mq-next-page');

            if (meta) {
                summary.textContent = `Page ${meta.current_page} of ${meta.last_page} (${meta.total} items)`;
                prev.disabled = meta.current_page <= 1;
                next.disabled = meta.current_page >= meta.last_page;
            } else {
                summary.textContent = `Page ${this.#page}`;
                prev.disabled = this.#page <= 1;
                next.disabled = count < this.#perPage;
            }
        }

        #skeletonRows() {
            return Array.from({ length: 4 }).map(() => `
                <tr>
                    <td colspan="9" style="padding:0;">
                        <div style="padding:14px 20px;">
                            <div class="oc-skeleton" style="height:14px;width:60%;margin-bottom:8px;"></div>
                            <div class="oc-skeleton" style="height:14px;width:40%;"></div>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        #renderRow(entry) {
            const status = entry.status ?? 'queued';
            const riskLevel = this.#riskLevelFromScore(entry.risk_score);
            const slaState = this.#slaState(entry);
            const actions = entry.available_actions ?? [];
            const title = this.#escape(entry.page?.title ?? `Page #${entry.page?.id ?? ''}`);
            const contributor = this.#escape(
                entry.contributor?.display_name ?? (entry.contributor?.id ? `Contributor #${entry.contributor.id}` : '—')
            );
            const assigned = entry.assigned_to_display_name
                ? this.#escape(entry.assigned_to_display_name)
                : (entry.assigned_to_user_id ? `User #${entry.assigned_to_user_id}` : '—');
            const submitted = entry.submitted_at ? this.#formatDate(entry.submitted_at) : '—';

            return `
                <tr data-entry-id="${entry.id}">
                    <td data-label="Status">
                        <span class="oc-badge oc-badge--status-${status}">${this.#statusLabel(status)}</span>
                    </td>
                    <td data-label="Risk">
                        <span class="oc-badge oc-badge--risk-${riskLevel}">${this.#riskLabel(riskLevel)}</span>
                    </td>
                    <td data-label="Title">
                        <a href="/${this.#site}/open-collab/admin/moderation/${entry.id}"
                           style="color:var(--navy);font-weight:600;text-decoration:none;">
                            ${title}
                        </a>
                    </td>
                    <td data-label="Contributor">${contributor}</td>
                    <td data-label="Submitted">${submitted}</td>
                    <td data-label="Assigned">${assigned}</td>
                    <td data-label="Priority">${entry.priority_score ?? 0}</td>
                    <td data-label="SLA">
                        <span class="oc-badge oc-badge--sla-${slaState.key}">${slaState.label}</span>
                    </td>
                    <td data-label="Actions" style="text-align:right;white-space:nowrap;">
                        ${actions.includes('claim') ? `
                            <button class="oc-btn oc-btn--primary oc-btn--sm" data-action="claim" data-id="${entry.id}">
                                Claim
                            </button>` : ''}
                        ${actions.includes('release') ? `
                            <button class="oc-btn oc-btn--ghost oc-btn--sm" data-action="release" data-id="${entry.id}">
                                Release
                            </button>` : ''}
                        <a href="/${this.#site}/open-collab/admin/moderation/${entry.id}"
                           class="oc-btn oc-btn--ghost oc-btn--sm">Open</a>
                    </td>
                </tr>
            `;
        }

        #bindRowActions() {
            document.querySelectorAll('[data-action="claim"]').forEach(btn => {
                btn.addEventListener('click', () => this.#claim(btn.dataset.id, btn));
            });
            document.querySelectorAll('[data-action="release"]').forEach(btn => {
                btn.addEventListener('click', () => this.#release(btn.dataset.id, btn));
            });
        }

        async #claim(id, btn) {
            btn.disabled = true;
            const original = btn.textContent;
            btn.innerHTML = '<div class="oc-spinner"></div>';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/moderation/${id}/claim`, {
                    method: 'POST',
                    headers: { Authorization: `Bearer ${this.#token()}`, Accept: 'application/json' },
                });

                if (res.status === 409) {
                    this.#showToast('Already claimed by another moderator', false);
                    this.load({ silent: true });
                    return;
                }

                if (!res.ok) throw new Error('claim failed');

                this.#showToast('Claimed — opening review…');
                window.location.href = `/${this.#site}/open-collab/admin/moderation/${id}`;
            } catch {
                this.#showToast('Could not claim item', false);
                btn.disabled = false;
                btn.textContent = original;
            }
        }

        async #release(id, btn) {
            btn.disabled = true;
            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/moderation/${id}/release`, {
                    method: 'POST',
                    headers: { Authorization: `Bearer ${this.#token()}`, Accept: 'application/json' },
                });

                if (!res.ok) throw new Error('release failed');

                this.#showToast('Released');
                this.load({ silent: true });
            } catch {
                this.#showToast('Could not release item', false);
                btn.disabled = false;
            }
        }

        #riskLevelFromScore(score) {
            score = Number(score ?? 0);
            if (score >= 100) return 'critical';
            if (score >= 60) return 'high';
            if (score >= 30) return 'medium';
            if (score > 0) return 'low';
            return 'none';
        }

        #riskLabel(level) {
            return { none: 'None', low: 'Low', medium: 'Medium', high: 'High', critical: 'Critical' }[level] ?? 'None';
        }

        #statusLabel(status) {
            return {
                queued: 'Queued',
                claimed: 'Claimed',
                in_review: 'In Review',
                changes_requested: 'Changes Requested',
                escalated: 'Escalated',
                approved: 'Approved',
                rejected: 'Rejected',
                cancelled: 'Cancelled',
            }[status] ?? status;
        }

        #slaState(entry) {
            // ASSUMED: API returns sla_due_at (ISO string) when applicable.
            if (!entry.sla_due_at) return { key: 'on_track', label: 'On track' };

            const due = new Date(entry.sla_due_at).getTime();
            const now = Date.now();
            const hoursLeft = (due - now) / 3600000;

            if (hoursLeft < 0) return { key: 'overdue', label: 'Overdue' };
            if (hoursLeft <= 4) return { key: 'due_soon', label: 'Due soon' };
            return { key: 'on_track', label: 'On track' };
        }

        #formatDate(iso) {
            const d = new Date(iso);
            return d.toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        #escape(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        #showToast(msg, ok = true) {
            const el = document.getElementById('status-toast');
            el.textContent = msg;
            el.style.background = ok ? 'var(--navy)' : 'var(--red)';
            el.style.opacity = '1';
            setTimeout(() => { el.style.opacity = '0'; }, 3000);
        }
    }

    new ModerationQueueManager(SITE, () => localStorage.getItem('oc_token') || '');
</script>
@endsection