<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consent History - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, .1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ── Page header card ───────────────────────────── */
        .page-header {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: .5rem;
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
        }

        /* ── Filters ────────────────────────────────────── */
        .filters {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 180px;
        }

        .filter-label {
            display: block;
            font-weight: 500;
            margin-bottom: .5rem;
            font-size: .875rem;
        }

        .filter-input {
            width: 100%;
            padding: .75rem;
            border: 1px solid var(--border-color);
            border-radius: .5rem;
            font-size: .9375rem;
            background: white;
            color: var(--text-primary);
        }

        /* ── Export button ──────────────────────────────── */
        .export-btn {
            padding: .75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: .75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .export-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, .3);
        }

        /* ── Timeline ───────────────────────────────────── */
        .timeline {
            position: relative;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 2rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }

        .timeline-item {
            position: relative;
            padding-left: 5rem;
            padding-bottom: 2rem;
        }

        .timeline-marker {
            position: absolute;
            left: 1.375rem;
            top: 0;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: var(--shadow);
        }

        .timeline-marker.granted {
            background: var(--success-color);
        }

        .timeline-marker.revoked {
            background: var(--danger-color);
        }

        .timeline-marker.updated {
            background: var(--warning-color);
        }

        .timeline-marker.expired {
            background: var(--text-secondary);
        }

        .timeline-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all .3s;
        }

        .timeline-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            transform: translateX(4px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        /* ── Action badges ──────────────────────────────── */
        .action-badge {
            padding: .375rem .75rem;
            border-radius: .5rem;
            font-size: .8125rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .action-badge.granted {
            background: #d1fae5;
            color: #065f46;
        }

        .action-badge.revoked {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-badge.updated {
            background: #fef3c7;
            color: #92400e;
        }

        .action-badge.expired {
            background: #f3f4f6;
            color: #4b5563;
        }

        .consent-name {
            font-size: 1.125rem;
            font-weight: 600;
            margin-top: .375rem;
        }

        /* ── Detail grid ────────────────────────────────── */
        .consent-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: .25rem;
        }

        .detail-label {
            font-size: .75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .05em;
            font-weight: 600;
        }

        .detail-value {
            font-size: .9375rem;
            color: var(--text-primary);
        }

        /* ── State-change pill row ──────────────────────── */
        .state-change {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            font-size: .875rem;
        }

        .state-box {
            padding: .375rem .75rem;
            border-radius: .5rem;
            font-size: .875rem;
            font-weight: 600;
        }

        .state-box.granted {
            background: #d1fae5;
            color: #065f46;
        }

        .state-box.revoked {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ── Empty / loading / error states ────────────── */
        .empty-state {
            background: white;
            border-radius: 1rem;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: .5;
        }

        .loading-state {
            background: white;
            border-radius: 1rem;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            color: var(--text-secondary);
        }

        /* ── Skeleton shimmer ───────────────────────────── */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: .5rem;
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        .skeleton-item {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }

        /* ── No-results within a loaded list ────────────── */
        .no-results {
            background: white;
            border-radius: 1rem;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            color: var(--text-secondary);
        }

        /* ── Responsive ─────────────────────────────────── */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .filters {
                flex-direction: column;
            }

            .timeline::before {
                left: 1rem;
            }

            .timeline-item {
                padding-left: 3rem;
            }

            .timeline-marker {
                left: .375rem;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<main class="container">
    <div class="page-header">
        <h1 class="page-title"><span>📋</span> Consent History</h1>
        <p class="page-subtitle">Complete audit trail of all changes to your consent preferences</p>
    </div>

    <div class="filters">
        <div class="filter-group">
            <label class="filter-label">Filter by Action</label>
            <select class="filter-input" id="actionFilter">
                <option value="">All Actions</option>
                <option value="granted">Granted</option>
                <option value="revoked">Revoked</option>
                <option value="updated">Updated</option>
                <option value="expired">Expired</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Filter by Consent</label>
            <select class="filter-input" id="consentFilter">
                <option value="">All Consents</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Date</label>
            <input type="date" class="filter-input" id="dateFilter">
        </div>
        <div class="filter-group" style="display:flex;align-items:flex-end;">
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/consent/download-data"
               class="export-btn">📥 Export Data</a>
        </div>
    </div>

    <!-- Skeleton shown while loading -->
    <div id="timeline-skeleton">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="skeleton-item" style="opacity: <?= 1 - $i * 0.15 ?>; padding-left: 5rem; position: relative;">
                <div style="position:absolute;left:1.375rem;top:1.5rem;width:1.25rem;height:1.25rem;border-radius:50%;background:#e5e7eb;border:3px solid white;"></div>
                <div class="skeleton" style="height:.875rem;width:5rem;margin-bottom:.75rem;"></div>
                <div class="skeleton" style="height:1.125rem;width:55%;margin-bottom:.625rem;"></div>
                <div class="skeleton" style="height:.875rem;width:35%;"></div>
            </div>
        <?php endfor ?>
    </div>

    <div id="timeline-root" style="display:none;"></div>
</main>

<script>
    const API_BASE = '/api/<?= $site->slug ?>';
    const MEMBER_ID = <?= (int)$member->id ?>;

    class AuditHistoryStore {
        constructor() {
            this.state = {
                entries: [],
                filters: {
                    action: '',
                    consent: '',
                    date: '',
                },
                loading: false,
                error: null,
            };
            this.listeners = [];
        }

        subscribe(listener) {
            this.listeners.push(listener);
            listener(this.state);
        }

        setState(patch) {
            this.state = {
                ...this.state,
                ...patch,
            };

            this.listeners.forEach(listener => listener(this.state));
        }
    }

    /* ─── UI COMPONENTS ─────────────────────────────────────── */

    /**
     * Component: Individual Audit Entry (Timeline Item)
     */
    class AuditRow {
        constructor(entry) {
            this.e = entry;
        }

        render() {
            const action = (this.e.action || 'expired').toLowerCase();
            const name = this.e.consent_type?.name || 'Unknown Preference';
            const dateObj = new Date(this.e.created_at.replace(/-/g, '/'));

            return UI.el('div', {className: 'timeline-item'}, [
                UI.el('div', {className: `timeline-marker ${action}`}),
                UI.el('div', {className: 'timeline-card'}, [
                    // Header: Action Badge, Name, and Timestamp
                    UI.el('div', {className: 'card-header'}, [
                        UI.el('div', {}, [
                            UI.el('span', {className: `action-badge ${action}`}, [action.toUpperCase()]),
                            UI.el('div', {className: 'consent-name'}, [name])
                        ]),
                        UI.el('div', {className: 'card-timestamp'}, [
                            UI.el('div', {className: 'date'}, [
                                dateObj.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})
                            ]),
                            UI.el('div', {className: 'time'}, [
                                dateObj.toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'})
                            ])
                        ])
                    ]),

                    // Details Grid: Source, IP, Admin Info
                    UI.el('div', {className: 'consent-details'}, this.renderDetails()),

                    // State Transition: Previous vs New
                    this.renderStateChange()
                ])
            ]);
        }

        renderDetails() {
            const details = [];
            const sourceIcons = {web: '🌐', email: '📧', api: '⚙️', admin: '👤', system: '🤖'};

            if (this.e.source) {
                details.push(this.detailItem('Source', `${sourceIcons[this.e.source] || ''} ${this.e.source}`));
            }
            if (this.e.ip_address) {
                details.push(this.detailItem('IP Address', this.e.ip_address));
            }
            if (this.e.admin_email) {
                details.push(this.detailItem('Modified By', this.e.admin_email));
            }
            if (this.e.reason) {
                details.push(UI.el('div', {className: 'detail-item full-width'}, [
                    UI.el('span', {className: 'detail-label'}, ['REASON']),
                    UI.el('span', {className: 'detail-value'}, [this.e.reason])
                ]));
            }
            return details;
        }

        detailItem(label, value) {
            return UI.el('div', {className: 'detail-item'}, [
                UI.el('span', {className: 'detail-label'}, [label.toUpperCase()]),
                UI.el('span', {className: 'detail-value'}, [value])
            ]);
        }

        renderStateChange() {
            if (this.e.previous_state === null || this.e.previous_state === undefined) return null;

            const pState = Boolean(Number(this.e.previous_state));
            const nState = Boolean(Number(this.e.new_state));

            return UI.el('div', {className: 'state-change'}, [
                UI.el('span', {className: 'state-label'}, ['Status Changed: ']),
                UI.el('span', {className: `state-box ${pState ? 'granted' : 'revoked'}`}, [pState ? 'Granted' : 'Not Granted']),
                UI.el('span', {className: 'state-arrow'}, [' → ']),
                UI.el('span', {className: `state-box ${nState ? 'granted' : 'revoked'}`}, [nState ? 'Granted' : 'Not Granted'])
            ]);
        }
    }

    /* ─── APP ORCHESTRATOR ──────────────────────────────────── */

    class HistoryApp {
        constructor() {
            this.container = document.getElementById('timeline-root');
            this.skeleton = document.getElementById('timeline-skeleton');
            this.store = new AuditHistoryStore();
            this.store.subscribe(state => this.render(state));
            this.init();
        }

        async init() {
            await this.loadData();
            this.wireFilters();
        }

        async loadData() {
            this.store.setState({loading: true, error: null});

            try {
                const res = await api(`${API_BASE}/member/consent/audit-history?member_id=${MEMBER_ID}`);
                this.store.setState({
                    entries: res.items || [],
                    loading: false,
                });

                if (this.skeleton) this.skeleton.style.display = 'none';
                this.container.style.display = 'block';

                this.populateConsentFilter();
            } catch (e) {
                this.store.setState({
                    loading: false,
                    error: e.message || 'Failed to load history.',
                });
                if (this.skeleton) {
                    this.skeleton.innerHTML = `<div class="error-state">Failed to load history: ${e.message}</div>`;
                }
            }
        }

        populateConsentFilter() {
            const select = document.getElementById('consentFilter');
            if (!select) return;

            const seen = new Set();
            this.store.state.entries.forEach(e => {
                const c = e.consent_type;
                if (c && !seen.has(c.code)) {
                    seen.add(c.code);
                    select.appendChild(UI.el('option', {value: c.code}, [c.name]));
                }
            });
        }

        render(state) {
            if (state.loading) {
                return;
            }

            if (state.error) {
                UI.render(this.container, UI.el('div', {className: 'empty-state'}, [
                    UI.el('p', {}, [state.error])
                ]));
                return;
            }

            const actionVal = state.filters.action;
            const consentVal = state.filters.consent;
            const dateVal = state.filters.date;

            const filtered = state.entries.filter(e => {
                const matchesAction = !actionVal || e.action === actionVal;
                const matchesConsent = !consentVal || (e.consent_type && e.consent_type.code === consentVal);
                const matchesDate = !dateVal || e.created_at.startsWith(dateVal);
                return matchesAction && matchesConsent && matchesDate;
            });

            if (filtered.length === 0) {
                UI.render(this.container, UI.el('div', {className: 'empty-state'}, [
                    UI.el('p', {}, ['No activity matches your current filters.'])
                ]));
                return;
            }

            const timelineNodes = filtered.map(entry => new AuditRow(entry).render());
            UI.render(this.container, UI.el('div', {className: 'timeline'}, timelineNodes));
        }

        wireFilters() {
            ['actionFilter', 'consentFilter', 'dateFilter'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.onchange = () => {
                        this.store.setState({
                            filters: {
                                action: document.getElementById('actionFilter').value,
                                consent: document.getElementById('consentFilter').value,
                                date: document.getElementById('dateFilter').value,
                            }
                        });
                    };
                }
            });
        }
    }

    // Bootstrap
    document.addEventListener('DOMContentLoaded', () => {
        window.auditApp = new HistoryApp();
    });
</script>
</body>
</html>
