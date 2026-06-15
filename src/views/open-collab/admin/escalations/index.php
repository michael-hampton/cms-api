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

<div id="esc-resolve-modal"
     class="oc-modal-backdrop"
     style="display:none;"
     role="dialog"
     aria-modal="true"
     aria-labelledby="esc-resolve-modal-title">
    <div class="oc-modal" role="document" style="max-width:520px;padding:28px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px;">
            <div>
                <h3 id="esc-resolve-modal-title"
                    style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin:0 0 4px;">
                    Resolve escalation
                </h3>
                <p style="margin:0;color:var(--slate);font-size:.83rem;line-height:1.5;">
                    Record the outcome and any supporting context. This will close the escalation for approval checks.
                </p>
            </div>
            <button type="button"
                    class="oc-btn oc-btn--ghost oc-btn--sm"
                    id="esc-resolve-close-btn"
                    aria-label="Close resolve escalation modal">✕</button>
        </div>

        <div class="oc-form-group">
            <label class="oc-label" for="esc-resolution">Resolution</label>
            <select class="oc-select" id="esc-resolution">
                <option value="">Select resolution…</option>
                <option value="cleared">Cleared — no issue found</option>
                <option value="confirmed_violation">Confirmed violation</option>
                <option value="remediated">Issue remediated</option>
                <option value="accepted_risk">Risk accepted</option>
                <option value="duplicate">Duplicate escalation</option>
                <option value="not_applicable">Not applicable</option>
            </select>
        </div>

        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="esc-resolution-notes">Resolution notes</label>
            <textarea class="oc-textarea"
                      id="esc-resolution-notes"
                      rows="4"
                      maxlength="2000"
                      placeholder="Add any legal, editorial, or moderation context…"></textarea>
            <div class="oc-help">These notes are internal and will be stored with the escalation.</div>
        </div>

        <div id="esc-resolve-errors"
             class="oc-form-errors"
             style="display:none;margin-bottom:14px;"></div>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="oc-btn oc-btn--ghost" id="esc-resolve-cancel-btn">Cancel</button>
            <button type="button" class="oc-btn oc-btn--primary" id="esc-resolve-submit-btn">
                Resolve escalation
            </button>
        </div>
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
        #resolveId = null;
        #resolveButton = null;

        constructor(site, token) {
            this.#site = site;
            this.#token = token;

            document.getElementById('esc-apply-filters').addEventListener('click', () => {
                this.#readFilters();
                this.load();
            });

            document.getElementById('esc-reset-filters').addEventListener('click', () => {
                document.getElementById('esc-filter-status').value = '';
                document.getElementById('esc-filter-category').value = '';
                document.getElementById('esc-filter-severity').value = '';
                this.#filters = {};
                this.load();
            });

            document.getElementById('esc-retry-btn').addEventListener('click', () => this.load());
            document.getElementById('esc-resolve-close-btn').addEventListener('click', () => this.#closeResolveModal());
            document.getElementById('esc-resolve-cancel-btn').addEventListener('click', () => this.#closeResolveModal());
            document.getElementById('esc-resolve-submit-btn').addEventListener('click', () => this.#submitResolve());
            document.getElementById('esc-resolve-modal').addEventListener('click', event => {
                if (event.target === event.currentTarget) {
                    this.#closeResolveModal();
                }
            });

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    this.#closeResolveModal();
                }
            });

            this.load();
        }

        #readFilters() {
            const filters = {};
            const status = document.getElementById('esc-filter-status').value;
            const category = document.getElementById('esc-filter-category').value;
            const severity = document.getElementById('esc-filter-severity').value;
            if (status) filters.status = status;
            if (category) filters.category = category;
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
                    headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const data = await res.json();
                const items = data.data ?? data;

                if (!items.length) {
                    body.innerHTML = '';
                    empty.style.display = 'block';
                    return;
                }

                body.innerHTML = items.map(escalation => this.#renderRow(escalation)).join('');
                this.#bindRowActions();
            } catch {
                body.innerHTML = '';
                error.style.display = 'block';
            }
        }

        #renderRow(escalation) {
            const actions = escalation.available_actions ?? [];

            return `
                <tr data-id="${escalation.id}">
                    <td data-label="Article">
                        <a href="/${this.#site}/open-collab/admin/moderation/${escalation.queue_entry_id}" style="color:var(--navy);font-weight:600;text-decoration:none;">
                            ${this.#escape(escalation.page_title ?? `Page #${escalation.page_id}`)}
                        </a>
                    </td>
                    <td data-label="Category">${this.#capitalise(escalation.category)}</td>
                    <td data-label="Severity"><span class="oc-badge oc-badge--risk-${escalation.severity}">${this.#capitalise(escalation.severity)}</span></td>
                    <td data-label="Assigned Team">${this.#escape(escalation.assigned_team)}</td>
                    <td data-label="Assigned User">${escalation.assigned_user_display_name ?? (escalation.assigned_user_id ? `User #${escalation.assigned_user_id}` : '—')}</td>
                    <td data-label="Due Date">${escalation.due_at ? this.#formatDate(escalation.due_at) : '—'}</td>
                    <td data-label="Status"><span class="oc-badge oc-badge--status-${escalation.status}">${this.#capitalise(escalation.status)}</span></td>
                    <td data-label="Actions" style="text-align:right;white-space:nowrap;">
                        ${actions.includes('acknowledge') ? `<button class="oc-btn oc-btn--ghost oc-btn--sm" data-ack="${escalation.id}">Acknowledge</button>` : ''}
                        ${actions.includes('resolve') ? `<button class="oc-btn oc-btn--primary oc-btn--sm" data-resolve="${escalation.id}">Resolve</button>` : ''}
                    </td>
                </tr>
            `;
        }

        #bindRowActions() {
            document.querySelectorAll('[data-ack]').forEach(button =>
                button.addEventListener('click', () => this.#acknowledge(button.dataset.ack, button))
            );

            document.querySelectorAll('[data-resolve]').forEach(button =>
                button.addEventListener('click', () => this.#openResolveModal(button.dataset.resolve, button))
            );
        }

        async #acknowledge(id, button) {
            button.disabled = true;

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/escalations/${id}/acknowledge`, {
                    method: 'POST',
                    headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
                });

                if (!res.ok) throw new Error('failed');

                this.#showToast('Acknowledged');
                this.load();
            } catch {
                this.#showToast('Could not acknowledge', false);
                button.disabled = false;
            }
        }

        #openResolveModal(id, button) {
            this.#resolveId = id;
            this.#resolveButton = button;

            document.getElementById('esc-resolution').value = '';
            document.getElementById('esc-resolution-notes').value = '';
            document.getElementById('esc-resolve-errors').style.display = 'none';
            document.getElementById('esc-resolve-errors').textContent = '';
            document.getElementById('esc-resolve-modal').style.display = 'grid';
            document.getElementById('esc-resolution').focus();
        }

        #closeResolveModal() {
            const modal = document.getElementById('esc-resolve-modal');
            if (modal.style.display === 'none') return;

            modal.style.display = 'none';
            this.#resolveId = null;
            this.#resolveButton = null;
        }

        async #submitResolve() {
            const resolution = document.getElementById('esc-resolution').value;
            const notes = document.getElementById('esc-resolution-notes').value.trim();
            const errors = document.getElementById('esc-resolve-errors');
            const submitButton = document.getElementById('esc-resolve-submit-btn');

            errors.style.display = 'none';
            errors.textContent = '';

            if (!resolution) {
                errors.textContent = 'Please select a resolution.';
                errors.style.display = 'block';
                document.getElementById('esc-resolution').focus();
                return;
            }

            if (!this.#resolveId) {
                errors.textContent = 'No escalation was selected.';
                errors.style.display = 'block';
                return;
            }

            submitButton.disabled = true;
            submitButton.innerHTML = '<div class="oc-spinner"></div> Resolving…';
            if (this.#resolveButton) this.#resolveButton.disabled = true;

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/escalations/${this.#resolveId}/resolve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        resolution,
                        notes: notes || undefined,
                    }),
                });

                const data = await res.json();

                if (!res.ok) {
                    const message = data.error
                        || data.message
                        || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not resolve escalation.');
                    throw new Error(message);
                }

                this.#closeResolveModal();
                this.#showToast('Escalation resolved');
                this.load();
            } catch (error) {
                errors.textContent = error.message || 'Could not resolve escalation.';
                errors.style.display = 'block';
                if (this.#resolveButton) this.#resolveButton.disabled = false;
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Resolve escalation';
            }
        }

        #capitalise(value) {
            return (value ?? '').replace(/_/g, ' ').replace(/^\w/, character => character.toUpperCase());
        }

        #formatDate(iso) {
            return new Date(iso).toLocaleString(undefined, {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        }

        #escape(value) {
            const element = document.createElement('div');
            element.textContent = value ?? '';
            return element.innerHTML;
        }

        #showToast(message, ok = true) {
            const element = document.getElementById('status-toast');
            element.textContent = message;
            element.style.background = ok ? 'var(--navy)' : 'var(--red)';
            element.style.opacity = '1';
            setTimeout(() => {
                element.style.opacity = '0';
            }, 3000);
        }
    }

    new EscalationQueueManager(SITE, () => localStorage.getItem('oc_token') || '');
</script>
@endsection
