<!-- ── Image Library Modal ────────────────────────────────────────────────── -->
<div id="image-library-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="image-library-modal-title"
     style="display:none;position:fixed;inset:0;z-index:700;background:rgba(15,25,41,.7);
            place-items:center;padding:20px;">

    <div class="il-panel" role="document">

        <!-- Header -->
        <div class="il-header">
            <h2 id="image-library-modal-title" class="il-header__title">Choose image</h2>
            <button type="button"
                    class="oc-btn oc-btn--ghost oc-btn--sm"
                    id="il-close-btn"
                    aria-label="Close image library">✕</button>
        </div>

        <!-- Tabs -->
        <div class="il-tabs" role="tablist">
            <button type="button"
                    class="il-tab il-tab--active"
                    role="tab"
                    id="il-tab-library"
                    aria-selected="true"
                    aria-controls="il-panel-library">
                Library
            </button>
            <button type="button"
                    class="il-tab"
                    role="tab"
                    id="il-tab-upload"
                    aria-selected="false"
                    aria-controls="il-panel-upload">
                Upload new
            </button>
        </div>

        <!-- ── Library tab ──────────────────────────────────────────────── -->
        <div id="il-panel-library" role="tabpanel" aria-labelledby="il-tab-library" class="il-body">

            <!-- Search bar -->
            <div class="il-search-bar">
                <input type="text"
                       id="il-search-input"
                       class="oc-input"
                       placeholder="Search your images…"
                       aria-label="Search images"
                       autocomplete="off">
            </div>

            <!-- Status messages (loading / empty / error) -->
            <div id="il-library-status" role="status" aria-live="polite" class="il-status" style="display:none;"></div>

            <!-- Thumbnail grid -->
            <div id="il-grid" class="il-grid" role="list" aria-label="Image library"></div>

            <!-- Pagination -->
            <div id="il-pagination" class="il-pagination" style="display:none;">
                <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" id="il-prev-btn" disabled>← Previous</button>
                <span id="il-page-indicator" class="il-page-indicator"></span>
                <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" id="il-next-btn">Next →</button>
            </div>

            <!-- Selected image detail panel -->
            <div id="il-detail-panel" class="il-detail" style="display:none;" aria-live="polite">
                <div class="il-detail__preview">
                    <img id="il-detail-img" src="" alt="" style="max-height:200px;border-radius:6px;border:1px solid var(--border);">
                </div>
                <div class="il-detail__meta">
                    <div class="il-detail__name" id="il-detail-name"></div>
                    <div class="il-detail__dims" id="il-detail-dims"></div>
                    <div class="il-detail__rights" id="il-detail-rights"></div>
                    <div class="il-detail__credit" id="il-detail-credit" style="display:none;"></div>
                    <div class="il-detail__blocked" id="il-detail-blocked"
                         role="alert" style="display:none;color:var(--red);font-size:.8rem;"></div>
                </div>
            </div>

        </div>

        <!-- ── Upload tab ────────────────────────────────────────────────── -->
        <div id="il-panel-upload" role="tabpanel" aria-labelledby="il-tab-upload" class="il-body" style="display:none;">

            <div id="il-upload-errors" role="alert" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

            <!-- Drop zone -->
            <div id="il-drop-zone"
                 class="il-drop-zone"
                 tabindex="0"
                 role="button"
                 aria-label="Click or drag and drop an image to upload">
                <svg viewBox="0 0 20 20" fill="currentColor" width="28" style="color:var(--slate-light);display:block;margin:0 auto 8px;">
                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                </svg>
                <div id="il-drop-zone-label" style="font-size:.82rem;color:var(--slate);">
                    Click or drag &amp; drop an image here
                </div>
            </div>
            <input type="file" id="il-file-input" accept="image/*" style="display:none;">

            <div id="il-upload-preview"
                 style="display:none;text-align:center;">
                <img id="il-upload-preview-img"
                     src=""
                     alt=""
                     style="max-height:220px;
                max-width:100%;
                border-radius:8px;
                border:1px solid var(--border);">
            </div>

            <!-- Upload form fields -->
            <div class="il-upload-form">
                <div class="oc-form-group">
                    <label class="oc-label" for="il-upload-name">Image name <span class="oc-required">*</span></label>
                    <input type="text" id="il-upload-name" class="oc-input" maxlength="255" autocomplete="off">
                </div>

                <div class="oc-form-group">
                    <label class="oc-label" for="il-upload-rights">Image rights <span class="oc-required">*</span></label>
                    <select id="il-upload-rights" class="oc-input">
                        <option value="">Select rights…</option>
                        <option value="contributor_owned">Contributor-owned</option>
                        <option value="staff_owned">Staff-owned</option>
                        <option value="royalty_free">Royalty Free</option>
                        <option value="creative_commons">Creative Commons</option>
                        <option value="public_domain">Public Domain</option>
                        <option value="third_party_licensed">Licensed third-party image</option>
                        <option value="agency">Agency image</option>
                        <option value="editorial_use_only">Editorial use only</option>
                    </select>
                </div>

                <div class="oc-form-group">
                    <label class="oc-label" for="il-upload-alt">Alt text <span class="oc-required">*</span></label>
                    <input type="text" id="il-upload-alt" class="oc-input" maxlength="500"
                           placeholder="Describe the image for accessibility…">
                </div>

                <div class="oc-form-group">
                    <label class="oc-label" for="il-upload-credit">
                        Credit <span id="il-credit-required-note" class="oc-required" style="display:none;">*</span>
                        <span id="il-credit-optional-note" style="font-weight:400;color:var(--slate-light);">(optional)</span>
                    </label>
                    <input type="text" id="il-upload-credit" class="oc-input" maxlength="255"
                           placeholder="e.g. Jane Smith / Agency Name">
                </div>

                <div class="oc-form-group">
                    <label class="oc-toggle-row" style="align-items:flex-start;gap:10px;">
                        <input type="checkbox" id="il-upload-rights-confirm">
                        <span style="font-size:.82rem;color:var(--navy);">
                            I confirm I have the rights to use this image and the information above is accurate.
                        </span>
                    </label>
                </div>

                <!-- Optional declarations -->
                <div class="il-declarations">
                    <div class="il-declarations__title">Declarations</div>
                    <label class="oc-toggle-row oc-toggle-row--sm">
                        <input type="checkbox" id="il-upload-ai-generated">
                        <span>AI-generated image</span>
                    </label>
                    <label class="oc-toggle-row oc-toggle-row--sm">
                        <input type="checkbox" id="il-upload-sponsored">
                        <span>Sponsored content</span>
                    </label>
                    <label class="oc-toggle-row oc-toggle-row--sm">
                        <input type="checkbox" id="il-upload-affiliate">
                        <span>Contains affiliate content</span>
                    </label>
                    <label class="oc-toggle-row oc-toggle-row--sm">
                        <input type="checkbox" id="il-upload-contains-music">
                        <span>Contains music</span>
                    </label>

                    <label class="oc-toggle-row oc-toggle-row--sm">
                        <input type="checkbox" id="il-upload-unclear-rights">
                        <span>Unclear image ownership / provenance</span>
                    </label>
                </div>
            </div>

            <!-- Upload progress -->
            <div id="il-upload-progress" style="display:none;text-align:center;padding:16px 0;">
                <div class="oc-spinner" style="margin:0 auto 8px;width:24px;height:24px;border-width:3px;"></div>
                <div style="font-size:.82rem;color:var(--slate);">Uploading…</div>
            </div>

        </div>

        <!-- Footer -->
        <div class="il-footer">
            <button type="button" class="oc-btn oc-btn--ghost" id="il-cancel-btn">Cancel</button>
            <button type="button" class="oc-btn oc-btn--amber" id="il-select-btn" disabled>Select image</button>
            <button type="button" class="oc-btn oc-btn--amber" id="il-upload-submit-btn" style="display:none;" disabled>
                Upload and select
            </button>
        </div>

    </div>
</div>

<!-- ── Modal styles ──────────────────────────────────────────────────────── -->
<style>
    .il-panel {
        background: #fff;
        border-radius: 12px;
        width: 100%;
        max-width: 860px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 60px rgba(0,0,0,.35);
        overflow: hidden;
    }

    .il-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
    }

    .il-header__title {
        font-size: .95rem;
        font-weight: 700;
        color: var(--navy);
        margin: 0;
    }

    .il-tabs {
        display: flex;
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
    }

    .il-tab {
        padding: 10px 20px;
        font-size: .82rem;
        font-weight: 600;
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        cursor: pointer;
        color: var(--slate);
        transition: color .15s, border-color .15s;
    }

    .il-tab--active,
    .il-tab:hover {
        color: var(--navy);
        border-bottom-color: var(--amber);
    }

    .il-body {
        flex: 1;
        overflow-y: auto;
        padding: 16px 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    /* Search */
    .il-search-bar {
        display: flex;
        gap: 8px;
    }

    /* Status messages */
    .il-status {
        text-align: center;
        padding: 32px 0;
        font-size: .82rem;
        color: var(--slate);
    }

    .il-status--error {
        color: var(--red);
    }

    /* Grid */
    .il-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 8px;
        min-height: 120px;
    }

    .il-grid-item {
        position: relative;
        border-radius: 6px;
        overflow: hidden;
        border: 2px solid transparent;
        cursor: pointer;
        background: var(--cream-dark);
        aspect-ratio: 1;
        transition: border-color .12s;
    }

    .il-grid-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .il-grid-item:hover,
    .il-grid-item:focus {
        border-color: var(--amber);
        outline: none;
    }

    .il-grid-item--selected {
        border-color: var(--amber);
        box-shadow: 0 0 0 2px var(--amber);
    }

    .il-grid-item--selected::after {
        content: '✓';
        position: absolute;
        top: 4px;
        right: 4px;
        background: var(--amber);
        color: #fff;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: .7rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .il-grid-item--blocked {
        opacity: .5;
        cursor: not-allowed;
    }

    .il-grid-item__rights-badge {
        position: absolute;
        bottom: 4px;
        left: 4px;
        background: rgba(15,25,41,.65);
        color: #fff;
        font-size: .55rem;
        font-weight: 700;
        padding: 1px 5px;
        border-radius: 3px;
        text-transform: uppercase;
        letter-spacing: .04em;
        line-height: 1.4;
        max-width: calc(100% - 8px);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Pagination */
    .il-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding-top: 4px;
    }

    .il-page-indicator {
        font-size: .75rem;
        color: var(--slate);
    }

    /* Detail panel */
    .il-detail {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 16px;
        padding: 14px;
        background: var(--cream-dark);
        border-radius: 8px;
        border: 1px solid var(--border);
    }

    .il-detail__meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .il-detail__name {
        font-weight: 600;
        font-size: .88rem;
        color: var(--navy);
    }

    .il-detail__dims,
    .il-detail__rights,
    .il-detail__credit {
        font-size: .75rem;
        color: var(--slate);
    }

    /* Drop zone */
    .il-drop-zone {
        border: 2px dashed var(--border);
        border-radius: var(--radius);
        padding: 28px 20px;
        text-align: center;
        cursor: pointer;
        background: var(--cream-dark);
        transition: border-color .15s, background .15s;
    }

    .il-drop-zone:hover,
    .il-drop-zone--over,
    .il-drop-zone:focus {
        border-color: var(--amber);
        background: rgba(245,158,11,.05);
        outline: none;
    }

    /* Upload form */
    .il-upload-form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .il-declarations {
        padding: 10px 12px;
        background: var(--cream-dark);
        border-radius: 6px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .il-declarations__title {
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--slate);
        margin-bottom: 2px;
    }

    /* Footer */
    .il-footer {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding: 14px 20px;
        border-top: 1px solid var(--border);
        flex-shrink: 0;
    }
</style>

<!-- ── Image Library JS ──────────────────────────────────────────────────── -->
<script>
    (() => {
        'use strict';

        const RIGHTS_LABELS = {
            contributor_owned:    'Contributor-owned',
            staff_owned:          'Staff-owned',
            third_party_licensed: 'Licensed third-party',
            agency:               'Agency',
            editorial_use_only:   'Editorial use only',
            royalty_free:         'Royalty Free',
            public_domain:        'Public Domain',
            creative_commons:     'Creative Commons',
            all_rights_reserved:  'All Rights Reserved',
            attribution_required: 'Attribution Required',
            custom_license:       'Custom License',
            unknown:              'Rights not confirmed',
        };

        const RIGHTS_REQUIRING_CREDIT = new Set([
            'contributor_owned',
            'third_party_licensed',
            'agency',
            'editorial_use_only',
            'attribution_required',
            'creative_commons',
        ]);

        const BLOCKING_RIGHTS = new Set(['unknown']);

        class ImageLibraryState {
            constructor() {
                this.activeBlockId = null;
                this.currentTab = 'library';
                this.selectedImage = null;
                this.currentPage = 1;
                this.totalPages = 1;
                this.pendingFile = null;
                this.isUploading = false;
                this.searchTimer = null;
                this.abortController = null;
                this.preFocusEl = null;
                this.trapHandler = null;
            }

            resetForOpen(blockId) {
                this.activeBlockId = blockId;
                this.currentTab = 'library';
                this.selectedImage = null;
                this.currentPage = 1;
                this.totalPages = 1;
            }

            resetForClose() {
                this.activeBlockId = null;
                this.selectedImage = null;
            }
        }

        class ImageLibrary {
            constructor() {
                this.state = new ImageLibraryState();
                this.onSelect = null;

                this.el = this.cacheElements();
                this.bindEvents();
            }

            cacheElements() {
                const byId = id => document.getElementById(id);

                return {
                    modal: byId('image-library-modal'),
                    closeBtn: byId('il-close-btn'),
                    cancelBtn: byId('il-cancel-btn'),

                    tabLibrary: byId('il-tab-library'),
                    tabUpload: byId('il-tab-upload'),
                    panelLibrary: byId('il-panel-library'),
                    panelUpload: byId('il-panel-upload'),

                    searchInput: byId('il-search-input'),
                    status: byId('il-library-status'),
                    grid: byId('il-grid'),
                    pagination: byId('il-pagination'),
                    prevBtn: byId('il-prev-btn'),
                    nextBtn: byId('il-next-btn'),
                    pageIndicator: byId('il-page-indicator'),

                    detailPanel: byId('il-detail-panel'),
                    detailImg: byId('il-detail-img'),
                    detailName: byId('il-detail-name'),
                    detailDims: byId('il-detail-dims'),
                    detailRights: byId('il-detail-rights'),
                    detailCredit: byId('il-detail-credit'),
                    detailBlocked: byId('il-detail-blocked'),

                    selectBtn: byId('il-select-btn'),
                    uploadSubmitBtn: byId('il-upload-submit-btn'),

                    fileInput: byId('il-file-input'),
                    dropZone: byId('il-drop-zone'),
                    dropZoneLabel: byId('il-drop-zone-label'),

                    uploadErrors: byId('il-upload-errors'),
                    uploadName: byId('il-upload-name'),
                    uploadRights: byId('il-upload-rights'),
                    uploadAlt: byId('il-upload-alt'),
                    uploadCredit: byId('il-upload-credit'),
                    uploadConfirm: byId('il-upload-rights-confirm'),
                    uploadAiGenerated: byId('il-upload-ai-generated'),
                    uploadContainsMusic: byId('il-upload-contains-music'),
                    uploadUnclearRights: byId('il-upload-unclear-rights'),
                    uploadSponsored: byId('il-upload-sponsored'),
                    uploadAffiliate: byId('il-upload-affiliate'),
                    uploadProgress: byId('il-upload-progress'),

                    creditRequiredNote: byId('il-credit-required-note'),
                    creditOptionalNote: byId('il-credit-optional-note'),
                };
            }

            bindEvents() {
                this.el.closeBtn.addEventListener('click', () => this.close());
                this.el.cancelBtn.addEventListener('click', () => this.close());

                this.el.modal.addEventListener('click', event => {
                    if (event.target === this.el.modal) {
                        this.close();
                    }
                });

                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape' && this.isOpen()) {
                        this.close();
                    }
                });

                this.el.tabLibrary.addEventListener('click', () => this.showTab('library'));
                this.el.tabUpload.addEventListener('click', () => this.showTab('upload'));

                this.el.searchInput.addEventListener('input', () => this.handleSearchInput());

                this.el.prevBtn.addEventListener('click', () => this.goToPreviousPage());
                this.el.nextBtn.addEventListener('click', () => this.goToNextPage());

                this.el.selectBtn.addEventListener('click', () => this.confirmSelectedImage());

                this.el.fileInput.addEventListener('change', () => {
                    const file = this.el.fileInput.files?.[0];

                    if (file) {
                        this.setUploadFile(file);
                    }
                });

                this.el.uploadRights.addEventListener('change', () => {
                    this.syncCreditRequirement();
                    this.validateUploadForm();
                });

                [
                    this.el.uploadName,
                    this.el.uploadRights,
                    this.el.uploadAlt,
                    this.el.uploadCredit,
                    this.el.uploadConfirm,
                ].forEach(input => {
                    input.addEventListener('input', () => this.validateUploadForm());
                    input.addEventListener('change', () => this.validateUploadForm());
                });

                this.el.uploadSubmitBtn.addEventListener('click', () => this.submitUpload());

                this.el.dropZone.addEventListener('click', () => this.el.fileInput.click());

                this.el.dropZone.addEventListener('dragover', event => {
                    event.preventDefault();
                    this.el.dropZone.classList.add('il-drop-zone--over');
                });

                this.el.dropZone.addEventListener('dragleave', () => {
                    this.el.dropZone.classList.remove('il-drop-zone--over');
                });

                this.el.dropZone.addEventListener('drop', event => this.handleDrop(event));
            }

            open(blockId, currentCmsImageId = null) {
                this.state.resetForOpen(blockId);

                this.showModal();
                this.showTab('library');
                this.loadLibrary();
            }

            close() {
                this.hideModal();
            }

            showModal() {
                this.el.modal.style.display = 'grid';
                this.el.closeBtn.focus();
                this.trapFocus();
            }

            hideModal() {
                this.el.modal.style.display = 'none';

                this.state.resetForClose();
                this.releaseFocusTrap();
            }

            isOpen() {
                return this.el.modal.style.display !== 'none';
            }

            showTab(tab) {
                this.state.currentTab = tab;

                const isLibrary = tab === 'library';
                const isUpload = tab === 'upload';

                this.el.panelLibrary.style.display = isLibrary ? 'flex' : 'none';
                this.el.panelUpload.style.display = isUpload ? 'flex' : 'none';

                this.el.tabLibrary.classList.toggle('il-tab--active', isLibrary);
                this.el.tabUpload.classList.toggle('il-tab--active', isUpload);

                this.el.tabLibrary.setAttribute('aria-selected', String(isLibrary));
                this.el.tabUpload.setAttribute('aria-selected', String(isUpload));

                this.el.selectBtn.style.display = isLibrary ? '' : 'none';
                this.el.uploadSubmitBtn.style.display = isUpload ? '' : 'none';

                if (isUpload) {
                    this.validateUploadForm();
                }
            }

            handleSearchInput() {
                clearTimeout(this.state.searchTimer);

                this.state.currentPage = 1;

                this.state.searchTimer = setTimeout(() => {
                    this.loadLibrary();
                }, 350);
            }

            goToPreviousPage() {
                if (this.state.currentPage <= 1) {
                    return;
                }

                this.state.currentPage--;
                this.loadLibrary();
            }

            goToNextPage() {
                if (this.state.currentPage >= this.state.totalPages) {
                    return;
                }

                this.state.currentPage++;
                this.loadLibrary();
            }

            async loadLibrary() {
                const params = new URLSearchParams({
                    page: this.state.currentPage,
                    per_page: 24,
                });

                const search = this.el.searchInput.value.trim();

                if (search) {
                    params.set('search', search);
                }

                this.setLibraryStatus('loading', 'Loading your image library…');
                this.el.grid.innerHTML = '';
                this.el.pagination.style.display = 'none';
                this.el.detailPanel.style.display = 'none';
                this.el.selectBtn.disabled = true;

                this.abortPreviousLibraryRequest();

                const controller = new AbortController();
                this.state.abortController = controller;

                try {
                    const response = await fetch(`/api/${SITE}/open-collab/images?${params}`, {
                        method: 'GET',
                        headers: {
                            Authorization: `Bearer ${TOKEN()}`,
                            Accept: 'application/json',
                        },
                        signal: controller.signal,
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        this.handleLibraryError(response);
                        return;
                    }

                    const items = data.items ?? data.data ?? [];
                    const meta = data.pagination ?? data.meta ?? {};

                    this.renderGrid(items, meta);
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    this.setLibraryStatus(
                        'error',
                        'The image library is temporarily unavailable. Your article has not been changed.'
                    );
                }
            }

            abortPreviousLibraryRequest() {
                if (this.state.abortController) {
                    this.state.abortController.abort();
                }
            }

            handleLibraryError(response) {
                if (response.status === 403) {
                    this.setLibraryStatus(
                        'error',
                        'You do not have permission to browse the image library.'
                    );
                    return;
                }

                this.setLibraryStatus(
                    'error',
                    'The image library is temporarily unavailable. Your article has not been changed.'
                );
            }

            renderGrid(items, meta) {
                this.el.grid.innerHTML = '';
                this.clearLibraryStatus();

                this.state.totalPages = Number(
                    meta.total_pages
                    ?? meta.last_page
                    ?? 1
                );

                this.state.currentPage = Number(
                    meta.current_page
                    ?? 1
                );

                if (!items.length) {
                    this.renderEmptyLibrary();
                    return;
                }

                items.forEach(image => {
                    this.el.grid.appendChild(this.createGridItem(image));
                });

                this.renderPagination();
            }

            renderEmptyLibrary() {
                const search = this.el.searchInput.value.trim();

                this.setLibraryStatus(
                    'empty',
                    search ? 'No images match your search.' : 'You have not uploaded any images yet.'
                );

                if (!search) {
                    this.appendEmptyLibraryCta();
                }
            }

            createGridItem(image) {
                const isBlocked = this.isBlockedImage(image);

                const item = document.createElement('div');
                item.className = `il-grid-item${isBlocked ? ' il-grid-item--blocked' : ''}`;
                item.role = 'listitem';
                item.tabIndex = isBlocked ? -1 : 0;
                item.dataset.id = image.id;

                item.setAttribute(
                    'aria-label',
                    `${image.name}${isBlocked ? ' (rights not confirmed, cannot select)' : ''}`
                );

                item.innerHTML = `
                <img src="${this.escAttr(image.thumbnail_url || image.preview_url)}"
                     alt="${this.escAttr(image.name)}"
                     loading="lazy">
                <span class="il-grid-item__rights-badge">
                    ${this.escHtml(this.getRightsLabel(image.image_rights))}
                </span>
            `;

                if (!isBlocked) {
                    item.addEventListener('click', () => this.selectImage(image));
                    item.addEventListener('keydown', event => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            this.selectImage(image);
                        }
                    });
                }

                return item;
            }

            renderPagination() {
                if (this.state.totalPages <= 1) {
                    this.el.pagination.style.display = 'none';
                    return;
                }

                this.el.pagination.style.display = 'flex';
                this.el.prevBtn.disabled = this.state.currentPage <= 1;
                this.el.nextBtn.disabled = this.state.currentPage >= this.state.totalPages;
                this.el.pageIndicator.textContent =
                    `Page ${this.state.currentPage} of ${this.state.totalPages}`;
            }

            appendEmptyLibraryCta() {
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'oc-btn oc-btn--ghost oc-btn--sm';
                button.style.marginTop = '10px';
                button.textContent = 'Upload an image';

                button.addEventListener('click', () => this.showTab('upload'));

                this.el.status.appendChild(button);
            }

            selectImage(image) {
                this.state.selectedImage = image;

                this.el.grid.querySelectorAll('.il-grid-item').forEach(item => {
                    item.classList.toggle(
                        'il-grid-item--selected',
                        String(item.dataset.id) === String(image.id)
                    );
                });

                this.renderDetailPanel(image);
            }

            renderDetailPanel(image) {
                const isBlocked = this.isBlockedImage(image);

                this.el.detailImg.src = image.preview_url || image.thumbnail_url;
                this.el.detailImg.alt = image.name ?? '';

                this.el.detailName.textContent = image.name ?? '';

                this.el.detailDims.textContent =
                    image.width && image.height
                        ? `${image.width} × ${image.height}px`
                        : '';

                this.el.detailRights.textContent =
                    `Rights: ${this.getRightsLabel(image.image_rights)}`;

                if (image.credit) {
                    this.el.detailCredit.textContent = `📷 ${image.credit}`;
                    this.el.detailCredit.style.display = '';
                } else {
                    this.el.detailCredit.textContent = '';
                    this.el.detailCredit.style.display = 'none';
                }

                if (isBlocked) {
                    this.el.detailBlocked.textContent =
                        'This image has unconfirmed rights and cannot be selected.';
                    this.el.detailBlocked.style.display = '';
                } else {
                    this.el.detailBlocked.textContent = '';
                    this.el.detailBlocked.style.display = 'none';
                }

                this.el.detailPanel.style.display = 'grid';
                this.el.selectBtn.disabled = isBlocked;
            }

            confirmSelectedImage() {
                const image = this.state.selectedImage;

                if (!image || !this.state.activeBlockId) {
                    return;
                }

                this.confirmSelection(image);
            }

            confirmSelection(image) {
                if (typeof this.onSelect === 'function') {
                    this.onSelect(this.state.activeBlockId, image);
                }

                this.close();
            }

            setLibraryStatus(type, message) {
                this.el.status.className =
                    `il-status${type === 'error' ? ' il-status--error' : ''}`;

                this.el.status.innerHTML = this.escHtml(message);
                this.el.status.style.display = '';
            }

            clearLibraryStatus() {
                this.el.status.innerHTML = '';
                this.el.status.style.display = 'none';
            }

            handleDrop(event) {
                event.preventDefault();

                this.el.dropZone.classList.remove('il-drop-zone--over');

                const file = Array.from(event.dataTransfer?.files ?? [])
                    .find(candidate => candidate.type.startsWith('image/'));

                if (file) {
                    this.setUploadFile(file);
                }
            }

            setUploadFile(file) {
                this.state.pendingFile = file;

                this.el.dropZoneLabel.textContent = file.name;

                const preview = document.getElementById('il-upload-preview');
                const previewImg = document.getElementById('il-upload-preview-img');

                const reader = new FileReader();

                reader.onload = event => {
                    previewImg.src = event.target.result;
                    preview.style.display = 'block';
                };

                reader.readAsDataURL(file);

                if (!this.el.uploadName.value) {
                    this.el.uploadName.value =
                        file.name.replace(/\.[^.]+$/, '');
                }

                this.validateUploadForm();
            }

            syncCreditRequirement() {
                const needsCredit = RIGHTS_REQUIRING_CREDIT.has(this.el.uploadRights.value);

                this.el.creditRequiredNote.style.display = needsCredit ? '' : 'none';
                this.el.creditOptionalNote.style.display = needsCredit ? 'none' : '';
            }

            validateUploadForm() {
                const data = this.getUploadFormData();
                const needsCredit = RIGHTS_REQUIRING_CREDIT.has(data.image_rights);

                const isValid =
                    !!this.state.pendingFile &&
                    !!data.name &&
                    !!data.image_rights &&
                    !!data.alt_text &&
                    data.rights_confirmation === '1' &&
                    (!needsCredit || !!data.credit);

                this.el.uploadSubmitBtn.disabled = !isValid || this.state.isUploading;

                return isValid;
            }

            getUploadFormData() {
                return {
                    name: this.el.uploadName.value.trim(),
                    image_rights: this.el.uploadRights.value,
                    alt_text: this.el.uploadAlt.value.trim(),
                    credit: this.el.uploadCredit.value.trim(),
                    rights_confirmation: this.el.uploadConfirm.checked ? '1' : '0',
                    ai_generated: this.el.uploadAiGenerated.checked ? '1' : '0',
                    sponsored_content: this.el.uploadSponsored.checked ? '1' : '0',
                    affiliate_content: this.el.uploadAffiliate.checked ? '1' : '0',
                    contains_music: this.el.uploadContainsMusic.checked ? '1' : '0',
                    unclear_rights: this.el.uploadUnclearRights.checked ? '1' : '0',
                };
            }

            async submitUpload() {
                if (this.state.isUploading || !this.validateUploadForm()) {
                    return;
                }

                this.hideUploadErrors();
                this.setUploading(true);

                try {
                    const response = await fetch(`/api/${SITE}/open-collab/images`, {
                        method: 'POST',
                        headers: {
                            Authorization: `Bearer ${TOKEN()}`,
                            Accept: 'application/json',
                        },
                        body: this.buildUploadFormData(),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        this.showUploadErrors(data.errors ?? {
                            _: [data.message ?? 'Upload failed.'],
                        });
                        return;
                    }

                    const image = data.image ?? data.data ?? data;

                    this.confirmSelection(image);
                } catch {
                    this.showUploadErrors({
                        _: ['A network error occurred. Please try again.'],
                    });
                } finally {
                    this.setUploading(false);
                    this.validateUploadForm();
                }
            }

            buildUploadFormData() {
                const formData = new FormData();
                const data = this.getUploadFormData();

                formData.append('file', this.state.pendingFile);

                Object.entries(data).forEach(([key, value]) => {
                    formData.append(key, value);
                });

                return formData;
            }

            setUploading(isUploading) {
                this.state.isUploading = isUploading;
                this.el.uploadProgress.style.display = isUploading ? '' : 'none';
                this.el.uploadSubmitBtn.disabled = isUploading;
            }

            hideUploadErrors() {
                this.el.uploadErrors.textContent = '';
                this.el.uploadErrors.style.display = 'none';
            }

            showUploadErrors(errors) {
                this.el.uploadErrors.textContent = Object.values(errors).flat().join(' ');
                this.el.uploadErrors.style.display = '';
            }

            trapFocus() {
                this.releaseFocusTrap();

                this.state.preFocusEl = document.activeElement;

                const focusable = this.el.modal.querySelectorAll(
                    'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'
                );

                const first = focusable[0];
                const last = focusable[focusable.length - 1];

                if (!first || !last) {
                    return;
                }

                this.state.trapHandler = event => {
                    if (event.key !== 'Tab') {
                        return;
                    }

                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    }

                    if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                };

                this.el.modal.addEventListener('keydown', this.state.trapHandler);
            }

            releaseFocusTrap() {
                if (this.state.trapHandler) {
                    this.el.modal.removeEventListener('keydown', this.state.trapHandler);
                }

                if (this.state.preFocusEl && typeof this.state.preFocusEl.focus === 'function') {
                    this.state.preFocusEl.focus();
                }

                this.state.trapHandler = null;
                this.state.preFocusEl = null;
            }

            isBlockedImage(image) {
                return BLOCKING_RIGHTS.has(image.image_rights);
            }

            getRightsLabel(rights) {
                return RIGHTS_LABELS[rights] ?? rights ?? 'Unknown';
            }

            escHtml(value) {
                if (value == null) {
                    return '';
                }

                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            escAttr(value) {
                return this.escHtml(value ?? '');
            }
        }

        window.imageLibrary = new ImageLibrary();
    })();
</script>