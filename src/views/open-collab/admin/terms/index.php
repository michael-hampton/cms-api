@section('logic')
<?php
$canCreateTerms = $canCreateTerms ?? false;
$canEditTerms = $canEditTerms ?? false;
$canPublishTerms = $canPublishTerms ?? false;
$canDeleteTerms = $canDeleteTerms ?? false;
$pageTitle = 'Terms & Conditions';
$activeNav = 'terms';
$breadcrumbs = [['label' => 'Terms & Conditions']];

?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')
<div id="terms-admin-root"
     data-site="<?= htmlspecialchars($siteSlug ?? $site ?? '') ?>"
     data-can-create="<?= $canCreateTerms ? '1' : '0' ?>"
     data-can-edit="<?= $canEditTerms ? '1' : '0' ?>"
     data-can-publish="<?= $canPublishTerms ? '1' : '0' ?>"
     data-can-delete="<?= $canDeleteTerms ? '1' : '0' ?>">

    <div id="terms-toast"
         style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;z-index:9999;pointer-events:none;white-space:nowrap;"></div>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px;">
        <div>
            <div class="oc-eyebrow">OpenCollab legal</div>
            <h1 style="margin:4px 0 8px;">Terms &amp; Conditions</h1>
            <p class="oc-muted" style="max-width:720px;">
                Create, review and publish immutable terms versions. Published versions remain available for audit and creator acceptance evidence.
            </p>
        </div>
        <?php if ($canCreateTerms): ?>
            <button class="oc-btn oc-btn--amber" type="button" id="terms-create-btn">Create draft</button>
        <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1.25fr) minmax(360px,.75fr);gap:20px;align-items:start;">
        <div class="oc-card">
            <div class="oc-card__header">
                <div>
                    <div class="oc-card__title">Version history</div>
                    <div class="oc-muted" style="font-size:.82rem;">Newest publication first</div>
                </div>
                <span id="terms-count" class="oc-badge oc-badge--muted">0</span>
            </div>

            <div id="terms-loading" style="padding:40px;text-align:center;">
                <div class="oc-spinner"></div>
            </div>

            <div id="terms-empty" style="display:none;padding:40px;text-align:center;">
                <h3 style="margin-bottom:8px;">No terms versions yet</h3>
                <p class="oc-muted">Create a draft manually or import a document.</p>
            </div>

            <div id="terms-list-wrap" class="oc-card__body" style="display:none;padding:0;overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;min-width:760px;">
                    <thead>
                    <tr style="text-align:left;border-bottom:1px solid var(--border);">
                        <th style="padding:14px 18px;">Version</th>
                        <th style="padding:14px 18px;">Title</th>
                        <th style="padding:14px 18px;">Change</th>
                        <th style="padding:14px 18px;">Status</th>
                        <th style="padding:14px 18px;text-align:right;">Action</th>
                    </tr>
                    </thead>
                    <tbody id="terms-list"></tbody>
                </table>
            </div>
        </div>

        <div style="position:sticky;top:84px;">
            <div class="oc-card" id="terms-editor-card">
                <div class="oc-card__header">
                    <div>
                        <div class="oc-card__title" id="terms-editor-title">Create draft</div>
                        <div class="oc-muted" id="terms-editor-subtitle" style="font-size:.82rem;"></div>
                    </div>
                </div>

                <div class="oc-card__body" style="padding:24px;">
                    <div id="terms-form-errors" class="oc-form-errors" style="display:none;margin-bottom:16px;"></div>
                    <div id="terms-locked-notice" class="oc-alert oc-alert--info" style="display:none;margin-bottom:16px;">
                        Published and archived versions are immutable.
                    </div>

                    <form id="terms-form" novalidate>
                        <div class="oc-form-group">
                            <label class="oc-label" for="terms-semantic-version">Semantic version</label>
                            <input class="oc-input" id="terms-semantic-version" name="semantic_version" required placeholder="1.0.0">
                        </div>

                        <div class="oc-form-group">
                            <label class="oc-label" for="terms-title">Title</label>
                            <input class="oc-input" id="terms-title" name="title" required>
                        </div>

                        <div class="oc-form-group">
                            <label class="oc-label" for="terms-change-summary">Change summary</label>
                            <textarea class="oc-textarea" id="terms-change-summary" name="change_summary" rows="3"></textarea>
                        </div>

                        <label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:18px;">
                            <input type="checkbox" id="terms-material-change" name="is_material_change" value="1" style="margin-top:3px;">
                            <span>
                                <strong>Material change</strong>
                                <span class="oc-muted" style="display:block;font-size:.78rem;margin-top:2px;">Requires creator re-acceptance.</span>
                            </span>
                        </label>

                        <div class="oc-form-group">
                            <label class="oc-label" for="terms-source-content">Terms content</label>
                            <textarea class="oc-textarea" id="terms-source-content" name="source_content" rows="18" required style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;line-height:1.55;"></textarea>
                            <div style="display:flex;justify-content:space-between;gap:12px;margin-top:6px;font-size:.78rem;">
                                <span id="terms-content-message" class="oc-muted">
                                    Minimum 50 characters required.
                                </span>
                                                            <span id="terms-content-counter" class="oc-muted">
                                    0 / 50
                                </span>
                            </div>
                        </div>

                        <div style="display:flex;justify-content:flex-end;gap:10px;">
                            <button class="oc-btn oc-btn--ghost" type="button" id="terms-reset-btn">Reset</button>
                            <button class="oc-btn oc-btn--amber" type="submit" id="terms-save-btn">Save draft</button>
                        </div>
                    </form>

                    <button class="oc-btn oc-btn--ghost" type="button" id="terms-publish-btn" style="display:none;width:100%;margin-top:12px;">Publish version</button>
                </div>
            </div>

            <?php if ($canCreateTerms): ?>
                <div class="oc-card" style="margin-top:20px;">
                    <div class="oc-card__header"><div class="oc-card__title">Import document</div></div>
                    <div class="oc-card__body" style="padding:24px;">
                        <div id="terms-import-errors" class="oc-form-errors" style="display:none;margin-bottom:16px;"></div>
                        <form id="terms-import-form" enctype="multipart/form-data" novalidate>
                            <div class="oc-form-group">
                                <label class="oc-label" for="terms-import-version">Semantic version</label>
                                <input class="oc-input" id="terms-import-version" name="semantic_version" required placeholder="1.0.0">
                            </div>
                            <div class="oc-form-group">
                                <label class="oc-label" for="terms-import-title">Title</label>
                                <input class="oc-input" id="terms-import-title" name="title" required>
                            </div>
                            <div class="oc-form-group">
                                <label class="oc-label" for="terms-import-summary">Change summary</label>
                                <textarea class="oc-textarea" id="terms-import-summary" name="change_summary" rows="3"></textarea>
                            </div>
                            <label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:18px;">
                                <input type="checkbox" id="terms-import-material" name="is_material_change" value="1" style="margin-top:3px;">
                                <span><strong>Material change</strong></span>
                            </label>
                            <div class="oc-form-group">
                                <label class="oc-label" for="terms-import-document">Document</label>
                                <input class="oc-input" id="terms-import-document" type="file" name="document" required accept=".pdf,.docx,.txt,.md">
                            </div>
                            <button class="oc-btn oc-btn--ghost" type="submit" id="terms-import-btn" style="width:100%;">Import as draft</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
class TermsAdminManager {
    constructor(root, tokenProvider) {
        this.root = root;
        this.tokenProvider = tokenProvider;
        this.site = root.dataset.site;
        this.permissions = {
            create: root.dataset.canCreate === '1',
            edit: root.dataset.canEdit === '1',
            publish: root.dataset.canPublish === '1',
            delete: root.dataset.canDelete === '1',
        };

        this.state = {
            items: [],
            selected: null,
            loading: false,
            saving: false,
            publishing: false,
            importing: false,
        };

        this.apiBase = `/api/${this.site}/open-collab/admin/terms`;
        this.cacheElements();
        this.bindEvents();
        this.load();
    }

    cacheElements() {
        this.elements = {
            loading: document.getElementById('terms-loading'),
            empty: document.getElementById('terms-empty'),
            listWrap: document.getElementById('terms-list-wrap'),
            list: document.getElementById('terms-list'),
            count: document.getElementById('terms-count'),
            createButton: document.getElementById('terms-create-btn'),
            form: document.getElementById('terms-form'),
            formErrors: document.getElementById('terms-form-errors'),
            editorTitle: document.getElementById('terms-editor-title'),
            editorSubtitle: document.getElementById('terms-editor-subtitle'),
            lockedNotice: document.getElementById('terms-locked-notice'),
            semanticVersion: document.getElementById('terms-semantic-version'),
            title: document.getElementById('terms-title'),
            changeSummary: document.getElementById('terms-change-summary'),
            materialChange: document.getElementById('terms-material-change'),
            sourceContent: document.getElementById('terms-source-content'),
            saveButton: document.getElementById('terms-save-btn'),
            resetButton: document.getElementById('terms-reset-btn'),
            publishButton: document.getElementById('terms-publish-btn'),
            importForm: document.getElementById('terms-import-form'),
            importErrors: document.getElementById('terms-import-errors'),
            importButton: document.getElementById('terms-import-btn'),
            toast: document.getElementById('terms-toast'),
            contentMessage: document.getElementById('terms-content-message'),
            contentCounter: document.getElementById('terms-content-counter'),
        };
    }

    bindEvents() {
        this.elements.sourceContent?.addEventListener(
            'input',
            () => this.updateContentValidation(),
        );
        this.elements.createButton?.addEventListener('click', () => this.select(null));
        this.elements.resetButton?.addEventListener('click', () => this.select(null));
        this.elements.form?.addEventListener('submit', event => {
            event.preventDefault();
            this.save();
        });
        this.elements.publishButton?.addEventListener('click', () => this.publish());
        this.elements.importForm?.addEventListener('submit', event => {
            event.preventDefault();
            this.importDocument();
        });
        this.elements.list?.addEventListener('click', event => {
            const button = event.target.closest('[data-terms-id]');

            if (!button) {
                return;
            }

            const id = Number(button.dataset.termsId);
            const item = this.state.items.find(
                candidate => Number(candidate.id) === id,
            );

            if (!item) {
                return;
            }

            if (button.dataset.action === 'delete') {
                this.deleteDraft(item);
                return;
            }

            if (button.dataset.action === 'publish') {
                this.state.selected = item;
                this.publish();
                return;
            }

            this.select(item);
        });
    }

    async load(selectedId = null) {
        this.setState({ loading: true });
        this.hideErrors(this.elements.formErrors);

        try {
            const payload = await this.request(this.apiBase);
            this.state.items = payload.terms ?? payload.data?.terms ?? [];
            this.renderList();

            const selected = selectedId
                ? this.state.items.find(item => Number(item.id) === Number(selectedId))
                : this.state.selected
                    ? this.state.items.find(item => Number(item.id) === Number(this.state.selected.id))
                    : null;

            this.select(selected ?? null, false);
        } catch (error) {
            this.showToast(error.message || 'Unable to load terms.', true);
        } finally {
            this.setState({ loading: false });
        }
    }

    select(item, scroll = true) {
        this.state.selected = item;
        this.renderEditor();
        this.renderList();

        if (scroll) {
            document.getElementById('terms-editor-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    updateContentValidation() {
        const minimum = 50;
        const length = this.elements.sourceContent.value.trim().length;
        const valid = length >= minimum;
        const item = this.state.selected;
        const status = item ? this.normaliseStatus(item.status) : 'draft';
        const locked = Boolean(item && status !== 'draft');
        const hasPermission = item
            ? this.permissions.edit
            : this.permissions.create;

        this.elements.contentCounter.textContent = `${length} / ${minimum}`;

        if (valid) {
            this.elements.contentMessage.textContent = 'Minimum length met.';
            this.elements.contentMessage.style.color = 'var(--green)';
            this.elements.contentCounter.style.color = 'var(--green)';
        } else {
            const remaining = minimum - length;

            this.elements.contentMessage.textContent =
                `${remaining} more character${remaining === 1 ? '' : 's'} required.`;

            this.elements.contentMessage.style.color = 'var(--red)';
            this.elements.contentCounter.style.color = 'var(--red)';
        }

        this.elements.saveButton.disabled =
            this.state.saving ||
            locked ||
            !hasPermission ||
            !valid;
    }

    renderList() {
        const items = this.state.items;
        this.elements.count.textContent = String(items.length);
        this.elements.empty.style.display = items.length ? 'none' : 'block';
        this.elements.listWrap.style.display = items.length ? 'block' : 'none';

        this.elements.list.innerHTML = items.map(item => {
            const status = this.normaliseStatus(item.status);
            const badgeClass = status === 'published'
                ? 'oc-badge--success'
                : status === 'archived'
                    ? 'oc-badge--muted'
                    : 'oc-badge--warning';
            const selected = this.state.selected && Number(this.state.selected.id) === Number(item.id);
            const action = status === 'draft' && this.permissions.edit ? 'Edit' : 'View';
            const canDelete =
                status === 'draft' &&
                this.permissions.delete;
            const canPublish =
                status === 'draft' &&
                this.permissions.publish;

            const actions = [
                `<button
        type="button"
        class="oc-btn oc-btn--ghost oc-btn--sm"
        data-action="select"
        data-terms-id="${Number(item.id)}"
    >
        ${action}
    </button>`,
            ];

            if (canDelete) {
                actions.push(`
        <button
            type="button"
            class="oc-btn oc-btn--ghost oc-btn--sm"
            data-action="delete"
            data-terms-id="${Number(item.id)}"
            style="color:var(--red);"
        >
            Delete
        </button>
    `);
            }

            if (canPublish) {
                actions.push(`
        <button
            type="button"
            class="oc-btn oc-btn--sm oc-btn--amber"
            data-action="publish"
            data-terms-id="${Number(item.id)}"
        >
            Publish
        </button>
    `);
            }

            return `
                <tr style="border-bottom:1px solid var(--border);${selected ? 'background:var(--amber-pale);' : ''}">
                    <td style="padding:16px 18px;font-weight:700;">v${this.escape(item.semantic_version ?? '')}</td>
                    <td style="padding:16px 18px;">
                        <div style="font-weight:600;">${this.escape(item.title ?? '')}</div>
                        ${item.change_summary ? `<div class="oc-muted" style="font-size:.78rem;margin-top:3px;max-width:300px;">${this.escape(item.change_summary)}</div>` : ''}
                    </td>
                    <td style="padding:16px 18px;">
                        <span class="oc-badge ${item.is_material_change ? 'oc-badge--danger' : 'oc-badge--muted'}">
                            ${item.is_material_change ? 'Material' : 'Editorial'}
                        </span>
                    </td>
                    <td style="padding:16px 18px;"><span class="oc-badge ${badgeClass}">${this.escape(this.ucfirst(status))}</span></td>
                   <td style="padding:16px 18px;text-align:right;">
                        <div style="display:flex;justify-content:flex-end;gap:6px;">
                            ${actions.join('')}
                        </div>
                    </td>
                </tr>`;
        }).join('');
    }

    renderEditor() {
        const item = this.state.selected;
        const status = item ? this.normaliseStatus(item.status) : 'draft';
        const locked = Boolean(item && status !== 'draft');
        const canSave = item ? this.permissions.edit && !locked : this.permissions.create;

        this.elements.editorTitle.textContent = item
            ? (locked ? 'Version details' : 'Edit draft')
            : 'Create draft';
        this.elements.editorSubtitle.textContent = item ? `Version ${item.semantic_version ?? ''}` : '';
        this.elements.lockedNotice.style.display = locked ? 'block' : 'none';

        this.elements.semanticVersion.value = item?.semantic_version ?? '';
        this.elements.title.value = item?.title ?? '';
        this.elements.changeSummary.value = item?.change_summary ?? '';
        this.elements.materialChange.checked = Boolean(item?.is_material_change);
        this.elements.sourceContent.value = item?.source_content ?? '';

        this.elements.semanticVersion.readOnly = Boolean(item);
        this.elements.title.readOnly = locked;
        this.elements.changeSummary.readOnly = locked;
        this.elements.materialChange.disabled = locked;
        this.elements.sourceContent.readOnly = locked;
        this.elements.saveButton.style.display = canSave ? 'inline-flex' : 'none';
        this.elements.resetButton.style.display = item ? 'inline-flex' : 'none';
        this.elements.publishButton.style.display = item && status === 'draft' && this.permissions.publish ? 'block' : 'none';

        this.hideErrors(this.elements.formErrors);
    }

    async save() {
        if (this.state.saving) return;
        const item = this.state.selected;

        const sourceContent = this.elements.sourceContent.value.trim();

        if (sourceContent.length < 50) {
            this.showErrors(this.elements.formErrors, {
                errors: {
                    source_content: [
                        `Terms content must be at least 50 characters. ${50 - sourceContent.length} more required.`,
                    ],
                },
            });

            this.elements.sourceContent.focus();
            this.updateContentValidation();
            return;
        }

        const payload = {
            semantic_version: this.elements.semanticVersion.value.trim(),
            title: this.elements.title.value.trim(),
            change_summary: this.elements.changeSummary.value.trim() || null,
            is_material_change: this.elements.materialChange.checked,
            source_content: sourceContent,
            source_format: 'html',
        };

        this.hideErrors(this.elements.formErrors);
        this.setState({ saving: true });
        this.setButtonBusy(this.elements.saveButton, true, item ? 'Saving…' : 'Creating…');

        try {
            const response = await this.request(
                item ? `${this.apiBase}/${item.id}` : this.apiBase,
                {
                    method: item ? 'PUT' : 'POST',
                    body: JSON.stringify(payload),
                },
            );
            const saved = response.terms ?? response.data?.terms;
            this.showToast(item ? 'Draft updated.' : 'Draft created.');
            await this.load(saved?.id ?? item?.id ?? null);
        } catch (error) {
            this.showErrors(this.elements.formErrors, error);
        } finally {
            this.setState({ saving: false });
            this.setButtonBusy(this.elements.saveButton, false, 'Save draft');
        }
    }

    async deleteDraft(item) {
        if (
            !this.permissions.delete ||
            this.normaliseStatus(item.status) !== 'draft'
        ) {
            return;
        }

        const confirmed = window.confirm(
            `Delete Terms v${item.semantic_version}? This cannot be undone.`,
        );

        if (!confirmed) {
            return;
        }

        try {
            await this.request(
                `${this.apiBase}/${item.id}`,
                { method: 'DELETE' },
            );

            if (
                this.state.selected &&
                Number(this.state.selected.id) === Number(item.id)
            ) {
                this.state.selected = null;
            }

            this.showToast('Terms draft deleted.');
            await this.load();
        } catch (error) {
            this.showToast(
                error.message || 'Unable to delete draft.',
                true,
            );
        }
    }

    async publish() {
        const item = this.state.selected;
        if (!item || this.state.publishing) return;

        this.setState({ publishing: true });
        this.setButtonBusy(this.elements.publishButton, true, 'Publishing…');

        try {
            await this.request(`${this.apiBase}/${item.id}/publish`, { method: 'POST' });
            this.showToast('Terms version published.');
            await this.load(item.id);
        } catch (error) {
            this.showErrors(this.elements.formErrors, error);
        } finally {
            this.setState({ publishing: false });
            this.setButtonBusy(this.elements.publishButton, false, 'Publish version');
        }
    }

    async importDocument() {
        if (!this.elements.importForm || this.state.importing) return;

        const formData = new FormData(this.elements.importForm);
        formData.set('is_material_change', document.getElementById('terms-import-material').checked ? '1' : '0');

        this.hideErrors(this.elements.importErrors);
        this.setState({ importing: true });
        this.setButtonBusy(this.elements.importButton, true, 'Importing…');

        try {
            const response = await this.request(`${this.apiBase}/from-document`, {
                method: 'POST',
                body: formData,
            });
            const imported = response.terms ?? response.data?.terms;
            this.elements.importForm.reset();
            this.showToast('Document imported as draft.');
            await this.load(imported?.id ?? null);
        } catch (error) {
            this.showErrors(this.elements.importErrors, error);
        } finally {
            this.setState({ importing: false });
            this.setButtonBusy(this.elements.importButton, false, 'Import as draft');
        }
    }

    async request(url, options = {}) {
        const isForm = options.body instanceof FormData;
        const token = this.tokenProvider?.() || '';
        const headers = {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            ...(isForm ? {} : { 'Content-Type': 'application/json' }),
            ...(options.headers ?? {}),
        };

        const response = await fetch(url, {
            ...options,
            headers,
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(payload.message || 'Request failed.');
            error.errors = payload.errors ?? payload.data?.errors ?? {};
            error.status = response.status;
            throw error;
        }

        return payload.data ?? payload;
    }

    setState(patch) {
        Object.assign(this.state, patch);
        this.elements.loading.style.display = this.state.loading ? 'block' : 'none';
    }

    setButtonBusy(button, busy, label) {
        if (!button) {
            return;
        }

        button.textContent = label;

        if (button === this.elements.saveButton) {
            this.state.saving = busy;
            this.updateContentValidation();
            return;
        }

        button.disabled = busy;
    }

    showErrors(container, error) {
        const errors = error.errors && typeof error.errors === 'object'
            ? Object.values(error.errors).flat()
            : [error.message || 'Request failed.'];

        container.innerHTML = errors.map(message => `<div>${this.escape(String(message))}</div>`).join('');
        container.style.display = 'block';
    }

    hideErrors(container) {
        if (!container) return;
        container.innerHTML = '';
        container.style.display = 'none';
    }

    showToast(message, isError = false) {
        this.elements.toast.textContent = message;
        this.elements.toast.style.background = isError ? 'var(--red)' : 'var(--navy)';
        this.elements.toast.style.opacity = '1';
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => {
            this.elements.toast.style.opacity = '0';
        }, 2600);
    }

    normaliseStatus(status) {
        if (status && typeof status === 'object') {
            return String(status.value ?? status.name ?? '').toLowerCase();
        }
        return String(status ?? '').toLowerCase();
    }

    ucfirst(value) {
        return value ? value.charAt(0).toUpperCase() + value.slice(1) : '';
    }

    escape(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('terms-admin-root');
    if (root) {
        window.termsAdminManager = new TermsAdminManager(
            root,
            () => localStorage.getItem('oc_token') || '',
        );
    }
});
</script>
@endsection
