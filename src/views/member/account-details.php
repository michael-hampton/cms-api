<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Details - <?= htmlspecialchars($site->name ?? 'Site') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: var(--primary-dark);
        }

        .page-header {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--text-secondary);
        }

        .message {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-icon {
            font-size: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label .required {
            color: var(--danger-color);
        }

        .form-input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input:disabled {
            background: var(--bg-light);
            color: var(--text-secondary);
            cursor: not-allowed;
        }

        .form-hint {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .info-grid {
            display: grid;
            gap: 1.5rem;
        }

        .info-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            font-size: 0.9375rem;
            color: var(--text-primary);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .badge.success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge.warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.info {
            background: #dbeafe;
            color: #1e40af;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--border-color);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-light);
            color: var(--text-secondary);
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            color: var(--text-primary);
        }

        .role-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .account-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-box {
            background: var(--bg-light);
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header-content {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .info-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .preference-item:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }

        .preference-info h3 {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .preference-info p {
            font-size: 14px;
            color: #7f8c8d;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #3498db;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /*.btn {*/
        /*    display: inline-block;*/
        /*    padding: 14px 28px;*/
        /*    border: none;*/
        /*    border-radius: 8px;*/
        /*    font-size: 16px;*/
        /*    font-weight: 600;*/
        /*    cursor: pointer;*/
        /*    text-decoration: none;*/
        /*    transition: all 0.3s ease;*/
        /*}*/

        /*.btn-primary {*/
        /*    background: #3498db;*/
        /*    color: white;*/
        /*}*/

        /*.btn-primary:hover {*/
        /*    background: #2980b9;*/
        /*    transform: translateY(-2px);*/
        /*    box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);*/
        /*}*/

        /*.btn-secondary {*/
        /*    background: #95a5a6;*/
        /*    color: white;*/
        /*}*/

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .info-box p {
            font-size: 14px;
            color: #5a6c7d;
            margin: 0;
        }

        @media (max-width: 768px) {
            .preference-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }

        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.9375rem;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            pointer-events: all;
            animation: slideIn 0.3s ease;
            max-width: 360px;
        }

        .toast.success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .toast.error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .toast.info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .toast-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            font-size: 1.1rem;
            padding: 0;
            line-height: 1;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    </style>
</head>
<body>

@include('member._header')

<div class="toast-container" id="toastContainer"></div>

<div class="container">
    <div class="breadcrumb">
        <a id="dashboardLink">Dashboard</a>
        <span>›</span>
        <span>Account Details</span>
    </div>

    <div id="messageContainer"></div>

    <div class="page-header">
        <h1>Account Details</h1>
        <p>View and manage your personal information and account status</p>
    </div>

    <!-- Personal Information Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <span class="card-icon">👤</span>
                Personal Information
            </h2>
        </div>

        <form method="POST" id="accountForm">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="first_name">
                        First Name <span class="required">*</span>
                    </label>
                    <input type="text" id="first_name" name="first_name" class="form-input" data-bind="first_name"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="last_name">
                        Last Name <span class="required">*</span>
                    </label>
                    <input type="text" id="last_name" name="last_name" class="form-input" data-bind="last_name"
                           required>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="display_name">
                        Display Name
                    </label>
                    <input type="text" id="display_name" name="display_name" class="form-input"
                           data-bind="display_name">
                    <span class="form-hint" data-bind="display_name_placeholder"></span>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="email">
                        Email Address <span class="required">*</span>
                    </label>
                    <input type="email" id="email" name="email" class="form-input" data-bind="email" required>
                    <span class="form-hint">Changing your email will require re-verification</span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    💾 Save Changes
                </button>
                <a id="cancelLink" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <!-- Account Status Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <span class="card-icon">🔐</span>
                Account Status
            </h2>
        </div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Account Status</div>
                <div class="info-value" data-bind="account_status"></div>
            </div>

            <div class="info-row">
                <div class="info-label">Email Status</div>
                <div class="info-value" data-bind="email_status"></div>
            </div>

            <div class="info-row">
                <div class="info-label">Member Since</div>
                <div class="info-value" data-bind="created_at"></div>
            </div>

            <div class="info-row">
                <div class="info-label">Last Login</div>
                <div class="info-value" data-bind="last_login_at"></div>
            </div>

            <div class="info-row">
                <div class="info-label">Member ID</div>
                <div class="info-value">
                    <code style="background: var(--bg-light); padding: 0.25rem 0.5rem; border-radius: 0.25rem;"
                          data-bind="id"></code>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Roles</div>
                <div class="info-value">
                    <div class="role-list" data-bind="roles"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <span class="card-icon">⚡</span>
                Quick Actions
            </h2>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <a id="passwordLink" class="btn btn-secondary">
                🔑 Change Password
            </a>

            <a id="verificationLink" class="btn btn-secondary" style="display:none;">
                📧 Resend Verification Email
            </a>
        </div>
    </div>

    <!-- Privacy -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <span class="card-icon">🔒</span>
                Privacy Settings
            </h2>
        </div>

        <form id="privacyForm">
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="show_activity" name="show_activity">
                    <label for="show_activity">Show my activity publicly</label>
                </div>
                <span class="form-hint">Allow others to see your reading activity and engagement</span>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="show_badges" name="show_badges">
                    <label for="show_badges">Show my badges publicly</label>
                </div>
                <span class="form-hint">Display your earned badges on your profile</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    💾 Save Privacy Settings
                </button>
            </div>
        </form>
    </div>
</div>

<div class="container">
    <div class="header">
        <h1>Communication Preferences</h1>
        <p id="siteNameText"></p>
    </div>

    <div class="info-box">
        <strong>Important</strong>
        <p>These preferences control marketing and promotional emails. You will always receive important transactional
            emails.</p>
    </div>

    <form id="preferencesForm">
        <div class="card">

            <div class="section-title">Email Preferences</div>

            <div class="preference-item">
                <div class="preference-info">
                    <h3>Marketing Emails</h3>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="marketing_emails">
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-item">
                <div class="preference-info">
                    <h3>Special Offers</h3>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="special_offers">
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-item">
                <div class="preference-info">
                    <h3>Product Updates</h3>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="product_updates">
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-item">
                <div class="preference-info">
                    <h3>Newsletter</h3>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="newsletter">
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-item">
                <div class="preference-info">
                    <h3>Third-Party Communications</h3>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="third_party_communications">
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Save Preferences</button>
            <a id="backLink" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </form>
</div>

<script>

    /* ─── SETTINGS ORCHESTRATOR ─────────────────────────────── */

    class SettingsStore {
        constructor() {
            this.state = {
                member: null,
                preferences: null,
                siteSlug: SITE_SLUG,
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

    class SettingsManager {
        constructor() {
            this.msgContainer = document.getElementById('messageContainer');
            this.store = new SettingsStore();

            this.forms = {
                details: document.getElementById('accountForm'),
                privacy: document.getElementById('privacyForm'),
                prefs: document.getElementById('preferencesForm')
            };

            this.store.subscribe(state => this.render(state));

            this.init();
        }

        async init() {
            await this.loadInitialData();
            this.wireEvents();
        }

        async loadInitialData() {
            this.store.setState({loading: true, error: null});

            try {
                const {success, data, message} = await api(`/api/${SITE_SLUG}/member/account-details`);
                if (!success) {
                    this.store.setState({
                        loading: false,
                        error: message || 'Failed to load account data',
                    });
                    UI.toast(message || 'Failed to load account data', 'error');
                    return;
                }

                this.store.setState({
                    member: data.member,
                    preferences: data.preferences,
                    siteSlug: data.site_slug,
                    loading: false,
                });
            } catch (_) {
                this.store.setState({
                    loading: false,
                    error: 'Failed to load account data',
                });
                UI.toast('Failed to load account data', 'error');
            }
        }

        render(state) {
            if (state.loading || !state.member) {
                return;
            }

            this.hydrate({
                member: state.member,
                preferences: state.preferences,
                site_slug: state.siteSlug,
            });
        }

        hydrate({member, preferences, site_slug}) {
            // 1. Identity & Profile
            const fullName = `${member.first_name || ''} ${member.last_name || ''}`.trim();

            UI.setFields({
                'first_name': member.first_name,
                'last_name': member.last_name,
                'email': member.email,
                'display_name': member.display_name || fullName,
                'id': `#${member.id}`
            });

            // 2. Status & Dates
            this.renderStatus(member);
            UI.setBind('id', `#${member.id}`);
            UI.setBind('created_at', UI.formatDate(member.created_at));
            UI.setBind('last_login_at', UI.formatDate(member.last_login_at, true));

            // 3. Navigation
            this.updateNavigation(site_slug);

            // 4. Preferences & Toggles
            this.setPreferences(member, preferences);

            // 5. Dynamic Components
            this.renderRoles(member.roles);
        }

        renderStatus(member) {
            const verified = !!member.email_verified_at;

            UI.setBind('account_status', member.is_active
                ? '<span class="badge success">✓ Active</span>'
                : '<span class="badge warning">⚠ Inactive</span>', true);

            UI.setBind('email_status', verified
                ? '<span class="badge success">✓ Verified</span>'
                : '<span class="badge warning">⚠ Not Verified</span>', true);

            const vLink = document.getElementById('verificationLink');
            if (vLink) vLink.style.display = verified ? 'none' : 'inline-block';
        }

        setPreferences(member, prefs) {
            UI.setChecks({
                'marketing_emails': prefs?.marketing_emails ?? true,
                'special_offers': prefs?.special_offers ?? true,
                'product_updates': prefs?.product_updates ?? true,
                'newsletter': prefs?.newsletter ?? true,
                'show_activity': member.show_activity ?? false,
                'show_badges': member.show_badges ?? false
            });
        }

        renderRoles(roles = []) {
            const container = document.querySelector('[data-bind="roles"]');
            if (!container) return;

            container.innerHTML = '';
            roles.forEach(r => {
                container.appendChild(UI.el('span', {className: 'badge info'}, [r.name]));
            });
        }

        updateNavigation(slug) {
            const links = ['dashboardLink', 'cancelLink', 'backLink', 'passwordLink', 'verificationLink'];
            const base = `/${slug}/member`;
            const paths = {
                passwordLink: `${base}/settings`,
                verificationLink: `${base}/resend-verification`
            };

            links.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.href = paths[id] || `${base}/dashboard`;
            });
        }


        wireEvents() {
            // Handle Account Details Form
            this.forms.details?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleDetailsSubmit(e);
            });

            this.forms.privacy?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handlePrivacySubmit(e);
            });

            // Handle Communication Preferences Form
            this.forms.prefs?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handlePreferencesSubmit(e);
            });
        }

        /**
         * Handle Privacy Settings Form (Form 2)
         */
        async handlePrivacySubmit(e) {
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            const original = btn.innerHTML;

            btn.disabled = true;
            btn.textContent = 'Updating...';

            // Map checkboxes to 1 or 0 for the database
            const payload = {
                show_activity: form.show_activity?.checked ? 1 : 0,
                show_badges: form.show_badges?.checked ? 1 : 0
            };

            try {
                const res = await api(`/api/${SITE_SLUG}/member/settings/privacy`, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
                UI.toast('Privacy settings updated', 'success');
            } catch (err) {
                UI.toast('Error saving privacy settings', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }

        /**
         * Updates Name/Email/Phone
         */
        async handleDetailsSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            const original = btn.innerHTML;

            const payload = {
                first_name: form.first_name.value,
                last_name: form.last_name.value,
                email: form.email.value,
                // phone: form.phone.value
            };

            try {
                btn.disabled = true;
                btn.textContent = 'Saving...';
                const res = await api(`/api/${SITE_SLUG}/member/account-details`, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
                this.store.setState({
                    member: {
                        ...this.store.state.member,
                        ...payload,
                    }
                });
                UI.toast('Account details updated successfully', 'success');
            } catch (_) {
                UI.toast('Failed to connect to server', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }

        /**
         * Updates Toggles (Marketing, Newsletter, etc)
         */
        async handlePreferencesSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            const original = btn.innerHTML;

            const payload = {
                marketing_emails: form.marketing_emails.checked ? 1 : 0,
                special_offers: form.special_offers.checked ? 1 : 0,
                product_updates: form.product_updates.checked ? 1 : 0,
                newsletter: form.newsletter.checked ? 1 : 0,
                third_party_communications: form.third_party_communications.checked ? 1 : 0
            };

            try {
                btn.disabled = true;
                btn.textContent = 'Saving...';
                const res = await api(`/api/${SITE_SLUG}/member/settings/communication-preferences`, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
                this.store.setState({
                    preferences: {
                        ...this.store.state.preferences,
                        ...payload,
                    }
                });
                UI.toast('Communication preferences saved', 'success');
            } catch (_) {
                UI.toast('Failed to connect to server', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }
    }

    /* ─── BOOTSTRAP ─────────────────────────────────────────── */

    document.addEventListener('DOMContentLoaded', () => {
        // Initializing the manager
        window.settingsApp = new SettingsManager();
    });
</script>
</body>
</html>
