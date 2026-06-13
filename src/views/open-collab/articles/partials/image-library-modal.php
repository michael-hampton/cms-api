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
                    aria-controls="il-panel-library"
                    onclick="window.imageLibrary.showTab('library')">
                Library
            </button>
            <button type="button"
                    class="il-tab"
                    role="tab"
                    id="il-tab-upload"
                    aria-selected="false"
                    aria-controls="il-panel-upload"
                    onclick="window.imageLibrary.showTab('upload')">
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
                 aria-label="Click or drag and drop an image to upload"
                 onclick="document.getElementById('il-file-input').click()"
                 ondragover="event.preventDefault();this.classList.add('il-drop-zone--over')"
                 ondragleave="this.classList.remove('il-drop-zone--over')"
                 ondrop="window.imageLibrary.handleDrop(event)">
                <svg viewBox="0 0 20 20" fill="currentColor" width="28" style="color:var(--slate-light);display:block;margin:0 auto 8px;">
                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                </svg>
                <div id="il-drop-zone-label" style="font-size:.82rem;color:var(--slate);">
                    Click or drag &amp; drop an image here
                </div>
            </div>
            <input type="file" id="il-file-input" accept="image/*" style="display:none;">

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
    (function() {
        'use strict';

        // ── State ─────────────────────────────────────────────────────────────
        let _activeBlockId       = null;
        let _currentTab          = 'library';
        let _selectedImage       = null;
        let _currentPage         = 1;
        let _totalPages          = 1;
        let _searchDebounce      = null;
        let _lastSearchXhr       = null;
        let _pendingFile         = null;
        let _isUploading         = false;

        // RIGHTS_LABELS and BLOCKING_RIGHTS match the ImageRightsCreditPolicy PHP class
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

        const RIGHTS_REQUIRING_CREDIT = [
            'contributor_owned','third_party_licensed','agency',
            'editorial_use_only','attribution_required','creative_commons',
        ];

        const BLOCKING_RIGHTS = ['unknown'];

        // ── Public API ────────────────────────────────────────────────────────
        window.imageLibrary = {

            /** @type {function(string, object): void} Override in editor to handle selection */
            onSelect: null,

            open(blockId, currentCmsImageId = null) {
                _activeBlockId   = blockId;
                _selectedImage   = null;
                _currentPage     = 1;
                showModal();
                showTab('library');
                loadLibrary();
            },

            close() {
                hideModal();
            },

            showTab,

            handleDrop(event) {
                event.preventDefault();
                document.getElementById('il-drop-zone').classList.remove('il-drop-zone--over');
                const file = Array.from(event.dataTransfer?.files ?? []).find(f => f.type.startsWith('image/'));
                if (file) setUploadFile(file);
            },
        };

        // ── Modal open/close ──────────────────────────────────────────────────
        function showModal() {
            const el = document.getElementById('image-library-modal');
            el.style.display = 'grid';
            document.getElementById('il-close-btn').focus();
            trapFocus(el);
        }

        function hideModal() {
            document.getElementById('image-library-modal').style.display = 'none';
            _selectedImage = null;
            _activeBlockId = null;
            releaseFocusTrap();
        }

        document.getElementById('il-close-btn').addEventListener('click', hideModal);
        document.getElementById('il-cancel-btn').addEventListener('click', hideModal);
        document.getElementById('image-library-modal').addEventListener('click', function(e) {
            if (e.target === this) hideModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('image-library-modal').style.display !== 'none') {
                hideModal();
            }
        });

        // ── Tab switching ─────────────────────────────────────────────────────
        function showTab(tab) {
            _currentTab = tab;
            document.getElementById('il-panel-library').style.display = tab === 'library' ? 'flex' : 'none';
            document.getElementById('il-panel-upload').style.display  = tab === 'upload'  ? 'flex' : 'none';
            document.getElementById('il-tab-library').classList.toggle('il-tab--active', tab === 'library');
            document.getElementById('il-tab-upload').classList.toggle('il-tab--active',  tab === 'upload');
            document.getElementById('il-tab-library').setAttribute('aria-selected', tab === 'library');
            document.getElementById('il-tab-upload').setAttribute('aria-selected',  tab === 'upload');
            document.getElementById('il-select-btn').style.display       = tab === 'library' ? '' : 'none';
            document.getElementById('il-upload-submit-btn').style.display = tab === 'upload'  ? '' : 'none';
        }

        // ── Library: search + pagination ──────────────────────────────────────
        document.getElementById('il-search-input').addEventListener('input', function() {
            clearTimeout(_searchDebounce);
            _currentPage  = 1;
            _searchDebounce = setTimeout(loadLibrary, 350);
        });

        document.getElementById('il-prev-btn').addEventListener('click', function() {
            if (_currentPage > 1) { _currentPage--; loadLibrary(); }
        });

        document.getElementById('il-next-btn').addEventListener('click', function() {
            if (_currentPage < _totalPages) { _currentPage++; loadLibrary(); }
        });

        function loadLibrary() {
            const search  = document.getElementById('il-search-input').value.trim();
            const params  = new URLSearchParams({ page: _currentPage, per_page: 24 });
            if (search) params.set('search', search);

            setLibraryStatus('loading', 'Loading your image library…');
            document.getElementById('il-grid').innerHTML = '';
            document.getElementById('il-pagination').style.display = 'none';
            document.getElementById('il-detail-panel').style.display = 'none';

            // Cancel any in-flight search
            if (_lastSearchXhr) { _lastSearchXhr.abort(); }

            const xhr = new XMLHttpRequest();
            _lastSearchXhr = xhr;
            xhr.open('GET', `/api/${SITE}/open-collab/images?${params}`);
            xhr.setRequestHeader('Authorization', `Bearer ${TOKEN()}`);
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.onload = function() {
                if (xhr.status === 0) return; // aborted
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        renderGrid(data.data ?? [], data.meta ?? {});
                    } catch {
                        setLibraryStatus('error', 'Failed to load image library.');
                    }
                } else if (xhr.status === 403) {
                    setLibraryStatus('error', 'You do not have permission to browse the image library.');
                } else {
                    setLibraryStatus('error', 'The image library is temporarily unavailable. Your article has not been changed.');
                }
            };

            xhr.onerror = function() {
                setLibraryStatus('error', 'The image library is temporarily unavailable. Your article has not been changed.');
            };

            xhr.send();
        }

        function renderGrid(items, meta) {
            const grid = document.getElementById('il-grid');
            grid.innerHTML = '';
            clearLibraryStatus();

            _totalPages = meta.last_page ?? 1;
            _currentPage = meta.current_page ?? 1;

            if (!items.length) {
                const search = document.getElementById('il-search-input').value.trim();
                setLibraryStatus('empty', search
                    ? 'No images match your search.'
                    : 'You have not uploaded any images yet.'
                );
                if (!search) {
                    appendEmptyLibraryCta();
                }
                return;
            }

            items.forEach(img => {
                const isBlocked = BLOCKING_RIGHTS.includes(img.image_rights);
                const div = document.createElement('div');
                div.className  = 'il-grid-item' + (isBlocked ? ' il-grid-item--blocked' : '');
                div.role       = 'listitem';
                div.tabIndex   = isBlocked ? -1 : 0;
                div.dataset.id = img.id;
                div.setAttribute('aria-label', img.name + (isBlocked ? ' (rights not confirmed, cannot select)' : ''));

                div.innerHTML = `
                <img src="${escAttr(img.thumbnail_url || img.preview_url)}"
                     alt="${escAttr(img.name)}"
                     loading="lazy">
                <span class="il-grid-item__rights-badge">${escHtml(RIGHTS_LABELS[img.image_rights] ?? img.image_rights ?? '')}</span>`;

                if (!isBlocked) {
                    div.addEventListener('click',   () => selectImage(img));
                    div.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectImage(img); } });
                }
                grid.appendChild(div);
            });

            // Pagination
            if (_totalPages > 1) {
                document.getElementById('il-pagination').style.display = 'flex';
                document.getElementById('il-prev-btn').disabled = _currentPage <= 1;
                document.getElementById('il-next-btn').disabled = _currentPage >= _totalPages;
                document.getElementById('il-page-indicator').textContent = `Page ${_currentPage} of ${_totalPages}`;
            }
        }

        function appendEmptyLibraryCta() {
            const status = document.getElementById('il-library-status');
            const cta = document.createElement('button');
            cta.type      = 'button';
            cta.className = 'oc-btn oc-btn--ghost oc-btn--sm';
            cta.style.marginTop = '10px';
            cta.textContent = 'Upload an image';
            cta.addEventListener('click', () => showTab('upload'));
            status.appendChild(cta);
        }

        // ── Image selection ───────────────────────────────────────────────────
        function selectImage(img) {
            _selectedImage = img;

            // Highlight selected grid item
            document.querySelectorAll('.il-grid-item').forEach(el => {
                el.classList.toggle('il-grid-item--selected', el.dataset.id == img.id);
            });

            // Populate detail panel
            const isBlocked = BLOCKING_RIGHTS.includes(img.image_rights);
            document.getElementById('il-detail-img').src    = img.preview_url || img.thumbnail_url;
            document.getElementById('il-detail-img').alt    = img.name;
            document.getElementById('il-detail-name').textContent = img.name;
            document.getElementById('il-detail-dims').textContent =
                img.width && img.height ? `${img.width} × ${img.height}px` : '';
            document.getElementById('il-detail-rights').textContent =
                'Rights: ' + (RIGHTS_LABELS[img.image_rights] ?? img.image_rights ?? 'Unknown');

            const creditEl = document.getElementById('il-detail-credit');
            if (img.credit) {
                creditEl.textContent = '📷 ' + img.credit;
                creditEl.style.display = '';
            } else {
                creditEl.style.display = 'none';
            }

            const blockedEl = document.getElementById('il-detail-blocked');
            if (isBlocked) {
                blockedEl.textContent = 'This image has unconfirmed rights and cannot be selected.';
                blockedEl.style.display = '';
            } else {
                blockedEl.style.display = 'none';
            }

            document.getElementById('il-detail-panel').style.display = 'grid';
            document.getElementById('il-select-btn').disabled = isBlocked;
        }

        document.getElementById('il-select-btn').addEventListener('click', function() {
            if (!_selectedImage || !_activeBlockId) return;
            confirmSelection(_selectedImage);
        });

        function confirmSelection(img) {
            if (typeof window.imageLibrary.onSelect === 'function') {
                window.imageLibrary.onSelect(_activeBlockId, img);
            }
            hideModal();
        }

        // ── Library status helpers ────────────────────────────────────────────
        function setLibraryStatus(type, message) {
            const el = document.getElementById('il-library-status');
            el.className  = 'il-status' + (type === 'error' ? ' il-status--error' : '');
            el.innerHTML  = escHtml(message);
            el.style.display = '';
        }

        function clearLibraryStatus() {
            const el = document.getElementById('il-library-status');
            el.style.display = 'none';
            el.innerHTML = '';
        }

        // ── Upload tab ────────────────────────────────────────────────────────
        document.getElementById('il-file-input').addEventListener('change', function() {
            if (this.files[0]) setUploadFile(this.files[0]);
        });

        function setUploadFile(file) {
            _pendingFile = file;
            document.getElementById('il-drop-zone-label').textContent = file.name;
            document.getElementById('il-upload-name').value =
                document.getElementById('il-upload-name').value || file.name.replace(/\.[^.]+$/, '');
            validateUploadForm();
        }

        // Credit required indicator toggled by rights selection
        document.getElementById('il-upload-rights').addEventListener('change', function() {
            const needsCredit = RIGHTS_REQUIRING_CREDIT.includes(this.value);
            document.getElementById('il-credit-required-note').style.display = needsCredit ? '' : 'none';
            document.getElementById('il-credit-optional-note').style.display = needsCredit ? 'none' : '';
            validateUploadForm();
        });

        ['il-upload-name','il-upload-rights','il-upload-alt','il-upload-credit','il-upload-rights-confirm']
            .forEach(id => document.getElementById(id).addEventListener('input', validateUploadForm));
        document.getElementById('il-upload-rights-confirm').addEventListener('change', validateUploadForm);

        function validateUploadForm() {
            const hasFile    = !!_pendingFile;
            const name       = document.getElementById('il-upload-name').value.trim();
            const rights     = document.getElementById('il-upload-rights').value;
            const alt        = document.getElementById('il-upload-alt').value.trim();
            const credit     = document.getElementById('il-upload-credit').value.trim();
            const confirmed  = document.getElementById('il-upload-rights-confirm').checked;
            const needCredit = RIGHTS_REQUIRING_CREDIT.includes(rights);

            const valid = hasFile && name && rights && alt && confirmed && (!needCredit || credit);
            document.getElementById('il-upload-submit-btn').disabled = !valid;
            return valid;
        }

        document.getElementById('il-upload-submit-btn').addEventListener('click', submitUpload);

        async function submitUpload() {
            if (_isUploading || !validateUploadForm()) return;

            const errBox = document.getElementById('il-upload-errors');
            errBox.style.display = 'none';

            _isUploading = true;
            document.getElementById('il-upload-submit-btn').disabled = true;
            document.getElementById('il-upload-progress').style.display = '';

            const formData = new FormData();
            formData.append('file',               _pendingFile);
            formData.append('name',               document.getElementById('il-upload-name').value.trim());
            formData.append('image_rights',       document.getElementById('il-upload-rights').value);
            formData.append('alt_text',           document.getElementById('il-upload-alt').value.trim());
            formData.append('credit',             document.getElementById('il-upload-credit').value.trim());
            formData.append('rights_confirmation', document.getElementById('il-upload-rights-confirm').checked ? '1' : '0');
            formData.append('ai_generated',       document.getElementById('il-upload-ai-generated').checked ? '1' : '0');
            formData.append('sponsored_content',  document.getElementById('il-upload-sponsored').checked ? '1' : '0');
            formData.append('affiliate_content',  document.getElementById('il-upload-affiliate').checked ? '1' : '0');

            try {
                const res = await fetch(`/api/${SITE}/open-collab/images`, {
                    method:  'POST',
                    headers: { Authorization: `Bearer ${TOKEN()}`, Accept: 'application/json' },
                    body:    formData,
                });
                const data = await res.json();

                if (res.ok) {
                    const img = data.data;
                    confirmSelection(img);
                } else {
                    showUploadErrors(data.errors ?? { _: [data.message ?? 'Upload failed.'] });
                }
            } catch {
                showUploadErrors({ _: ['A network error occurred. Please try again.'] });
            } finally {
                _isUploading = false;
                document.getElementById('il-upload-progress').style.display = 'none';
                validateUploadForm();
            }
        }

        function showUploadErrors(errors) {
            const errBox = document.getElementById('il-upload-errors');
            const messages = Object.values(errors).flat().join(' ');
            errBox.textContent = messages;
            errBox.style.display = '';
            // Keep entered metadata intact — do not clear form on error
        }

        // ── Focus trap ────────────────────────────────────────────────────────
        let _preFocusEl = null;
        let _trapHandler = null;

        function trapFocus(el) {
            _preFocusEl = document.activeElement;
            const focusable = el.querySelectorAll(
                'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'
            );
            const first = focusable[0];
            const last  = focusable[focusable.length - 1];
            _trapHandler = function(e) {
                if (e.key !== 'Tab') return;
                if (e.shiftKey) {
                    if (document.activeElement === first) { e.preventDefault(); last.focus(); }
                } else {
                    if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
                }
            };
            el.addEventListener('keydown', _trapHandler);
        }

        function releaseFocusTrap() {
            const el = document.getElementById('image-library-modal');
            if (_trapHandler) el.removeEventListener('keydown', _trapHandler);
            if (_preFocusEl) _preFocusEl.focus();
            _trapHandler = null;
            _preFocusEl  = null;
        }

        // ── Utilities ─────────────────────────────────────────────────────────
        function escHtml(str) {
            if (str == null) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function escAttr(str) { return escHtml(str ?? ''); }

    })();
</script>