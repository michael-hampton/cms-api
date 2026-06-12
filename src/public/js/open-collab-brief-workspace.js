class BriefApiClient {
    constructor(site, tokenProvider) {
        this.site = site;
        this.tokenProvider = tokenProvider;
        this.base = `/api/${site}/open-collab`;
    }

    request(path, options = {}) {
        const headers = {
            Accept: 'application/json',
            Authorization: `Bearer ${this.tokenProvider()}`,
            ...(options.headers || {}),
        };

        return fetch(`${this.base}${path}`, {...options, headers})
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const error = new Error(payload.error || 'Request failed.');
                    error.payload = payload;
                    throw error;
                }
                return payload;
            });
    }

    getBrief(id) { return this.request(`/briefs/${id}`); }
    getTimeline(id) { return this.request(`/briefs/${id}/timeline`); }
    getAttachments(id) { return this.request(`/briefs/${id}/attachments`); }
    getComments(id) { return this.request(`/briefs/${id}/comments`); }
    acceptAssignment(id) { return this.request(`/briefs/${id}/accept`, {method: 'POST'}); }
    rejectAssignment(id, payload) { return this.json(`/briefs/${id}/reject`, payload); }
    negotiateAssignment(id, payload) { return this.json(`/briefs/${id}/negotiate`, payload); }
    requestClarification(id, payload) { return this.json(`/briefs/${id}/request-clarification`, payload); }
    requestDeadlineChange(id, payload) { return this.json(`/briefs/${id}/request-deadline-change`, payload); }
    submitBrief(id, payload) { return this.json(`/briefs/${id}/submit`, payload); }
    resubmitBrief(id, payload) { return this.json(`/briefs/${id}/resubmit`, payload); }
    updateTask(id, taskId, payload) { return this.json(`/briefs/${id}/tasks/${taskId}`, payload, 'PATCH'); }
    createComment(id, payload) { return this.json(`/briefs/${id}/comments`, payload); }
    resolveComment(commentId) { return this.request(`/comments/${commentId}/resolve`, {method: 'POST'}); }
    uploadAttachment(id, formData) { return this.request(`/briefs/${id}/attachments`, {method: 'POST', body: formData, headers: {}}); }
    deleteAttachment(id, attachmentId) { return this.request(`/briefs/${id}/attachments/${attachmentId}`, {method: 'DELETE'}); }

    json(path, payload, method = 'POST') {
        return this.request(path, {
            method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload || {}),
        });
    }
}

class ContributorBriefState {
    constructor() {
        this.payload = null;
        this.loading = true;
        this.error = null;
    }

    setLoading() { this.loading = true; this.error = null; }
    setPayload(payload) { this.payload = payload.data || payload; this.loading = false; this.error = null; }
    setError(message) { this.loading = false; this.error = message; }
}

class StatusBadgeRenderer {
    render(label, tone = '') {
        return `<span class="oc-brief-badge ${tone}">${this.escape(label)}</span>`;
    }

    escape(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }
}

class ContributorBriefOverviewRenderer {
    constructor(root, badgeRenderer) {
        this.header = root.querySelector('[data-overview-header]');
        this.panel = root.querySelector('[data-overview-panel]');
        this.summary = root.querySelector('[data-summary-panel]');
        this.badges = badgeRenderer;
    }

    render(payload) {
        const brief = payload.brief;
        this.header.innerHTML = `
            <h1>${this.escape(brief.title)}</h1>
            <div class="oc-brief-row__statuses">
                ${this.badges.render(brief.assignment_status_label)}
                ${this.badges.render(brief.workflow_status_label, 'oc-brief-badge--muted')}
                ${brief.is_overdue ? this.badges.render('Overdue', 'oc-brief-badge--danger') : ''}
            </div>`;

        this.panel.innerHTML = `
            <h2>Brief Overview</h2>
            <div class="oc-brief-detail-grid">
                ${this.detail('Description', brief.description || 'No description provided.')}
                ${this.detail('Requirements', brief.requirements || 'No requirements provided.')}
                ${this.detail('Target audience', brief.target_audience || 'Not specified')}
                ${this.detail('SEO guidance', brief.seo_guidance || 'Not specified')}
                ${this.detail('Target word count', brief.target_word_count || 'Not specified')}
                ${this.detail('Reference links', (brief.reference_links || []).join(', ') || 'None')}
            </div>`;

        this.summary.innerHTML = `
            <h2>Assignment Summary</h2>
            <div class="oc-brief-detail-grid">
                ${this.detail('Assignment', brief.assignment_status_label)}
                ${this.detail('Workflow', brief.workflow_status_label)}
                ${this.detail('Deadline', this.date(brief.deadline_at))}
                ${this.detail('Time remaining', this.remaining(brief.deadline_at))}
                ${this.detail('Last updated', this.date(brief.last_updated_at))}
                ${this.detail('Submission', brief.workflow_status_label)}
            </div>`;
    }

    detail(label, value) {
        return `<div class="oc-brief-detail"><span>${this.escape(label)}</span><p>${this.escape(value)}</p></div>`;
    }

    date(value) {
        return value ? new Intl.DateTimeFormat(undefined, {day: '2-digit', month: 'short', year: 'numeric'}).format(new Date(value)) : 'None';
    }

    remaining(value) {
        if (!value) return 'Not scheduled';
        const diff = new Date(value).getTime() - Date.now();
        const days = Math.ceil(Math.abs(diff) / 86400000);
        return diff < 0 ? `${days} days overdue` : `${days} days remaining`;
    }

    escape(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }
}

class ContributorBriefTaskPanel {
    constructor(root, api, getBriefId, onRefresh) {
        this.el = root.querySelector('[data-task-panel]');
        this.api = api;
        this.getBriefId = getBriefId;
        this.onRefresh = onRefresh;
    }

    render(tasks) {
        this.el.innerHTML = `<h2>Tasks / Subtasks</h2>${tasks.length ? tasks.map(t => `
            <div class="oc-brief-item">
                <div class="oc-brief-item__head"><strong>${this.escape(t.title)}</strong><span>${this.escape(t.status)}</span></div>
                <p>${this.escape(t.description || '')}</p>
                <span>${this.escape(t.assigned_user || 'Unassigned')} · Due ${this.date(t.due_date)}</span>
                <div style="margin-top:8px;"><button class="oc-btn oc-btn--ghost oc-btn--sm" data-task-complete="${t.id}">Mark complete</button></div>
            </div>`).join('') : '<p class="oc-brief-panel__empty">No tasks yet.</p>'}`;

        this.el.querySelectorAll('[data-task-complete]').forEach((button) => {
            button.addEventListener('click', () => this.api.updateTask(this.getBriefId(), button.dataset.taskComplete, {status: 'completed'}).then(this.onRefresh));
        });
    }

    date(value) { return value ? new Date(value).toLocaleDateString() : 'none'; }
    escape(value) { const div = document.createElement('div'); div.textContent = value == null ? '' : String(value); return div.innerHTML; }
}

class ContributorBriefAttachmentPanel {
    constructor(root, api, getBriefId, onRefresh) {
        this.el = root.querySelector('[data-attachment-panel]');
        this.api = api;
        this.getBriefId = getBriefId;
        this.onRefresh = onRefresh;
    }

    render(attachments) {
        this.el.innerHTML = `<h2>Attachments</h2>
            <form data-upload-form class="oc-brief-field">
                <input type="file" name="file">
                <textarea name="description" placeholder="Description"></textarea>
                <button class="oc-btn oc-btn--ghost oc-btn--sm" type="submit">Upload attachment</button>
            </form>
            ${attachments.length ? attachments.map(a => `
                <div class="oc-brief-item">
                    <div class="oc-brief-item__head"><strong>${this.escape(a.filename)}</strong><span>${this.escape(a.type)}</span></div>
                    <p>${this.escape(a.description || '')}</p>
                    <a href="${this.escapeAttr(a.url || '#')}" target="_blank" rel="noopener">Download</a>
                    ${a.can_delete ? `<button class="oc-btn oc-btn--ghost oc-btn--sm" data-delete-attachment="${a.id}">Delete</button>` : ''}
                </div>`).join('') : '<p class="oc-brief-panel__empty">No attachments yet.</p>'}`;

        this.el.querySelector('[data-upload-form]').addEventListener('submit', (event) => {
            event.preventDefault();
            this.api.uploadAttachment(this.getBriefId(), new FormData(event.currentTarget)).then(this.onRefresh);
        });
        this.el.querySelectorAll('[data-delete-attachment]').forEach((button) => {
            button.addEventListener('click', () => this.api.deleteAttachment(this.getBriefId(), button.dataset.deleteAttachment).then(this.onRefresh));
        });
    }

    escape(value) { const div = document.createElement('div'); div.textContent = value == null ? '' : String(value); return div.innerHTML; }
    escapeAttr(value) { return this.escape(value).replace(/"/g, '&quot;'); }
}

class ContributorBriefCommentPanel {
    constructor(root, api, getBriefId, onRefresh) {
        this.el = root.querySelector('[data-comment-panel]');
        this.api = api;
        this.getBriefId = getBriefId;
        this.onRefresh = onRefresh;
    }

    render(comments) {
        this.el.innerHTML = `<h2>Comments and Clarifications</h2>
            <form data-comment-form class="oc-brief-field">
                <textarea name="content" placeholder="Add a comment"></textarea>
                <button class="oc-btn oc-btn--ghost oc-btn--sm" type="submit">Post comment</button>
            </form>
            ${comments.length ? comments.map(c => `
                <div class="oc-brief-item">
                    <div class="oc-brief-item__head"><strong>${this.escape(c.author)}</strong><span>${c.is_resolved ? 'Resolved' : 'Open'}</span></div>
                    <p>${this.escape(c.content)}</p>
                    <button class="oc-btn oc-btn--ghost oc-btn--sm" data-resolve-comment="${c.id}">Resolve</button>
                </div>`).join('') : '<p class="oc-brief-panel__empty">No comments yet.</p>'}`;

        this.el.querySelector('[data-comment-form]').addEventListener('submit', (event) => {
            event.preventDefault();
            const form = new FormData(event.currentTarget);
            this.api.createComment(this.getBriefId(), {content: form.get('content')}).then(this.onRefresh);
        });
        this.el.querySelectorAll('[data-resolve-comment]').forEach((button) => {
            button.addEventListener('click', () => this.api.resolveComment(button.dataset.resolveComment).then(this.onRefresh));
        });
    }

    escape(value) { const div = document.createElement('div'); div.textContent = value == null ? '' : String(value); return div.innerHTML; }
}

class ContributorBriefTimelinePanel {
    constructor(root) { this.el = root.querySelector('[data-timeline-panel]'); }
    render(timeline) {
        this.el.innerHTML = `<h2>Workflow Timeline</h2>${timeline.length ? timeline.map(e => `
            <div class="oc-brief-item">
                <div class="oc-brief-item__head"><strong>${this.escape(e.label)}</strong><span>${this.date(e.created_at)}</span></div>
                <p>${this.escape(e.message)}</p>
            </div>`).join('') : '<p class="oc-brief-panel__empty">No workflow activity yet.</p>'}`;
    }
    date(value) { return value ? new Date(value).toLocaleString() : ''; }
    escape(value) { const div = document.createElement('div'); div.textContent = value == null ? '' : String(value); return div.innerHTML; }
}

class ContributorAssignmentActions {
    constructor(root, api, getBriefId, modal, onRefresh) {
        this.el = root.querySelector('[data-action-panel]');
        this.api = api;
        this.getBriefId = getBriefId;
        this.modal = modal;
        this.onRefresh = onRefresh;
    }

    render(actions) {
        const labels = {
            accept: 'Accept assignment', reject: 'Reject assignment', negotiate: 'Negotiate assignment',
            request_clarification: 'Request clarification', request_deadline_change: 'Request deadline change',
            submit: 'Submit work', resubmit: 'Resubmit work',
        };
        this.el.innerHTML = `<h2>Available Actions</h2><div class="oc-brief-actions">${
            actions.length ? actions.map(a => `<button class="oc-btn oc-btn--ghost oc-btn--sm" data-action="${a}">${labels[a] || a}</button>`).join('') : '<p class="oc-brief-panel__empty">No actions available.</p>'
        }</div>`;
        this.el.querySelectorAll('[data-action]').forEach((button) => {
            button.addEventListener('click', () => this.run(button.dataset.action));
        });
    }

    run(action) {
        if (action === 'accept') {
            this.api.acceptAssignment(this.getBriefId()).then(this.onRefresh);
            return;
        }
        this.modal.open(action, (payload) => {
            const id = this.getBriefId();
            const map = {
                reject: () => this.api.rejectAssignment(id, payload),
                negotiate: () => this.api.negotiateAssignment(id, payload),
                request_clarification: () => this.api.requestClarification(id, payload),
                request_deadline_change: () => this.api.requestDeadlineChange(id, payload),
                submit: () => this.api.submitBrief(id, payload),
                resubmit: () => this.api.resubmitBrief(id, payload),
            };
            return map[action]().then(this.onRefresh);
        });
    }
}

class AssignmentActionModal {
    constructor(root) {
        this.el = root.querySelector('[data-action-modal]');
        this.title = this.el.querySelector('[data-modal-title]');
        this.fields = this.el.querySelector('[data-modal-fields]');
        this.form = this.el.querySelector('[data-modal-form]');
        this.errors = this.el.querySelector('[data-modal-errors]');
        this.el.querySelectorAll('[data-modal-close]').forEach(b => b.addEventListener('click', () => this.close()));
    }

    open(action, submitter) {
        this.action = action;
        this.submitter = submitter;
        this.errors.hidden = true;
        this.title.textContent = this.titleFor(action);
        this.fields.innerHTML = this.fieldsFor(action);
        this.el.hidden = false;
        this.form.onsubmit = (event) => {
            event.preventDefault();
            submitter(Object.fromEntries(new FormData(this.form))).then(() => this.close()).catch((error) => this.showErrors(error));
        };
    }

    close() { this.el.hidden = true; }
    titleFor(action) { return action.split('_').map(w => w[0].toUpperCase() + w.slice(1)).join(' '); }
    fieldsFor(action) {
        if (action === 'request_deadline_change') return this.textarea('reason', 'Reason') + this.input('requested_deadline', 'Requested deadline', 'datetime-local');
        if (action === 'negotiate') return this.textarea('message', 'Message') + this.input('requested_deadline', 'Requested deadline', 'datetime-local') + this.textarea('scope_details', 'Scope/change details');
        if (action === 'reject') return this.textarea('reason', 'Reason');
        if (action === 'submit' || action === 'resubmit') return this.textarea('notes', 'Submission notes');
        return this.textarea('message', 'Message');
    }
    textarea(name, label) { return `<div class="oc-brief-field"><label>${label}</label><textarea name="${name}"></textarea></div>`; }
    input(name, label, type) { return `<div class="oc-brief-field"><label>${label}</label><input name="${name}" type="${type}"></div>`; }
    showErrors(error) {
        const errors = error.payload?.errors || {error: [error.message]};
        this.errors.innerHTML = Object.values(errors).flat().join('<br>');
        this.errors.hidden = false;
    }
}

class ContributorBriefWorkspacePage {
    init() {
        this.root = document.querySelector('[data-brief-workspace]');
        if (!this.root) return;
        this.state = new ContributorBriefState();
        this.api = new BriefApiClient(this.root.dataset.site, () => localStorage.getItem('oc_token') || '');
        this.badges = new StatusBadgeRenderer();
        this.overview = new ContributorBriefOverviewRenderer(this.root, this.badges);
        this.tasks = new ContributorBriefTaskPanel(this.root, this.api, () => this.briefId(), () => this.load());
        this.attachments = new ContributorBriefAttachmentPanel(this.root, this.api, () => this.briefId(), () => this.load());
        this.comments = new ContributorBriefCommentPanel(this.root, this.api, () => this.briefId(), () => this.load());
        this.timeline = new ContributorBriefTimelinePanel(this.root);
        this.modal = new AssignmentActionModal(this.root);
        this.actions = new ContributorAssignmentActions(this.root, this.api, () => this.briefId(), this.modal, () => this.load());
        this.root.querySelector('[data-retry]').addEventListener('click', () => this.load());
        this.load();
    }

    briefId() { return this.root.dataset.briefId; }

    async load() {
        this.renderLoading();
        try {
            this.state.setPayload(await this.api.getBrief(this.briefId()));
        } catch (error) {
            this.state.setError(error.message);
        }
        this.render();
    }

    renderLoading() {
        this.root.querySelector('[data-state="loading"]').hidden = false;
        this.root.querySelector('[data-state="error"]').hidden = true;
        this.root.querySelector('[data-workspace-content]').hidden = true;
    }

    render() {
        this.root.querySelector('[data-state="loading"]').hidden = true;
        if (this.state.error) {
            this.root.querySelector('[data-error-message]').textContent = this.state.error;
            this.root.querySelector('[data-state="error"]').hidden = false;
            return;
        }
        const payload = this.state.payload;
        this.root.querySelector('[data-workspace-content]').hidden = false;
        this.overview.render(payload);
        this.tasks.render(payload.tasks || []);
        this.attachments.render(payload.attachments || []);
        this.comments.render(payload.comments || []);
        this.timeline.render(payload.timeline || []);
        this.actions.render(payload.available_actions || []);
    }
}

class ContributorSubmissionActions {}
class ContributorSubmissionApi {}
class SubmitBriefModal {}
class ResubmitBriefModal {}
class SubmissionStateManager {}
class ContributorWorkflowTimeline {}
class ContributorTimelineApi {}
class WorkflowTimelineRenderer extends ContributorBriefTimelinePanel {}
class WorkflowStatusBadge extends StatusBadgeRenderer {}
class ContributorTimelineState extends ContributorBriefState {}
class ContributorAssignmentApi extends BriefApiClient {}
class AcceptAssignmentModal extends AssignmentActionModal {}
class RejectAssignmentModal extends AssignmentActionModal {}
class ClarificationModal extends AssignmentActionModal {}
class DeadlineChangeRequestModal extends AssignmentActionModal {}
class NegotiateAssignmentModal extends AssignmentActionModal {}
class AssignmentActionRenderer extends ContributorAssignmentActions {}
class AssignmentStateManager extends ContributorBriefState {}

window.ContributorBriefWorkspacePage = ContributorBriefWorkspacePage;
