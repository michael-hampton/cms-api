<style>
    /* =============================================================================
    Badge Admin — BEM stylesheet
    ============================================================================= */

    /* -----------------------------------------------------------------------------
       Tokens
       ----------------------------------------------------------------------------- */

    :root {
        --color-bg: #f8f9fa;
        --color-surface: #ffffff;
        --color-border: #dee2e6;
        --color-border-focus: #4a90e2;

        --color-text: #212529;
        --color-text-muted: #6c757d;

        --color-primary: #4a90e2;
        --color-primary-hover: #357abd;
        --color-primary-text: #ffffff;

        --color-secondary: #6c757d;
        --color-secondary-hover: #545b62;
        --color-secondary-text: #ffffff;

        --color-danger: #dc3545;
        --color-danger-hover: #b02a37;
        --color-danger-text: #ffffff;

        --color-success: #198754;
        --color-valid: #198754;
        --color-invalid: #dc3545;

        --color-row-inactive: #f1f3f5;
        --color-preview-bg: #f1f3f5;

        --radius: 6px;
        --radius-sm: 4px;
        --shadow: 0 1px 4px rgba(0, 0, 0, .08);
        --shadow-modal: 0 8px 32px rgba(0, 0, 0, .18);

        --font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        --font-size-base: 14px;
        --font-size-sm: 12px;
        --font-size-mono: 13px;

        --spacing-xs: 4px;
        --spacing-sm: 8px;
        --spacing-md: 16px;
        --spacing-lg: 24px;
        --spacing-xl: 32px;
    }

    /* -----------------------------------------------------------------------------
       Reset / base
       ----------------------------------------------------------------------------- */

    *, *::before, *::after {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        background: var(--color-bg);
        color: var(--color-text);
        font-family: var(--font-family);
        font-size: var(--font-size-base);
        line-height: 1.5;
    }

    /* -----------------------------------------------------------------------------
       Page shell
       ----------------------------------------------------------------------------- */

    .badge-admin-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: var(--spacing-xl) var(--spacing-lg);
    }

    /* SPA root — just a block container, no layout tricks needed */
    .badge-admin {
        display: block;
    }

    /* -----------------------------------------------------------------------------
       Shared buttons
       ----------------------------------------------------------------------------- */

    .btn {
        display: inline-flex;
        align-items: center;
        gap: var(--spacing-xs);
        padding: 8px 16px;
        border: none;
        border-radius: var(--radius);
        font-family: inherit;
        font-size: var(--font-size-base);
        font-weight: 500;
        line-height: 1;
        cursor: pointer;
        transition: background 120ms, opacity 120ms;
        white-space: nowrap;
    }

    .btn:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .btn-primary {
        background: var(--color-primary);
        color: var(--color-primary-text);
    }

    .btn-secondary {
        background: var(--color-secondary);
        color: var(--color-secondary-text);
    }

    .btn-danger {
        background: var(--color-danger);
        color: var(--color-danger-text);
    }

    .btn-primary:hover:not(:disabled) {
        background: var(--color-primary-hover);
    }

    .btn-secondary:hover:not(:disabled) {
        background: var(--color-secondary-hover);
    }

    .btn-danger:hover:not(:disabled) {
        background: var(--color-danger-hover);
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: var(--font-size-sm);
    }

    /* -----------------------------------------------------------------------------
       BadgeList
       ----------------------------------------------------------------------------- */

    .badge-list {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .badge-list__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: var(--spacing-md) var(--spacing-lg);
        border-bottom: 1px solid var(--color-border);
    }

    .badge-list__header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }

    .badge-list__loading,
    .badge-list__empty {
        padding: var(--spacing-xl);
        text-align: center;
        color: var(--color-text-muted);
        margin: 0;
    }

    .badge-list__errors {
        margin: var(--spacing-md) var(--spacing-lg);
        padding: var(--spacing-sm) var(--spacing-md);
        background: #fff5f5;
        border: 1px solid #fca5a5;
        border-radius: var(--radius);
        color: var(--color-danger);
    }

    .badge-list__errors p {
        margin: 2px 0;
        font-size: var(--font-size-sm);
    }

    .badge-list__table {
        width: 100%;
        border-collapse: collapse;
    }

    .badge-list__table thead {
        background: var(--color-bg);
    }

    .badge-list__table th {
        padding: var(--spacing-sm) var(--spacing-md);
        text-align: left;
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--color-text-muted);
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid var(--color-border);
    }

    .badge-list__table td {
        padding: var(--spacing-sm) var(--spacing-md);
        border-bottom: 1px solid var(--color-border);
        vertical-align: middle;
    }

    .badge-list__table tbody tr:last-child td {
        border-bottom: none;
    }

    .badge-list__table tbody tr:hover {
        background: #f8f9fa;
    }

    .badge-list__row--inactive td {
        background: var(--color-row-inactive);
        color: var(--color-text-muted);
    }

    .badge-list__icon {
        font-size: 20px;
        width: 48px;
    }

    .badge-list__name {
        font-weight: 500;
    }

    .badge-list__points,
    .badge-list__criteria,
    .badge-list__active {
        color: var(--color-text-muted);
    }

    .badge-list__actions {
        display: flex;
        gap: var(--spacing-xs);
    }

    .badge-list__pagination {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-md) var(--spacing-lg);
        border-top: 1px solid var(--color-border);
        background: var(--color-bg);
    }

    .badge-list__page-info {
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
        flex: 1;
        text-align: center;
    }

    /* -----------------------------------------------------------------------------
       Modal — backdrop + dialog
       ----------------------------------------------------------------------------- */

    .badge-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: var(--spacing-md);
        /* Smooth in */
        animation: backdrop-in 150ms ease;
    }

    @keyframes backdrop-in {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .badge-modal {
        background: var(--color-surface);
        border-radius: var(--radius);
        box-shadow: var(--shadow-modal);
        width: 100%;
        max-width: 640px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        animation: modal-in 150ms ease;
        overflow: hidden;
    }

    @keyframes modal-in {
        from {
            transform: translateY(12px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .badge-modal__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: var(--spacing-md) var(--spacing-lg);
        border-bottom: 1px solid var(--color-border);
        flex-shrink: 0;
    }

    .badge-modal__title {
        margin: 0;
        font-size: 17px;
        font-weight: 600;
    }

    .badge-modal__close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: var(--color-text-muted);
        padding: 4px 8px;
        border-radius: var(--radius-sm);
        line-height: 1;
        transition: background 120ms, color 120ms;
    }

    .badge-modal__close:hover {
        background: var(--color-bg);
        color: var(--color-text);
    }

    .badge-modal__body {
        padding: var(--spacing-lg);
        overflow-y: auto;
        flex: 1;
    }

    .badge-modal__footer {
        display: flex;
        justify-content: flex-end;
        gap: var(--spacing-sm);
        padding: var(--spacing-md) var(--spacing-lg);
        border-top: 1px solid var(--color-border);
        flex-shrink: 0;
        background: var(--color-bg);
    }

    /* Prevent body scroll when modal is open */
    body:has(.badge-modal-backdrop) {
        overflow: hidden;
    }

    /* -----------------------------------------------------------------------------
       BadgeForm (inside modal body)
       ----------------------------------------------------------------------------- */

    .badge-form__errors {
        margin-bottom: var(--spacing-md);
        padding: var(--spacing-sm) var(--spacing-md);
        background: #fff5f5;
        border: 1px solid #fca5a5;
        border-radius: var(--radius);
        color: var(--color-danger);
    }

    .badge-form__errors--hidden {
        display: none;
    }

    .badge-form__errors p {
        margin: 2px 0;
        font-size: var(--font-size-sm);
    }

    .badge-form__body {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }

    /* Two-column grid for name + slug */
    .badge-form__grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--spacing-md);
    }

    .badge-form__field {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs);
    }

    .badge-form__field--checkbox {
        flex-direction: row;
        align-items: center;
        gap: var(--spacing-sm);
    }

    .badge-form__label {
        font-weight: 500;
        font-size: var(--font-size-sm);
        color: var(--color-text);
    }

    .badge-form__input,
    .badge-form__textarea {
        padding: 8px 12px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-size: var(--font-size-base);
        color: var(--color-text);
        background: var(--color-surface);
        transition: border-color 120ms;
        width: 100%;
    }

    .badge-form__input:focus,
    .badge-form__textarea:focus {
        outline: none;
        border-color: var(--color-border-focus);
        box-shadow: 0 0 0 3px rgba(74, 144, 226, .15);
    }

    .badge-form__textarea {
        resize: vertical;
        min-height: 72px;
    }

    .badge-form__criteria > .badge-form__label {
        display: block;
        margin-bottom: var(--spacing-xs);
    }

    /* -----------------------------------------------------------------------------
       BadgeCriteriaBuilder
       ----------------------------------------------------------------------------- */

    .criteria-builder {
        border: 1px solid var(--color-border);
        border-radius: var(--radius);
        overflow: hidden;
    }

    .criteria-rows {
        display: flex;
        flex-direction: column;
    }

    .criteria-empty {
        padding: var(--spacing-md);
        margin: 0;
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
        text-align: center;
        background: var(--color-bg);
    }

    .criteria-row {
        display: grid;
        grid-template-columns: 2fr 1.2fr 1fr auto;
        gap: var(--spacing-sm);
        align-items: center;
        padding: var(--spacing-sm) var(--spacing-md);
        border-bottom: 1px solid var(--color-border);
        background: var(--color-surface);
    }

    .criteria-row:last-child {
        border-bottom: none;
    }

    .criteria-row:nth-child(even) {
        background: #fafbfc;
    }

    .criteria-row__hint {
        grid-column: 1 / -1;
        font-size: var(--font-size-sm);
        color: var(--color-invalid);
    }

    .criteria-row__select,
    .criteria-row__value {
        padding: 7px 10px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-size: var(--font-size-base);
        color: var(--color-text);
        background: var(--color-surface);
        width: 100%;
        transition: border-color 120ms;
    }

    .criteria-row__select {
        cursor: pointer;
    }

    .criteria-row__select:focus,
    .criteria-row__value:focus {
        outline: none;
        border-color: var(--color-border-focus);
        box-shadow: 0 0 0 3px rgba(74, 144, 226, .15);
    }

    .criteria-row__remove {
        flex-shrink: 0;
    }

    .criteria-add {
        display: block;
        width: 100%;
        border-radius: 0;
        border-top: 1px solid var(--color-border);
        background: var(--color-bg);
        color: var(--color-text);
        text-align: center;
        font-size: var(--font-size-sm);
        padding: var(--spacing-sm) var(--spacing-md);
    }

    .criteria-add:hover:not(:disabled) {
        background: #e9ecef;
    }

    .criteria-preview {
        border-top: 1px solid var(--color-border);
        background: var(--color-preview-bg);
        padding: var(--spacing-sm) var(--spacing-md);
    }

    .criteria-preview__label {
        margin: 0 0 var(--spacing-xs);
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
        font-weight: 500;
    }

    .criteria-preview__json {
        margin: 0;
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        font-size: var(--font-size-mono);
        color: var(--color-text);
        white-space: pre-wrap;
        word-break: break-all;
        max-height: 120px;
        overflow-y: auto;
    }

    .criteria-status {
        margin: 0;
        padding: var(--spacing-sm) var(--spacing-md);
        font-size: var(--font-size-sm);
        font-weight: 500;
        border-top: 1px solid var(--color-border);
    }

    .criteria-status--valid {
        color: var(--color-valid);
        background: #f0fdf4;
    }

    .criteria-status--invalid {
        color: var(--color-invalid);
        background: #fff5f5;
    }

    /* -----------------------------------------------------------------------------
       Responsive
       ----------------------------------------------------------------------------- */

    @media (max-width: 640px) {
        .badge-modal {
            max-height: 100vh;
            border-radius: 0;
        }

        .badge-modal-backdrop {
            padding: 0;
            align-items: flex-end;
        }

        .badge-form__grid {
            grid-template-columns: 1fr;
        }

        .criteria-row {
            grid-template-columns: 1fr 1fr;
        }

        .criteria-row__value {
            grid-column: 1 / -1;
        }

        .criteria-row__remove {
            grid-column: 2;
            grid-row: 1;
            justify-self: end;
        }

        .badge-list__table th:nth-child(3),
        .badge-list__table td:nth-child(3),
        .badge-list__table th:nth-child(4),
        .badge-list__table td:nth-child(4) {
            display: none;
        }
    }
</style>

<div id="badge-admin-root" class="badge-admin-page">
    <p class="badge-admin-page__loading">Loading badge admin…</p>
</div>

<script>
    /**
     * BadgeState
     *
     * Centralised, observable state for the badge admin SPA.
     * Components subscribe via onChange() and receive the full state snapshot
     * on every mutation — no partial diffs.
     */
    class BadgeState {
        #state = {
            badges: [],
            selectedBadge: null,
            loading: false,
            mode: 'list',      // 'list' | 'create' | 'edit' | 'view'
            errors: [],
            pagination: {
                currentPage: 1,
                lastPage: 1,
                total: 0,
                perPage: 20,
            },
        };

        #listeners = [];

        // -------------------------------------------------------------------------
        // Subscription
        // -------------------------------------------------------------------------

        onChange(listener) {
            this.#listeners.push(listener);
            return () => {
                this.#listeners = this.#listeners.filter(l => l !== listener);
            };
        }

        #notify() {
            const snapshot = this.snapshot();
            this.#listeners.forEach(l => l(snapshot));
        }

        snapshot() {
            return structuredClone(this.#state);
        }

        // -------------------------------------------------------------------------
        // Mutations
        // -------------------------------------------------------------------------

        setBadges(badges, pagination) {
            this.#state.badges = badges;
            if (pagination) this.#state.pagination = pagination;
            this.#notify();
        }

        setSelectedBadge(badge) {
            this.#state.selectedBadge = badge;
            this.#notify();
        }

        setLoading(loading) {
            this.#state.loading = loading;
            this.#notify();
        }

        setMode(mode) {
            this.#state.mode = mode;
            this.#notify();
        }

        setErrors(errors) {
            this.#state.errors = Array.isArray(errors) ? errors : [errors];
            this.#notify();
        }

        clearErrors() {
            this.#state.errors = [];
            this.#notify();
        }

        addBadge(badge) {
            this.#state.badges = [badge, ...this.#state.badges];
            this.#state.pagination.total += 1;
            this.#notify();
        }

        replaceBadge(updated) {
            this.#state.badges = this.#state.badges.map(b =>
                b.id === updated.id ? updated : b
            );
            if (this.#state.selectedBadge?.id === updated.id) {
                this.#state.selectedBadge = updated;
            }
            this.#notify();
        }

        removeBadge(id) {
            this.#state.badges = this.#state.badges.filter(b => b.id !== id);
            this.#state.pagination.total = Math.max(0, this.#state.pagination.total - 1);
            if (this.#state.selectedBadge?.id === id) {
                this.#state.selectedBadge = null;
            }
            this.#notify();
        }

        openCreate() {
            this.#state.selectedBadge = null;
            this.#state.errors = [];
            this.#state.mode = 'create';
            this.#notify();
        }

        openEdit(badge) {
            this.#state.selectedBadge = structuredClone(badge);
            this.#state.errors = [];
            this.#state.mode = 'edit';
            this.#notify();
        }

        backToList() {
            this.#state.selectedBadge = null;
            this.#state.errors = [];
            this.#state.mode = 'list';
            this.#notify();
        }
    }

    /**
     * BadgeApiService
     *
     * Thin HTTP wrapper for the badge admin endpoints.
     * All methods return the parsed response body on success
     * or throw an Error with a human-readable message on failure.
     */
    class BadgeApiService {
        /**
         * @param {string} baseUrl  e.g. '/admin/badges'
         * @param {string} csrfToken
         */
        constructor(baseUrl, csrfToken) {
            this.baseUrl = baseUrl.replace(/\/$/, '');
            this.csrfToken = csrfToken;
        }

        // -------------------------------------------------------------------------
        // Public API
        // -------------------------------------------------------------------------

        async list(page = 1) {
            return this.#get(`?page=${page}`);
        }

        async show(id) {
            return this.#get(`/${id}`);
        }

        async create(payload) {
            return this.#post('', payload);
        }

        async update(id, payload) {
            return this.#put(`/${id}`, payload);
        }

        async destroy(id) {
            return this.#delete(`/${id}`);
        }

        // -------------------------------------------------------------------------
        // Private HTTP helpers
        // -------------------------------------------------------------------------

        async #get(path) {
            const res = await fetch(`${this.baseUrl}${path}`, {
                headers: {Accept: 'application/json'},
            });
            return this.#handle(res);
        }

        async #post(path, body) {
            const res = await fetch(`${this.baseUrl}${path}`, {
                method: 'POST',
                headers: this.#jsonHeaders(),
                body: JSON.stringify(body),
            });
            return this.#handle(res);
        }

        async #put(path, body) {
            const res = await fetch(`${this.baseUrl}${path}`, {
                method: 'PUT',
                headers: this.#jsonHeaders(),
                body: JSON.stringify(body),
            });
            return this.#handle(res);
        }

        async #delete(path) {
            const res = await fetch(`${this.baseUrl}${path}`, {
                method: 'DELETE',
                headers: this.#jsonHeaders(),
            });
            return this.#handle(res);
        }

        #jsonHeaders() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
            };
        }

        async #handle(res) {
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                const message = data?.message
                    ?? data?.error
                    ?? `Request failed: ${res.status}`;
                const err = new Error(message);
                err.status = res.status;
                err.errors = data?.errors ?? {};
                throw err;
            }

            return data;
        }
    }

    /**
     * BadgeCriteriaBuilder
     *
     * Visual criteria builder. Renders a row per rule:
     *   [ Type ▼ ] [ Operator ▼ ] [ Value ] [ Remove ]
     *
     * No raw JSON editing — ever.
     *
     * Key constraint: value inputs are NEVER re-rendered on keystroke.
     * Only the preview <pre> and status <p> are updated in-place,
     * so the cursor position is never lost.
     */

    const CRITERIA_TYPES = [
        {value: 'comments_count', label: 'Comments posted'},
        {value: 'pages_read', label: 'Pages read'},
        {value: 'likes_given', label: 'Likes given'},
        {value: 'member_days', label: 'Days as member'},
        {value: 'orders_count', label: 'Orders completed'},
        {value: 'total_spent', label: 'Total spent (£)'},
    ];

    const OPERATORS = [
        {value: '>=', label: '≥ at least'},
        {value: '>', label: '> more than'},
        {value: '=', label: '= exactly'},
        {value: '<=', label: '≤ at most'},
        {value: '<', label: '< less than'},
    ];

    class BadgeCriteriaBuilder {
        #container;
        #onChange;
        #rules = [];
        #previewEl = null;
        #statusEl = null;
        #rowListEl = null;

        constructor(container, onChange) {
            this.#container = container;
            this.#onChange = onChange;
            this.#buildShell();
            this.#renderRows();
            this.#updateLiveRegions();
        }

        // -------------------------------------------------------------------------
        // Public
        // -------------------------------------------------------------------------

        setValue(rules) {
            this.#rules = (rules ?? []).map(r => ({...r}));
            this.#renderRows();
            this.#updateLiveRegions();
        }

        getValue() {
            return this.#rules.map(r => ({...r}));
        }

        isValid() {
            return this.#rules.length > 0 && this.#rules.every(r => this.#ruleIsValid(r));
        }

        // -------------------------------------------------------------------------
        // Shell — built once, never torn down
        // -------------------------------------------------------------------------

        #buildShell() {
            this.#container.innerHTML = '';

            const wrapper = document.createElement('div');
            wrapper.className = 'criteria-builder';

            this.#rowListEl = document.createElement('div');
            this.#rowListEl.className = 'criteria-rows';
            wrapper.appendChild(this.#rowListEl);

            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'btn btn-secondary criteria-add';
            addBtn.textContent = '+ Add Rule';
            addBtn.addEventListener('click', () => this.#addRule());
            wrapper.appendChild(addBtn);

            const previewWrap = document.createElement('div');
            previewWrap.className = 'criteria-preview';

            const previewLabel = document.createElement('p');
            previewLabel.className = 'criteria-preview__label';
            previewLabel.textContent = 'JSON preview (read-only)';

            this.#previewEl = document.createElement('pre');
            this.#previewEl.className = 'criteria-preview__json';

            previewWrap.appendChild(previewLabel);
            previewWrap.appendChild(this.#previewEl);
            wrapper.appendChild(previewWrap);

            this.#statusEl = document.createElement('p');
            this.#statusEl.className = 'criteria-status';
            wrapper.appendChild(this.#statusEl);

            this.#container.appendChild(wrapper);
        }

        // -------------------------------------------------------------------------
        // Row rendering — only called on add / remove / select change
        // Value <input> nodes are never recreated mid-edit
        // -------------------------------------------------------------------------

        #renderRows() {
            this.#rowListEl.innerHTML = '';

            if (this.#rules.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'criteria-empty';
                empty.textContent = 'No criteria yet — add at least one rule.';
                this.#rowListEl.appendChild(empty);
                return;
            }

            this.#rules.forEach((rule, index) => {
                this.#rowListEl.appendChild(this.#buildRow(rule, index));
            });
        }

        #buildRow(rule, index) {
            const row = document.createElement('div');
            row.className = 'criteria-row';
            row.dataset.index = String(index);

            row.appendChild(this.#buildSelect(
                CRITERIA_TYPES,
                rule.type ?? '',
                `criteria[${index}][type]`,
                (val) => this.#onSelectChange(index, 'type', val),
            ));

            row.appendChild(this.#buildSelect(
                OPERATORS,
                rule.operator ?? '>=',
                `criteria[${index}][operator]`,
                (val) => this.#onSelectChange(index, 'operator', val),
            ));

            // Value input — `input` event updates rules + live regions only,
            // never re-renders rows, so cursor is never displaced.
            const valueInput = document.createElement('input');
            valueInput.type = 'number';
            valueInput.name = `criteria[${index}][value]`;
            valueInput.className = 'criteria-row__value';
            valueInput.placeholder = 'Value';
            valueInput.min = '0';
            valueInput.step = '1';
            valueInput.value = (rule.value !== '' && rule.value !== undefined) ? rule.value : '';
            valueInput.addEventListener('input', (e) => {
                const raw = e.target.value;
                this.#rules[index] = {
                    ...this.#rules[index],
                    value: raw === '' ? '' : Number(raw),
                };
                this.#updateLiveRegions();
                this.#onChange(this.getValue());
            });
            row.appendChild(valueInput);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'criteria-row__remove btn btn-danger btn-sm';
            removeBtn.textContent = '✕';
            removeBtn.setAttribute('aria-label', 'Remove criterion');
            removeBtn.addEventListener('click', () => this.#removeRule(index));
            row.appendChild(removeBtn);

            if (!this.#ruleIsValid(rule)) {
                const hint = document.createElement('span');
                hint.className = 'criteria-row__hint';
                hint.textContent = 'Select a type and enter a numeric value ≥ 0';
                row.appendChild(hint);
            }

            return row;
        }

        #buildSelect(options, currentValue, name, onChangeFn) {
            const select = document.createElement('select');
            select.name = name;
            select.className = 'criteria-row__select';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.disabled = true;
            placeholder.selected = !currentValue;
            placeholder.textContent = '— select —';
            select.appendChild(placeholder);

            options.forEach(opt => {
                const el = document.createElement('option');
                el.value = opt.value;
                el.textContent = opt.label;
                el.selected = opt.value === currentValue;
                select.appendChild(el);
            });

            select.addEventListener('change', (e) => onChangeFn(e.target.value));
            return select;
        }

        // -------------------------------------------------------------------------
        // Live region update — no DOM destruction
        // -------------------------------------------------------------------------

        #updateLiveRegions() {
            this.#previewEl.textContent = JSON.stringify(this.#rules, null, 2);

            const valid = this.isValid();
            this.#statusEl.textContent = valid
                ? '✓ Criteria are valid'
                : '✗ Please fix the criteria above before saving';
            this.#statusEl.className = `criteria-status criteria-status--${valid ? 'valid' : 'invalid'}`;
        }

        // -------------------------------------------------------------------------
        // Mutations
        // -------------------------------------------------------------------------

        #addRule() {
            this.#rules.push({type: '', operator: '>=', value: ''});
            this.#renderRows();
            this.#updateLiveRegions();
            this.#onChange(this.getValue());
        }

        #removeRule(index) {
            this.#rules.splice(index, 1);
            this.#renderRows();
            this.#updateLiveRegions();
            this.#onChange(this.getValue());
        }

        // Select changes are safe to re-render — no active text input is affected
        #onSelectChange(index, field, value) {
            this.#rules[index] = {...this.#rules[index], [field]: value};
            this.#renderRows();
            this.#updateLiveRegions();
            this.#onChange(this.getValue());
        }

        // -------------------------------------------------------------------------
        // Validation
        // -------------------------------------------------------------------------

        #ruleIsValid(rule) {
            const validType = CRITERIA_TYPES.some(t => t.value === rule.type);
            const validOperator = OPERATORS.some(o => o.value === rule.operator);
            const validValue = rule.value !== '' && !isNaN(Number(rule.value)) && Number(rule.value) >= 0;
            return validType && validOperator && validValue;
        }
    }

    /**
     * BadgeForm
     *
     * Renders as a modal overlay. The backdrop and dialog are appended to
     * document.body so nothing in the admin panel layout clips or offsets them.
     *
     * Usage:
     *   const form = new BadgeForm(null, { onSubmit, onCancel });
     *   form.render(badge | null);   // null → create mode
     *   form.showErrors(errors);
     *   form.destroy();
     */
    /**
     * BadgeForm
     *
     * Renders as a modal overlay appended to document.body.
     *
     * Fixes vs previous version:
     *  1. render() is idempotent — if the modal is already open for the same
     *     mode+badge it does NOT tear down and rebuild. This stops the modal
     *     vanishing whenever BadgeApp emits a state notification mid-flight
     *     (e.g. setLoading(true) during the create flow).
     *  2. isEdit was referenced inside the .catch() callback but was only
     *     defined as a local in #buildModal — moved to a class field so the
     *     catch handler can read it.
     *  3. Slug auto-populates from name while the user hasn't manually edited
     *     the slug field. Once they type in slug directly, auto-fill stops.
     */
    class BadgeForm {
        #onSubmit;
        #onCancel;
        #criteriaBuilder = null;
        #currentCriteria = [];

        // Modal DOM nodes
        #backdrop = null;
        #dialog = null;
        #onKeydown = null;

        // Track which badge/mode the open modal was built for
        #openForBadgeId = undefined;   // null = create mode, number = edit mode
        #isEdit = false;
        #slugTouched = false;       // true once user manually edits the slug field

        constructor(_ignored, {onSubmit, onCancel}) {
            this.#onSubmit = onSubmit;
            this.#onCancel = onCancel;
        }

        // -------------------------------------------------------------------------
        // Public
        // -------------------------------------------------------------------------

        /**
         * Open (or keep open) the modal for the given badge.
         * Passing null means "create" mode.
         * Calling render() again with the same badge id is a no-op.
         */
        render(badge = null) {
            const incomingId = badge?.id ?? null;

            // Already showing the right modal — do nothing.
            if (this.#backdrop && this.#openForBadgeId === incomingId) return;

            this.#currentCriteria = badge?.criteria ?? [];
            this.#isEdit = badge !== null;
            this.#slugTouched = false;
            this.#openForBadgeId = incomingId;

            this.#destroyModal();
            this.#buildModal(badge);
        }

        showErrors(errors) {
            const banner = this.#dialog?.querySelector('.badge-form__errors');
            if (!banner) return;

            banner.innerHTML = '';
            const messages = typeof errors === 'string'
                ? [errors]
                : Object.values(errors).flat();

            messages.forEach(msg => {
                const p = document.createElement('p');
                p.textContent = msg;
                banner.appendChild(p);
            });

            banner.classList.remove('badge-form__errors--hidden');
            banner.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }

        destroy() {
            this.#destroyModal();
        }

        // -------------------------------------------------------------------------
        // Modal scaffold
        // -------------------------------------------------------------------------

        #buildModal(badge) {
            // Backdrop
            this.#backdrop = document.createElement('div');
            this.#backdrop.className = 'badge-modal-backdrop';
            this.#backdrop.addEventListener('click', (e) => {
                if (e.target === this.#backdrop) this.#close();
            });

            // Dialog
            this.#dialog = document.createElement('div');
            this.#dialog.className = 'badge-modal';
            this.#dialog.setAttribute('role', 'dialog');
            this.#dialog.setAttribute('aria-modal', 'true');
            this.#dialog.setAttribute('aria-label', this.#isEdit ? 'Edit Badge' : 'Create Badge');

            // Header
            const header = document.createElement('div');
            header.className = 'badge-modal__header';

            const title = document.createElement('h2');
            title.className = 'badge-modal__title';
            title.textContent = this.#isEdit ? 'Edit Badge' : 'Create Badge';

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'badge-modal__close';
            closeBtn.textContent = '✕';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.addEventListener('click', () => this.#close());

            header.appendChild(title);
            header.appendChild(closeBtn);

            // Error banner
            const errorBanner = document.createElement('div');
            errorBanner.className = 'badge-form__errors badge-form__errors--hidden';
            errorBanner.setAttribute('role', 'alert');

            // Scrollable body
            const body = document.createElement('div');
            body.className = 'badge-modal__body';

            const form = document.createElement('form');
            form.className = 'badge-form__body';
            form.noValidate = true;
            form.appendChild(errorBanner);

            // ── Name + Slug (two-column grid) ──────────────────────────────────────

            const grid = document.createElement('div');
            grid.className = 'badge-form__grid';

            const nameField = this.#buildField({
                id: 'badge-name', label: 'Name', type: 'text', name: 'name',
                value: badge?.name ?? '', required: true, placeholder: 'e.g. Super Reader',
            });

            const slugField = this.#buildField({
                id: 'badge-slug', label: 'Slug', type: 'text', name: 'slug',
                value: badge?.slug ?? '', placeholder: 'e.g. super-reader',
            });

            grid.appendChild(nameField);
            grid.appendChild(slugField);
            form.appendChild(grid);

            // Wire slug auto-populate
            const nameInput = nameField.querySelector('input');
            const slugInput = slugField.querySelector('input');
            this.#wireSlugAutofill(nameInput, slugInput);

            // ── Remaining fields ───────────────────────────────────────────────────

            form.appendChild(this.#buildField({
                id: 'badge-category', label: 'Category', type: 'text', name: 'category',
                value: badge?.category ?? '', placeholder: 'e.g. engagement',
            }));

            form.appendChild(this.#buildTextareaField({
                id: 'badge-description', label: 'Description', name: 'description',
                value: badge?.description ?? '', placeholder: 'Explain what earns this badge',
            }));

            form.appendChild(this.#buildField({
                id: 'badge-icon', label: 'Icon (emoji or URL)', type: 'text', name: 'icon',
                value: badge?.icon ?? '', placeholder: '🏆',
            }));

            form.appendChild(this.#buildField({
                id: 'badge-points', label: 'Bonus points', type: 'number', name: 'points',
                value: badge?.points ?? 0, min: 0,
            }));

            form.appendChild(this.#buildCheckboxField({
                id: 'badge-is-active', label: 'Active', name: 'is_active',
                checked: badge?.is_active ?? true,
            }));

            // ── Criteria builder ───────────────────────────────────────────────────

            const criteriaSection = document.createElement('div');
            criteriaSection.className = 'badge-form__criteria';

            const criteriaLabel = document.createElement('label');
            criteriaLabel.className = 'badge-form__label';
            criteriaLabel.textContent = 'Criteria';
            criteriaSection.appendChild(criteriaLabel);

            const criteriaMount = document.createElement('div');
            criteriaMount.className = 'criteria-builder-mount';
            criteriaSection.appendChild(criteriaMount);
            form.appendChild(criteriaSection);

            body.appendChild(form);

            // Footer
            const footer = document.createElement('div');
            footer.className = 'badge-modal__footer';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-secondary';
            cancelBtn.textContent = 'Cancel';
            cancelBtn.addEventListener('click', () => this.#close());

            const submitBtn = document.createElement('button');
            submitBtn.type = 'submit';
            submitBtn.className = 'btn btn-primary';
            submitBtn.textContent = this.#isEdit ? 'Save Changes' : 'Create Badge';

            footer.appendChild(cancelBtn);
            footer.appendChild(submitBtn);

            this.#dialog.appendChild(header);
            this.#dialog.appendChild(body);
            form.appendChild(footer);
            this.#backdrop.appendChild(this.#dialog);
            document.body.appendChild(this.#backdrop);

            // Focus first field
            requestAnimationFrame(() => {
                this.#dialog?.querySelector('input, textarea, select')?.focus();
            });

            // Mount criteria builder
            this.#criteriaBuilder = new BadgeCriteriaBuilder(criteriaMount, (rules) => {
                this.#currentCriteria = rules;
            });
            if (this.#currentCriteria.length > 0) {
                this.#criteriaBuilder.setValue(this.#currentCriteria);
            }

            // ESC to close
            this.#onKeydown = (e) => {
                if (e.key === 'Escape') this.#close();
            };
            document.addEventListener('keydown', this.#onKeydown);

            // Submit
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.#handleSubmit(form, errorBanner, submitBtn);
            });
        }

        #destroyModal() {
            if (this.#backdrop) {
                this.#backdrop.remove();
                this.#backdrop = null;
                this.#dialog = null;
            }
            if (this.#onKeydown) {
                document.removeEventListener('keydown', this.#onKeydown);
                this.#onKeydown = null;
            }
            this.#criteriaBuilder = null;
            this.#openForBadgeId = undefined;
        }

        #close() {
            this.#destroyModal();
            this.#onCancel();
        }

        // -------------------------------------------------------------------------
        // Slug auto-populate
        // -------------------------------------------------------------------------

        #wireSlugAutofill(nameInput, slugInput) {
            // If user manually edits slug, stop auto-filling
            slugInput.addEventListener('input', () => {
                this.#slugTouched = true;
            });

            nameInput.addEventListener('input', () => {
                if (this.#slugTouched) return;
                slugInput.value = this.#slugify(nameInput.value);
            });
        }

        #slugify(str) {
            return str
                .toLowerCase()
                .trim()
                .replace(/[^\w\s-]/g, '')   // strip non-word chars except spaces/hyphens
                .replace(/[\s_]+/g, '-')    // spaces and underscores → hyphens
                .replace(/-+/g, '-')        // collapse multiple hyphens
                .replace(/^-+|-+$/g, '');   // trim leading/trailing hyphens
        }

        // -------------------------------------------------------------------------
        // Submit
        // -------------------------------------------------------------------------

        #handleSubmit(form, errorBanner, submitBtn) {
            errorBanner.classList.add('badge-form__errors--hidden');
            errorBanner.innerHTML = '';

            if (!this.#criteriaBuilder?.isValid()) {
                this.showErrors('Please add at least one valid criterion before saving.');
                return;
            }

            const data = new FormData(form);
            const payload = {
                name: data.get('name')?.trim() ?? '',
                slug: data.get('slug')?.trim() ?? '',
                category: data.get('category')?.trim() ?? '',
                description: data.get('description')?.trim() ?? '',
                icon: data.get('icon')?.trim() ?? '',
                points: Number(data.get('points') ?? 0),
                is_active: form.querySelector('[name="is_active"]').checked,
                criteria: this.#currentCriteria,
            };

            if (!payload.name) {
                this.showErrors('Name is required.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving…';

            // #isEdit is a class field — safely accessible in this callback
            this.#onSubmit(payload).catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = this.#isEdit ? 'Save Changes' : 'Create Badge';
            });
        }

        // -------------------------------------------------------------------------
        // Field builders
        // -------------------------------------------------------------------------

        #buildField({id, label, type, name, value, required = false, placeholder = '', min}) {
            const wrap = document.createElement('div');
            wrap.className = 'badge-form__field';

            const lbl = document.createElement('label');
            lbl.htmlFor = id;
            lbl.className = 'badge-form__label';
            lbl.textContent = label + (required ? ' *' : '');

            const input = document.createElement('input');
            input.id = id;
            input.type = type;
            input.name = name;
            input.value = value;
            input.className = 'badge-form__input';
            input.placeholder = placeholder;
            if (required) input.required = true;
            if (min !== undefined) input.min = String(min);

            wrap.appendChild(lbl);
            wrap.appendChild(input);
            return wrap;
        }

        #buildTextareaField({id, label, name, value, placeholder}) {
            const wrap = document.createElement('div');
            wrap.className = 'badge-form__field';

            const lbl = document.createElement('label');
            lbl.htmlFor = id;
            lbl.className = 'badge-form__label';
            lbl.textContent = label;

            const textarea = document.createElement('textarea');
            textarea.id = id;
            textarea.name = name;
            textarea.className = 'badge-form__textarea';
            textarea.placeholder = placeholder;
            textarea.rows = 3;
            textarea.textContent = value;

            wrap.appendChild(lbl);
            wrap.appendChild(textarea);
            return wrap;
        }

        #buildCheckboxField({id, label, name, checked}) {
            const wrap = document.createElement('div');
            wrap.className = 'badge-form__field badge-form__field--checkbox';

            const input = document.createElement('input');
            input.id = id;
            input.type = 'checkbox';
            input.name = name;
            input.checked = checked;

            const lbl = document.createElement('label');
            lbl.htmlFor = id;
            lbl.className = 'badge-form__label';
            lbl.textContent = label;

            wrap.appendChild(input);
            wrap.appendChild(lbl);
            return wrap;
        }
    }

    /**
     * BadgeList
     *
     * Renders the paginated badge table.
     *
     * Usage:
     *   const list = new BadgeList(containerEl, { onCreate, onEdit, onDelete });
     *   list.render(state);
     */
    class BadgeList {
        #container;
        #onCreate;
        #onEdit;
        #onDelete;
        #onPageChange;

        constructor(container, {onCreate, onEdit, onDelete, onPageChange}) {
            this.#container = container;
            this.#onCreate = onCreate;
            this.#onEdit = onEdit;
            this.#onDelete = onDelete;
            this.#onPageChange = onPageChange;
        }

        // -------------------------------------------------------------------------
        // Public
        // -------------------------------------------------------------------------

        render(state) {
            this.#container.innerHTML = '';

            const wrapper = document.createElement('div');
            wrapper.className = 'badge-list';

            // Header
            const header = document.createElement('div');
            header.className = 'badge-list__header';

            const h2 = document.createElement('h2');
            h2.textContent = `Badges (${state.pagination.total})`;

            const createBtn = document.createElement('button');
            createBtn.type = 'button';
            createBtn.className = 'btn btn-primary';
            createBtn.textContent = '+ New Badge';
            createBtn.addEventListener('click', () => this.#onCreate());

            header.appendChild(h2);
            header.appendChild(createBtn);
            wrapper.appendChild(header);

            // Loading
            if (state.loading) {
                const loading = document.createElement('p');
                loading.className = 'badge-list__loading';
                loading.textContent = 'Loading…';
                wrapper.appendChild(loading);
                this.#container.appendChild(wrapper);
                return;
            }

            // Errors
            if (state.errors.length > 0) {
                const errBanner = document.createElement('div');
                errBanner.className = 'badge-list__errors';
                state.errors.forEach(msg => {
                    const p = document.createElement('p');
                    p.textContent = msg;
                    errBanner.appendChild(p);
                });
                wrapper.appendChild(errBanner);
            }

            // Empty state
            if (state.badges.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'badge-list__empty';
                empty.textContent = 'No badges yet. Create one to get started.';
                wrapper.appendChild(empty);
                this.#container.appendChild(wrapper);
                return;
            }

            // Table
            const table = document.createElement('table');
            table.className = 'badge-list__table';

            const thead = document.createElement('thead');
            thead.innerHTML = `
            <tr>
                <th>Icon</th>
                <th>Name</th>
                <th>Points</th>
                <th>Criteria</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        `;
            table.appendChild(thead);

            const tbody = document.createElement('tbody');
            state.badges.forEach(badge => tbody.appendChild(this.#buildRow(badge)));
            table.appendChild(tbody);
            wrapper.appendChild(table);

            // Pagination
            if (state.pagination.lastPage > 1) {
                wrapper.appendChild(this.#buildPagination(state.pagination));
            }

            this.#container.appendChild(wrapper);
        }

        // -------------------------------------------------------------------------
        // Private
        // -------------------------------------------------------------------------

        #buildRow(badge) {
            const tr = document.createElement('tr');
            tr.className = badge.is_active ? '' : 'badge-list__row--inactive';

            tr.innerHTML = `
            <td class="badge-list__icon">${this.#escapeHtml(badge.icon ?? '—')}</td>
            <td class="badge-list__name">${this.#escapeHtml(badge.name)}</td>
            <td class="badge-list__points">${badge.points ?? 0}</td>
            <td class="badge-list__criteria">${badge.criteria?.length ?? 0} rule(s)</td>
            <td class="badge-list__active">${badge.is_active ? '✓' : '✗'}</td>
            <td class="badge-list__actions"></td>
        `;

            const actions = tr.querySelector('.badge-list__actions');

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-sm btn-secondary';
            editBtn.textContent = 'Edit';
            editBtn.addEventListener('click', () => this.#onEdit(badge));

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-sm btn-danger';
            deleteBtn.textContent = 'Delete';
            deleteBtn.addEventListener('click', () => {
                if (confirm(`Delete badge "${badge.name}"? This cannot be undone.`)) {
                    this.#onDelete(badge.id);
                }
            });

            actions.appendChild(editBtn);
            actions.appendChild(deleteBtn);

            return tr;
        }

        #buildPagination({currentPage, lastPage}) {
            const nav = document.createElement('nav');
            nav.className = 'badge-list__pagination';
            nav.setAttribute('aria-label', 'Badge pagination');

            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'btn btn-sm btn-secondary';
            prev.textContent = '← Prev';
            prev.disabled = currentPage <= 1;
            prev.addEventListener('click', () => this.#onPageChange(currentPage - 1));

            const info = document.createElement('span');
            info.className = 'badge-list__page-info';
            info.textContent = `Page ${currentPage} of ${lastPage}`;

            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'btn btn-sm btn-secondary';
            next.textContent = 'Next →';
            next.disabled = currentPage >= lastPage;
            next.addEventListener('click', () => this.#onPageChange(currentPage + 1));

            nav.appendChild(prev);
            nav.appendChild(info);
            nav.appendChild(next);

            return nav;
        }

        #escapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = String(str);
            return d.innerHTML;
        }
    }

    /**
     * BadgeApp
     *
     * Root SPA controller. Owns the state, the API service, and the two views
     * (list and form). Coordinates all user actions.
     *
     * Bootstrap:
     *   const app = new BadgeApp(document.getElementById('badge-admin'), {
     *     apiBase:   '/admin/badges',
     *     csrfToken: document.querySelector('meta[name="csrf-token"]').content,
     *   });
     *   app.init();
     */
    class BadgeApp {
        #root;
        #state;
        #api;
        #listComponent;
        #formComponent;
        #listContainer;

        constructor(root, {apiBase, csrfToken}) {
            this.#root = root;
            this.#state = new BadgeState();
            this.#api = new BadgeApiService(apiBase, csrfToken);
        }

        // -------------------------------------------------------------------------
        // Bootstrap
        // -------------------------------------------------------------------------

        init() {
            this.#buildLayout();

            this.#listComponent = new BadgeList(this.#listContainer, {
                onCreate: () => this.#handleCreate(),
                onEdit: (badge) => this.#handleEdit(badge),
                onDelete: (id) => this.#handleDelete(id),
                onPageChange: (page) => this.#loadBadges(page),
            });

            // BadgeForm is modal — no container arg needed
            this.#formComponent = new BadgeForm(null, {
                onSubmit: (payload) => this.#handleSubmit(payload),
                onCancel: () => this.#backToList(),
            });

            this.#state.onChange(state => this.#onStateChange(state));

            this.#loadBadges(1);
        }

        // -------------------------------------------------------------------------
        // Layout
        // -------------------------------------------------------------------------

        #buildLayout() {
            this.#root.innerHTML = '';
            this.#root.className = 'badge-admin';

            this.#listContainer = document.createElement('div');
            this.#listContainer.className = 'badge-admin__list';

            this.#root.appendChild(this.#listContainer);
        }

        // -------------------------------------------------------------------------
        // State → view
        // -------------------------------------------------------------------------

        #onStateChange(state) {
            // List always re-renders (reflects loading, errors, pagination)
            this.#listComponent.render(state);

            if (state.mode === 'create' || state.mode === 'edit') {
                this.#formComponent.render(state.selectedBadge);
            }
        }

        // -------------------------------------------------------------------------
        // Actions
        // -------------------------------------------------------------------------

        async #loadBadges(page) {
            this.#state.setLoading(true);
            this.#state.clearErrors();

            try {
                const data = await this.#api.list(page);
                this.#state.setBadges(data.data, data.meta ? {
                    currentPage: data.meta.current_page,
                    lastPage: data.meta.last_page,
                    total: data.meta.total,
                    perPage: data.meta.per_page,
                } : null);
            } catch (err) {
                this.#state.setErrors(err.message ?? 'Failed to load badges.');
            } finally {
                this.#state.setLoading(false);
            }
        }

        #handleCreate() {
            this.#state.openCreate();
        }

        #handleEdit(badge) {
            this.#state.openEdit(badge);
        }

        async #handleDelete(id) {
            this.#state.setLoading(true);
            this.#state.clearErrors();

            try {
                await this.#api.destroy(id);
                this.#state.removeBadge(id);
            } catch (err) {
                this.#state.setErrors(err.message ?? 'Failed to delete badge.');
            } finally {
                this.#state.setLoading(false);
            }
        }

        async #handleSubmit(payload) {
            const snapshot = this.#state.snapshot();
            const isEdit = snapshot.mode === 'edit';
            const badgeId = snapshot.selectedBadge?.id;

            this.#state.clearErrors();

            try {
                if (isEdit) {
                    const updated = await this.#api.update(badgeId, payload);
                    this.#state.replaceBadge(updated);
                } else {
                    const created = await this.#api.create(payload);
                    this.#state.addBadge(created);
                }
                this.#backToList();
            } catch (err) {
                this.#state.setErrors(err.message ?? 'Failed to save badge.');
                this.#formComponent.showErrors(err.errors ?? err.message ?? 'An error occurred.');
                throw err; // re-throw so BadgeForm re-enables the submit button
            }
        }

        #backToList() {
            this.#formComponent.destroy();
            this.#state.backToList();
        }
    }

    document.addEventListener('DOMContentLoaded', () => new BadgeApp(
        document.getElementById('badge-admin-root'),
        {
            apiBase: '/api/<?= \App\Framework\Support\SiteContext::slug() ?>/admin/badges',
            csrfToken: '<?= csrf_token() ?>'
        }
    ).init());
</script>