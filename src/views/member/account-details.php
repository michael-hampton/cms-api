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
    </style>
</head>
<body>

@include('member._header')

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
    document.addEventListener('DOMContentLoaded', load);
    const SITE_SLUG = '<?= \App\Framework\Support\SiteContext::slug() ?>';

    async function load() {
        const res = await fetch('/api/' + SITE_SLUG + '/member/account-details');
        const json = await res.json();

        if (!json.success) return;

        const {member, preferences, site_slug} = json.data;

        hydrate(member, preferences, site_slug);
    }

    function hydrate(member, preferences, slug) {

        // links
        document.getElementById('dashboardLink').href = `/${slug}/member/dashboard`;
        document.getElementById('cancelLink').href = `/${slug}/member/dashboard`;
        document.getElementById('backLink').href = `/${slug}/member/dashboard`;
        document.getElementById('passwordLink').href = `/${slug}/member/settings`;
        document.getElementById('verificationLink').href = `/${slug}/member/resend-verification`;

        // basic fields
        setValue('first_name', member.first_name);
        setValue('last_name', member.last_name);
        const fullName = `${member.first_name || ''} ${member.last_name || ''}`.trim();

// If display_name exists, use it, otherwise fallback
        setValue('display_name', member.display_name || fullName);

// Always show fallback as placeholder
        setText('display_name_placeholder', fullName);
        setValue('email', member.email);

        setText('display_name_placeholder', member.first_name + ' ' + member.last_name);

        setText('id', `#${member.id}`);

        setText('created_at', formatDate(member.created_at));
        setText('last_login_at', formatDate(member.last_login_at, true));

        // badges
        setHTML('account_status',
            member.is_active
                ? '<span class="badge success">✓ Active</span>'
                : '<span class="badge warning">⚠ Inactive</span>'
        );

        const verified = !!member.email_verified_at;

        setHTML('email_status',
            verified
                ? '<span class="badge success">✓ Verified</span>'
                : '<span class="badge warning">⚠ Not Verified</span>'
        );

        if (!verified) {
            document.getElementById('verificationLink').style.display = 'inline-block';
        }

        // roles
        const roleContainer = document.querySelector('[data-bind="roles"]');
        roleContainer.innerHTML = '';
        member.roles.forEach(r => {
            const span = document.createElement('span');
            span.className = 'badge info';
            span.textContent = r.name;
            roleContainer.appendChild(span);
        });

        // preferences
        setCheckbox('marketing_emails', preferences?.marketing_emails ?? true);
        setCheckbox('special_offers', preferences?.special_offers ?? true);
        setCheckbox('product_updates', preferences?.product_updates ?? true);
        setCheckbox('newsletter', preferences?.newsletter ?? true);
        setCheckbox('third_party_communications', preferences?.third_party_communications ?? false);

        document.getElementById('siteNameText').innerText = `Manage how ${slug} communicates with you`;
        setCheckbox('show_activity', member.show_activity ?? false);
        setCheckbox('show_badges', member.show_badges ?? false);
    }

    function setValue(key, val) {
        document.querySelectorAll(`[data-bind="${key}"]`).forEach(el => el.value = val || '');
    }

    function setText(key, val) {
        document.querySelectorAll(`[data-bind="${key}"]`).forEach(el => el.textContent = val || '');
    }

    function setHTML(key, val) {
        document.querySelectorAll(`[data-bind="${key}"]`).forEach(el => el.innerHTML = val);
    }

    function setCheckbox(name, val) {
        const el = document.querySelector(`input[name="${name}"]`);
        if (el) el.checked = !!val;
    }

    function formatDate(obj, withTime = false) {
        if (!obj || !obj.date) return 'N/A';

        const d = new Date(obj.date);

        const datePart = d.toLocaleDateString('en-GB', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        if (!withTime) return datePart;

        const timePart = d.toLocaleTimeString('en-GB', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });

        return `${datePart} ${timePart}`;
    }

    document.getElementById('accountForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const form = e.target;

        const payload = {
            first_name: form.first_name.value,
            last_name: form.last_name.value,
            display_name: form.display_name.value,
            email: form.email.value
        };

        const res = await fetch('/api/' + SITE_SLUG + '/member/account-details', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const json = await res.json();

        handleResponse(json, 'Account details updated');
    });

    document.getElementById('privacyForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const form = e.target;

        const payload = {
            show_activity: form.show_activity.checked ? 1 : 0,
            show_badges: form.show_badges.checked ? 1 : 0
        };

        const res = await fetch('/api/' + SITE_SLUG + '/member/settings/privacy', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const json = await res.json();

        handleResponse(json, 'Privacy settings updated');
    });

    document.getElementById('preferencesForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const form = e.target;

        const payload = {
            marketing_emails: form.marketing_emails.checked ? 1 : 0,
            special_offers: form.special_offers.checked ? 1 : 0,
            product_updates: form.product_updates.checked ? 1 : 0,
            newsletter: form.newsletter.checked ? 1 : 0,
            third_party_communications: form.third_party_communications.checked ? 1 : 0
        };

        const res = await fetch('/api/' + SITE_SLUG + '/member/settings/communication-preferences', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const json = await res.json();

        handleResponse(json, 'Preferences saved');
    });

    function handleResponse(json, successMessage) {
        const container = document.getElementById('messageContainer');

        if (json.success) {
            container.innerHTML = `
            <div class="message success">
                <span>✓</span>
                ${successMessage}
            </div>
        `;
        } else {
            container.innerHTML = `
            <div class="message error">
                <span>✕</span>
                ${json.message || 'Something went wrong'}
            </div>
        `;
        }

        window.scrollTo({top: 0, behavior: 'smooth'});
    }
</script>
</body>
</html>