class ContributorBriefInboxState {
    constructor() {
        this.briefs = [];
        this.summary = {};
        this.filter = 'all';
        this.search = '';
        this.loading = false;
        this.error = null;
    }

    setLoading() {
        this.loading = true;
        this.error = null;
    }

    setLoaded(payload) {
        this.briefs = payload.data || [];
        this.summary = payload.meta?.summary || {};
        this.loading = false;
        this.error = null;
    }

    setError(message) {
        this.loading = false;
        this.error = message || 'Briefs could not be loaded.';
    }
}

class ContributorBriefInboxApi {
    constructor(site, tokenProvider) {
        this.site = site;
        this.tokenProvider = tokenProvider;
    }

    async index({filter, search}) {
        const url = new URL(`/api/${this.site}/open-collab/briefs`, window.location.origin);
        url.searchParams.set('filter', filter || 'all');

        if (search) {
            url.searchParams.set('search', search);
        }

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${this.tokenProvider()}`,
            },
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.error || 'Briefs could not be loaded.');
        }

        return payload;
    }
}

class ContributorBriefInboxFilters {
    constructor(root, onChange) {
        this.root = root;
        this.onChange = onChange;
    }

    init() {
        this.root.querySelectorAll('[data-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                this.root.querySelectorAll('[data-filter]').forEach((item) => {
                    item.classList.remove('is-active');
                });
                button.classList.add('is-active');
                this.onChange(button.dataset.filter || 'all');
            });
        });
    }
}

class ContributorBriefInboxSearch {
    constructor(input, onChange) {
        this.input = input;
        this.onChange = onChange;
        this.timer = null;
    }

    init() {
        this.input.addEventListener('input', () => {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.onChange(this.input.value.trim());
            }, 250);
        });
    }
}

class ContributorBriefInboxRenderer {
    constructor(root) {
        this.root = root;
        this.list = root.querySelector('[data-brief-list]');
        this.loading = root.querySelector('[data-state="loading"]');
        this.empty = root.querySelector('[data-state="empty"]');
        this.error = root.querySelector('[data-state="error"]');
        this.errorMessage = root.querySelector('[data-error-message]');
    }

    render(state) {
        this.renderSummary(state.summary);
        this.loading.hidden = !state.loading;
        this.error.hidden = !state.error;
        this.empty.hidden = state.loading || state.error || state.briefs.length > 0;
        this.list.hidden = state.loading || state.error || state.briefs.length === 0;

        if (state.error) {
            this.errorMessage.textContent = state.error;
        }

        if (!state.loading && !state.error) {
            this.renderBriefs(state.briefs);
        }
    }

    renderSummary(summary) {
        Object.entries(summary || {}).forEach(([key, value]) => {
            const target = this.root.querySelector(`[data-summary="${key}"]`);
            if (target) {
                target.textContent = value;
            }
        });
    }

    renderBriefs(briefs) {
        this.list.innerHTML = '';

        const fragment = document.createDocumentFragment();
        briefs.forEach((brief) => {
            fragment.appendChild(this.renderBrief(brief));
        });

        this.list.appendChild(fragment);
    }

    renderBrief(brief) {
        const row = document.createElement('article');
        row.className = `oc-brief-row${brief.is_overdue ? ' oc-brief-row--overdue' : ''}`;

        row.innerHTML = `
            <div class="oc-brief-row__main">
                <div class="oc-brief-row__titleline">
                    <h2>${this.escape(brief.title || 'Untitled brief')}</h2>
                    ${brief.is_overdue ? '<span class="oc-brief-badge oc-brief-badge--danger">Overdue</span>' : ''}
                </div>
                <div class="oc-brief-row__meta">
                    <span>${this.escape(brief.site || 'Unassigned brand')}</span>
                    <span>Updated ${this.formatDate(brief.last_updated_at, 'Unknown')}</span>
                </div>
            </div>
            <div class="oc-brief-row__statuses">
                <span class="oc-brief-badge">${this.escape(brief.assignment_status_label || brief.assignment_status)}</span>
                <span class="oc-brief-badge oc-brief-badge--muted">${this.escape(brief.workflow_status_label || brief.workflow_status)}</span>
            </div>
            <div class="oc-brief-row__deadline">
                <strong>${this.formatDate(brief.deadline_at, 'No deadline')}</strong>
                <span>${this.timeRemaining(brief.deadline_at)}</span>
            </div>
            <a class="oc-btn oc-btn--ghost oc-btn--sm" href="${this.escapeAttribute(brief.workspace_url || '#')}">View Brief</a>
        `;

        return row;
    }

    formatDate(value, fallback = 'No date') {
        if (!value) {
            return fallback;
        }

        return new Intl.DateTimeFormat(undefined, {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(value));
    }

    timeRemaining(value) {
        if (!value) {
            return 'Not scheduled';
        }

        const diff = new Date(value).getTime() - Date.now();
        const days = Math.ceil(Math.abs(diff) / 86400000);

        if (diff < 0) {
            return `${days} day${days === 1 ? '' : 's'} overdue`;
        }

        if (days === 0) {
            return 'Due today';
        }

        return `${days} day${days === 1 ? '' : 's'} remaining`;
    }

    escape(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    escapeAttribute(value) {
        return this.escape(value).replace(/"/g, '&quot;');
    }
}

class ContributorBriefInboxPage {
    init() {
        this.root = document.querySelector('[data-brief-inbox]');

        if (!this.root) {
            return;
        }

        this.state = new ContributorBriefInboxState();
        this.api = new ContributorBriefInboxApi(
            this.root.dataset.site || '',
            () => localStorage.getItem('oc_token') || '',
        );
        this.renderer = new ContributorBriefInboxRenderer(this.root);

        new ContributorBriefInboxFilters(this.root, (filter) => {
            this.state.filter = filter;
            this.load();
        }).init();

        new ContributorBriefInboxSearch(this.root.querySelector('[data-search]'), (search) => {
            this.state.search = search;
            this.load();
        }).init();

        this.root.querySelector('[data-retry]').addEventListener('click', () => this.load());

        this.load();
    }

    async load() {
        this.state.setLoading();
        this.renderer.render(this.state);

        try {
            const payload = await this.api.index({
                filter: this.state.filter,
                search: this.state.search,
            });
            this.state.setLoaded(payload);
        } catch (error) {
            this.state.setError(error.message);
        }

        this.renderer.render(this.state);
    }
}
