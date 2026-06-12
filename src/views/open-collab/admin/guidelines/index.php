@section('logic')
<?php
$allowedComponentKeys = $allowedComponentKeys ?? [];
$canCreateGuideline = in_array('guidelines.create_action', $allowedComponentKeys, true);
$canEditGuideline = in_array('guidelines.edit_action', $allowedComponentKeys, true);
$canPublishGuideline = in_array('guidelines.publish_action', $allowedComponentKeys, true);
$canDeleteGuideline = in_array('guidelines.delete_action', $allowedComponentKeys, true);
$canCloneGuideline = in_array('guidelines.clone_action', $allowedComponentKeys, true);
$pageTitle = 'Brand Guidelines';
$activeNav = 'guidelines';
$breadcrumbs = [['label' => 'Guidelines']];
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:9999;pointer-events:none;white-space:nowrap;"></div>

<div id="edit-modal" class="oc-modal-backdrop" onclick="if(event.target===this)manager.closeEditModal()">
    <div class="oc-modal" style="max-width:680px;">
        <div class="oc-modal__header">
            <span class="oc-modal__title" id="edit-modal-title">Edit Guidelines v—</span>
            <button class="oc-modal__close" onclick="manager.closeEditModal()">✕</button>
        </div>
        <div class="oc-modal__body">
            <div id="edit-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
            <input type="hidden" id="edit-guideline-id">
            <div class="oc-form-group" style="margin-bottom:0;">
                <label class="oc-label" for="edit-content">Guidelines content</label>
                <textarea class="oc-textarea" id="edit-content" rows="14"
                          style="min-height:260px;font-family:monospace;font-size:.82rem;"></textarea>
            </div>
        </div>
        <div class="oc-modal__footer">
            <button onclick="manager.closeEditModal()" class="oc-btn oc-btn--ghost">Cancel</button>
            <button onclick="manager.saveEdit()" class="oc-btn oc-btn--amber" id="save-edit-btn">Save changes</button>
        </div>
    </div>
</div>

<div id="view-modal" class="oc-modal-backdrop" onclick="if(event.target===this)manager.closeViewModal()">
    <div class="oc-modal" style="max-width:720px;">
        <div class="oc-modal__header">
            <span class="oc-modal__title" id="view-modal-title">Guidelines v—</span>
            <button class="oc-modal__close" onclick="manager.closeViewModal()">✕</button>
        </div>
        <div id="view-modal-meta"
             style="padding:0 24px 12px;font-size:.75rem;color:var(--slate);display:flex;gap:16px;flex-wrap:wrap;border-bottom:1px solid var(--border);"></div>
        <div id="view-modal-content"
             style="padding:24px;overflow-y:auto;font-size:.875rem;line-height:1.75;color:var(--navy);max-height:60vh;"></div>
        <div class="oc-modal__footer" id="view-modal-actions"></div>
    </div>
</div>

<div id="template-modal" class="oc-modal-backdrop" onclick="if(event.target===this)templateManager.close()">
    <div class="oc-modal" style="max-width:760px;">
        <div class="oc-modal__header">
            <span class="oc-modal__title">Guideline Templates Library</span>
            <button class="oc-modal__close" onclick="templateManager.close()">✕</button>
        </div>

        <div id="template-form-wrap"
             style="display:none;padding:20px 24px;border-bottom:1px solid var(--border);background:var(--cream);">
            <div id="template-form-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
            <input type="hidden" id="template-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div class="oc-form-group" style="margin-bottom:0;"><label class="oc-label">Template Name</label><input
                            class="oc-input" id="template-name" type="text"></div>
                <div class="oc-form-group" style="margin-bottom:0; margin-top: 0;"><label class="oc-label">Unique
                        Slug</label><input class="oc-input" id="template-slug" type="text"></div>
            </div>
            <div class="oc-form-group" style="margin-bottom:12px;"><label class="oc-label">Description</label><input
                        class="oc-input" id="template-description" type="text"></div>
            <div class="oc-form-group" style="margin-bottom:12px;"><label class="oc-label">Content</label><textarea
                        class="oc-textarea" id="template-content" rows="6" style="font-family:monospace;"></textarea>
            </div>
            <div class="oc-form-group" style="margin-bottom:12px;">
                <label class="oc-label" for="template-document">Import from document</label>
                <input class="oc-input" id="template-document" type="file" accept=".pdf,.docx,.txt,.md">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button onclick="templateManager.hideForm()" class="oc-btn oc-btn--ghost oc-btn--sm">Cancel</button>
                <button onclick="templateManager.save()" class="oc-btn oc-btn--amber oc-btn--sm" id="template-save-btn">
                    Save Template
                </button>
            </div>
        </div>

        <div class="oc-modal__body" style="padding:0;">
            <div id="template-header-actions"
                 style="padding:16px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;background:#fafafa;">
                <span style="font-size:.8rem;color:var(--slate);">Select a template to generate a new guidelines draft.</span>
                <button onclick="templateManager.showForm()" class="oc-btn oc-btn--amber oc-btn--sm">+ New Template
                </button>
            </div>
            <div id="templates-loading" style="text-align:center;padding:40px;color:var(--slate);">
                <div class="oc-spinner"></div>
            </div>
            <div id="templates-list" style="display:none;max-height:400px;overflow-y:auto;"></div>
            <div id="templates-empty" style="display:none;padding:40px;text-align:center;">No templates found.</div>
        </div>
        <div class="oc-modal__footer">
            <button onclick="templateManager.close()" class="oc-btn oc-btn--ghost">Close</button>
        </div>
    </div>
</div>

<div id="confirm-modal" class="oc-modal-backdrop" onclick="if(event.target===this)manager.closeConfirm()">
    <div class="oc-modal" style="max-width:420px;">
        <div class="oc-modal__header"><span class="oc-modal__title" id="confirm-title">Confirm Action</span></div>
        <div class="oc-modal__body">
            <p id="confirm-message" style="font-size:.875rem;color:var(--slate);line-height:1.6;"></p>
        </div>
        <div class="oc-modal__footer">
            <button onclick="manager.closeConfirm()" class="oc-btn oc-btn--ghost">Cancel</button>
            <button id="confirm-ok-btn" class="oc-btn oc-btn--amber">Confirm</button>
        </div>
    </div>
</div>

<div class="oc-grid-sidebar" style="align-items:start;gap:24px;">
    <div>
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title">Guideline Versions</span>
                <span id="version-count"
                      style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
            </div>
            <div id="list-loading" style="padding:40px;text-align:center;">
                <div class="oc-spinner"></div>
            </div>
            <div id="guidelines-empty" style="display:none;padding:48px 24px;text-align:center;color:var(--slate);">No
                guidelines found.
            </div>
            <div id="guidelines-list" style="display:none;"></div>
        </div>
    </div>

    <div style="position:sticky;top:84px;">
        <?php if ($canCreateGuideline): ?>
        <div class="oc-card">
            <div class="oc-card__header"><span class="oc-card__title">Create New Draft</span></div>
            <div class="oc-card__body">
                <div id="create-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
                <textarea class="oc-textarea" id="quick-guideline-content" rows="12"
                          placeholder="Enter guideline text..."
                          style="font-family:monospace;font-size:.82rem;margin-bottom:12px;"></textarea>
                <div class="oc-form-group" style="margin-bottom:12px;">
                    <label class="oc-label" for="quick-guideline-document">Or upload a guidelines document</label>
                    <input class="oc-input" id="quick-guideline-document" type="file" accept=".pdf,.docx,.txt,.md">
                </div>
                <div style="display:flex;gap:8px;">
                    <button onclick="manager.createDraft()" class="oc-btn oc-btn--amber" style="flex:1;"
                            id="create-btn">Save as draft
                    </button>
                    <button onclick="manager.createDraftFromDocument()" class="oc-btn oc-btn--ghost" id="create-document-btn"
                            title="Upload Document">
                        Upload
                    </button>
                    <button onclick="templateManager.open()" class="oc-btn oc-btn--ghost" id="template-btn"
                            title="Use Template">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="15">
                            <path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/>
                            <path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const CAN_CREATE_GUIDELINE = <?= $canCreateGuideline ? 'true' : 'false' ?>;
    const CAN_EDIT_GUIDELINE = <?= $canEditGuideline ? 'true' : 'false' ?>;
    const CAN_PUBLISH_GUIDELINE = <?= $canPublishGuideline ? 'true' : 'false' ?>;
    const CAN_DELETE_GUIDELINE = <?= $canDeleteGuideline ? 'true' : 'false' ?>;
    const CAN_CLONE_GUIDELINE = <?= $canCloneGuideline ? 'true' : 'false' ?>;

    class GuidelinesTemplateManager {
        #site;
        #token;
        constructor(site, token) {
            this.#site = site;
            this.#token = token;

            this.initSlugListener();
        }

        async open() {
            document.getElementById('template-modal').classList.add('is-open');
            this.hideForm();
            await this.load();
        }

        close() {
            document.getElementById('template-modal').classList.remove('is-open');
        }

        async load() {
            const list = document.getElementById('templates-list');
            const loader = document.getElementById('templates-loading');
            const empty = document.getElementById('templates-empty');

            // 1. Reset state: hide list and empty message, show loader
            list.style.display = 'none';
            empty.style.display = 'none';
            loader.style.display = 'block';

            const res = await this.#fetch(`/api/${this.#site}/open-collab/admin/guideline-templates`);
            const items = await res.json().then(d => Array.isArray(d) ? d : (d.data ?? []));

            loader.style.display = 'none';

            // 2. Only show the one that is relevant
            if (!items.length) {
                empty.style.display = 'block';
                return;
            }

            list.style.display = 'block';
            list.innerHTML = '';

            items.forEach(t => {
                const div = document.createElement('div');
                div.className = 'template-row';
                div.innerHTML = `
                    <div style="min-width:0;">
                        <div style="font-weight:600;color:var(--navy);">${this.#esc(t.name)}</div>
                        <div style="font-size:.7rem;color:var(--slate);">${this.#esc(t.slug)}</div>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <button onclick="templateManager.use(${t.id}, this)" class="oc-btn oc-btn--sm oc-btn--amber">Use</button>
                        <button onclick="templateManager.edit(${JSON.stringify(t).replace(/"/g, '&quot;')})" class="oc-btn oc-btn--sm oc-btn--ghost">Edit</button>
                        <button onclick="templateManager.deactivate(${t.id})" class="oc-btn oc-btn--sm oc-btn--ghost" style="color:var(--red);">×</button>
                    </div>`;
                list.appendChild(div);
            });
        }

        initSlugListener() {
            const nameInput = document.getElementById('template-name');
            const slugInput = document.getElementById('template-slug');

            nameInput.addEventListener('input', () => {
                // Only auto-populate if the slug is empty or matches a slugified version of the old name
                slugInput.value = nameInput.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/(^-|-$)+/g, '');
            });
        }

        showForm(t = null) {
            // 1. Fill fields
            document.getElementById('template-id').value = t?.id || '';
            document.getElementById('template-name').value = t?.name || '';
            document.getElementById('template-slug').value = t?.slug || '';
            document.getElementById('template-description').value = t?.description || '';
            document.getElementById('template-content').value = t?.content || '';
            document.getElementById('template-document').value = '';

            // 2. Toggle Visibility
            document.getElementById('template-form-wrap').style.display = 'block';
            document.getElementById('template-header-actions').style.display = 'none'; // Hide the + button header
            document.getElementById('templates-list').style.display = 'none';           // Hide the list
            document.getElementById('templates-empty').style.display = 'none';

            // 3. Focus
            document.getElementById('template-name').focus();
        }

        hideForm() {
            document.getElementById('template-form-wrap').style.display = 'none';
            document.getElementById('template-header-actions').style.display = 'flex'; // Show + button header

            // Only show the list if there's actually content there
            const list = document.getElementById('templates-list');
            if (list.children.length > 0) {
                list.style.display = 'block';
            } else {
                document.getElementById('templates-empty').style.display = 'block';
            }
        }

        async save() {
            const btn = document.getElementById('template-save-btn');
            this.#setLoading(btn, 'Saving...');
            const id = document.getElementById('template-id').value;
            const file = document.getElementById('template-document').files[0];
            if (!id && file) {
                const form = new FormData();
                form.append('name', document.getElementById('template-name').value);
                form.append('slug', document.getElementById('template-slug').value);
                form.append('description', document.getElementById('template-description').value);
                form.append('document', file);
                await this.#fetch(`/api/${this.#site}/open-collab/admin/guideline-templates/import-document`, {
                    method: 'POST',
                    body: form
                });
                this.#clearLoading(btn, 'Save Template');
                this.hideForm();
                this.load();
                return;
            }
            const payload = {
                name: document.getElementById('template-name').value,
                slug: document.getElementById('template-slug').value,
                description: document.getElementById('template-description').value,
                content: document.getElementById('template-content').value,
            };
            const method = id ? 'PUT' : 'POST';
            const url = id ? `/api/${this.#site}/open-collab/admin/guideline-templates/${id}` : `/api/${this.#site}/open-collab/admin/guideline-templates`;

            await this.#fetch(url, {method, body: JSON.stringify(payload)});
            this.#clearLoading(btn, 'Save Template');
            this.hideForm();
            this.load();
        }

        async use(id, btn) {
            this.#setLoading(btn, '...');
            await this.#fetch(`/api/${this.#site}/open-collab/admin/guidelines/from-template`, {
                method: 'POST',
                body: JSON.stringify({template_id: id})
            });
            this.close();
            manager.reload();
        }

        edit(t) {
            this.showForm(t);
        }

        async deactivate(id) {
            if (confirm('Delete this template?')) {
                await this.#fetch(`/api/${this.#site}/open-collab/admin/guideline-templates/${id}`, {method: 'DELETE'});
                this.load();
            }
        }

        #fetch(url, opts = {}) {
            const isForm = opts.body instanceof FormData;
            const headers = {
                Authorization: `Bearer ${this.#token()}`,
                Accept: 'application/json', ...(opts.headers ?? {})
            };
            if (!isForm) headers['Content-Type'] = 'application/json';
            return fetch(url, {
                ...opts,
                headers
            });
        }

        #esc(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        #setLoading(btn, label) {
            btn.disabled = true;
            btn.innerHTML = `<div class="oc-spinner"></div> ${label}`;
        }

        #clearLoading(btn, label) {
            btn.disabled = false;
            btn.textContent = label;
        }
    }

    class GuidelinesManager {
        #site;
        #token;

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
            this.reload();
        }

        reload() {
            this.#load();
        }

        async #load() {
            const res = await this.#fetch(`/api/${this.#site}/open-collab/admin/guidelines`);
            const data = await res.json();
            const items = Array.isArray(data) ? data : (data.data ?? []);
            document.getElementById('list-loading').style.display = 'none';
            document.getElementById('version-count').textContent = items.length;
            const listEl = document.getElementById('guidelines-list');
            if (!items.length) {
                document.getElementById('guidelines-empty').style.display = 'block';
                return;
            }
            listEl.style.display = 'block';
            listEl.innerHTML = '';
            items.forEach((g, i) => listEl.appendChild(this.#buildRow(g, i === 0)));
        }

        #buildRow(g, isLatest) {
            const status = (g.status?.value ?? g.status ?? 'draft').toLowerCase();
            const badgeClass = {draft: 'badge-draft', published: 'badge-published', archived: 'badge-archived'}[status];
            const div = document.createElement('div');
            div.style.cssText = 'padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;';
            div.innerHTML = `
                <div>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;">
                        <span style="font-weight:600;color:var(--navy);">Version ${g.version}</span>
                        <span class="oc-status-badge ${badgeClass}">${status}</span>
                        ${isLatest && status === 'published' ? '<span class="oc-status-badge" style="background:#fef9c3;color:#a16207;">Active</span>' : ''}
                    </div>
                    <div style="font-size:.75rem;color:var(--slate);">Created ${new Date(g.created_at).toLocaleDateString()}</div>
                </div>
                <div style="display:flex;gap:6px;">
                    <button onclick="manager.viewGuideline(${g.id},${g.version})" class="oc-btn oc-btn--ghost oc-btn--sm">View</button>
                    ${status === 'draft' && CAN_EDIT_GUIDELINE ? `<button onclick="manager.editGuideline(${g.id},${g.version})" class="oc-btn oc-btn--ghost oc-btn--sm">Edit</button>` : ''}
                    ${status === 'draft' && CAN_PUBLISH_GUIDELINE ? `<button onclick="manager.publishGuideline(${g.id},${g.version})" class="oc-btn oc-btn--sm oc-btn--amber">Publish</button>` : ''}
                    ${status === 'draft' && isLatest && CAN_DELETE_GUIDELINE ? `<button onclick="manager.deleteGuideline(${g.id},${g.version})" class="oc-btn oc-btn--ghost oc-btn--sm" style="color:var(--red);">Delete</button>` : ''}
                    ${status !== 'draft' && CAN_CLONE_GUIDELINE ? `<button onclick="manager.cloneGuideline(${g.id},${g.version})" class="oc-btn oc-btn--ghost oc-btn--sm">Clone</button>` : ''}
                </div>`;
            return div;
        }

        async createDraft() {
            const btn = document.getElementById('create-btn');
            const err = document.getElementById('create-errors');
            const content = document.getElementById('quick-guideline-content').value.trim();
            if (content.length < 50) {
                err.textContent = 'Minimum 50 characters required.';
                err.style.display = 'block';
                return;
            }
            err.style.display = 'none';
            this.#setLoading(btn, 'Saving...');
            await this.#fetch(`/api/${this.#site}/open-collab/admin/guidelines`, {
                method: 'POST',
                body: JSON.stringify({content})
            });
            this.#clearLoading(btn, 'Save as draft');
            document.getElementById('quick-guideline-content').value = '';
            this.reload();
            this.#toast('✓ Draft saved');
        }

        async createDraftFromDocument() {
            const btn = document.getElementById('create-document-btn');
            const err = document.getElementById('create-errors');
            const file = document.getElementById('quick-guideline-document').files[0];
            if (!file) {
                err.textContent = 'Choose a PDF, DOCX, TXT, or Markdown document.';
                err.style.display = 'block';
                return;
            }
            err.style.display = 'none';
            const form = new FormData();
            form.append('document', file);
            form.append('title', file.name.replace(/\.[^.]+$/, ''));
            this.#setLoading(btn, 'Uploading...');
            await this.#fetch(`/api/${this.#site}/open-collab/admin/guidelines/from-document`, {
                method: 'POST',
                body: form
            });
            this.#clearLoading(btn, 'Upload');
            document.getElementById('quick-guideline-document').value = '';
            this.reload();
            this.#toast('✓ Document draft saved');
        }

        async viewGuideline(id, version) {
            const res = await this.#fetch(`/api/${this.#site}/open-collab/admin/guidelines/${id}`);
            const data = await res.json();
            const g = data.data?.guideline ?? data.guideline;
            const status = (g.status?.value ?? g.status ?? 'draft').toLowerCase();
            document.getElementById('view-modal-title').textContent = `Guidelines v${version}`;
            document.getElementById('view-modal-content').innerHTML = g.content || 'No content';
            const meta = [`Created ${new Date(g.created_at).toLocaleDateString()}`];
            if (g.published_at) meta.push(`Published ${new Date(g.published_at).toLocaleDateString()}`);
            if (g.source_template_id) meta.push(`From template #${g.source_template_id}`);
            document.getElementById('view-modal-meta').innerHTML = meta.join(' · ');
            const btns = [`<button onclick="manager.closeViewModal()" class="oc-btn oc-btn--ghost">Close</button>`];
            if (status === 'draft' && CAN_EDIT_GUIDELINE) btns.push(`<button onclick="manager.closeViewModal();manager.editGuideline(${g.id},${g.version})" class="oc-btn oc-btn--amber">Edit Draft</button>`);
            document.getElementById('view-modal-actions').innerHTML = btns.join('');
            document.getElementById('view-modal').classList.add('is-open');
        }

        async editGuideline(id, v) {
            const res = await this.#fetch(`/api/${this.#site}/open-collab/admin/guidelines/${id}`);
            const data = await res.json();
            const g = data.data?.guideline ?? data.guideline;
            document.getElementById('edit-modal-title').textContent = `Edit Guidelines v${v}`;
            document.getElementById('edit-guideline-id').value = id;
            document.getElementById('edit-content').value = g.content;
            document.getElementById('edit-modal').classList.add('is-open');
        }

        async saveEdit() {
            const btn = document.getElementById('save-edit-btn');
            const err = document.getElementById('edit-errors');
            const id = document.getElementById('edit-guideline-id').value;
            const content = document.getElementById('edit-content').value.trim();
            if (content.length < 50) {
                err.textContent = 'Minimum 50 characters required.';
                err.style.display = 'block';
                return;
            }
            err.style.display = 'none';
            this.#setLoading(btn, 'Saving...');
            await this.#fetch(`/api/${this.#site}/open-collab/admin/guidelines/${id}`, {
                method: 'PUT',
                body: JSON.stringify({content})
            });
            this.#clearLoading(btn, 'Save changes');
            this.closeEditModal();
            this.reload();
            this.#toast('✓ Updated');
        }

        publishGuideline(id, v) {
            this.confirm({
                title: 'Publish Guidelines', message: `Make v${v} the active guidelines?`, onOk: async () => {
                    await this.#fetch(`/api/${this.#site}/open-collab/admin/guidelines/${id}/publish`, {method: 'POST'});
                    this.reload();
                    this.#toast('✓ Published');
                }
            });
        }

        cloneGuideline(id, v) {
            this.confirm({
                title: 'Clone to Draft', message: `Create new draft from v${v}?`, onOk: async () => {
                    await this.#fetch(`/api/${this.#site}/open-collab/admin/guidelines/${id}/clone`, {method: 'POST'});
                    this.reload();
                    this.#toast('✓ Cloned');
                }
            });
        }

        deleteGuideline(id, v) {
            this.confirm({
                title: 'Delete Draft', message: `Delete draft v${v}?`, onOk: async () => {
                    await this.#fetch(`/api/${this.#site}/open-collab/admin/guidelines/${id}`, {method: 'DELETE'});
                    this.reload();
                    this.#toast('Draft deleted', false);
                }
            });
        }

        closeEditModal() {
            document.getElementById('edit-modal').classList.remove('is-open');
        }

        closeViewModal() {
            document.getElementById('view-modal').classList.remove('is-open');
        }

        confirm({title, message, onOk}) {
            document.getElementById('confirm-title').textContent = title;
            document.getElementById('confirm-message').textContent = message;
            document.getElementById('confirm-ok-btn').onclick = () => {
                this.closeConfirm();
                onOk();
            };
            document.getElementById('confirm-modal').classList.add('is-open');
        }

        closeConfirm() {
            document.getElementById('confirm-modal').classList.remove('is-open');
        }

        #fetch(url, opts = {}) {
            const isForm = opts.body instanceof FormData;
            const headers = {
                Authorization: `Bearer ${this.#token()}`,
                Accept: 'application/json', ...(opts.headers ?? {})
            };
            if (!isForm) headers['Content-Type'] = 'application/json';
            return fetch(url, {
                ...opts,
                headers
            });
        }

        #esc(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        #setLoading(btn, label) {
            btn.disabled = true;
            btn.innerHTML = `<div class="oc-spinner"></div> ${label}`;
        }

        #clearLoading(btn, label) {
            btn.disabled = false;
            btn.textContent = label;
        }

        #toast(msg, ok = true) {
            const el = document.getElementById('status-toast');
            el.textContent = msg;
            el.style.background = ok ? 'var(--navy)' : 'var(--red)';
            el.style.opacity = '1';
            setTimeout(() => el.style.opacity = '0', 3000);
        }
    }

    const manager = new GuidelinesManager(SITE, () => localStorage.getItem('oc_token') || '');
    const templateManager = new GuidelinesTemplateManager(SITE, () => localStorage.getItem('oc_token') || '');
</script>
@endsection
