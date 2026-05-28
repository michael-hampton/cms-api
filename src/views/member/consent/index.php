<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy & Consent Preferences - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
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

        /* ── Two-column page layout ─────────────────────── */
        .page-layout {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            align-items: start;
        }

        .page-layout-left {
            min-width: 0;
        }

        .page-layout-right {
            position: sticky;
            top: 2rem;
        }

        /* ── Shared card shell ──────────────────────────── */
        .card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        /* ── Page header ────────────────────────────────── */
        .page-header {
            margin-bottom: 2rem;
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
            font-size: 1rem;
        }

        /* ── Alert banner ───────────────────────────────── */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: .75rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            animation: slideDown .3s ease-out;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        @keyframes slideDown {
            from {
                transform: translateY(-12px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* ── Actions bar ────────────────────────────────── */
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* ── Buttons ────────────────────────────────────── */
        .btn {
            padding: .75rem 1.5rem;
            border: none;
            border-radius: .75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .3s;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            text-decoration: none;
            font-size: .9375rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, .3);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
        }

        /* ── Consent categories ─────────────────────────── */
        .category-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .category-icon {
            width: 3rem;
            height: 3rem;
            border-radius: .75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .category-icon.essential {
            background: linear-gradient(135deg, #ef444420, #dc262620);
        }

        .category-icon.functional {
            background: linear-gradient(135deg, #3b82f620, #2563eb20);
        }

        .category-icon.analytics {
            background: linear-gradient(135deg, #f59e0b20, #d9770620);
        }

        .category-icon.marketing {
            background: linear-gradient(135deg, #10b98120, #059f6920);
        }

        .category-icon.preferences {
            background: linear-gradient(135deg, #8b5cf620, #7c3aed20);
        }

        .category-title {
            font-size: 1.5rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        /* ── Consent items ──────────────────────────────── */
        .consent-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .consent-item:last-child {
            border-bottom: none;
        }

        .consent-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: .75rem;
        }

        .consent-info {
            flex: 1;
        }

        .consent-name {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: .5rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .required-badge {
            background: var(--danger-color);
            color: white;
            padding: .125rem .5rem;
            border-radius: .25rem;
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .consent-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: .5rem;
        }

        .consent-status {
            display: inline-flex;
            align-items: center;
            gap: .375rem;
            padding: .375rem .75rem;
            border-radius: .5rem;
            font-size: .875rem;
            font-weight: 500;
            margin-top: .5rem;
        }

        .consent-status.granted {
            background: #d1fae5;
            color: #065f46;
        }

        .consent-status.not-granted {
            background: #fee2e2;
            color: #991b1b;
        }

        .consent-meta {
            display: flex;
            gap: 1.5rem;
            font-size: .875rem;
            color: var(--text-secondary);
            margin-top: .75rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: .375rem;
        }

        /* ── Toggle switch ──────────────────────────────── */
        .toggle-switch {
            position: relative;
            width: 56px;
            height: 28px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #cbd5e1;
            transition: .3s;
            border-radius: 28px;
        }

        .toggle-slider::before {
            position: absolute;
            content: '';
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background: white;
            transition: .3s;
            border-radius: 50%;
        }

        .toggle-switch input:checked + .toggle-slider {
            background: var(--success-color);
        }

        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(28px);
        }

        .toggle-switch input:disabled + .toggle-slider {
            cursor: not-allowed;
            opacity: .5;
        }

        /* ── Data rights ────────────────────────────────── */
        .rights-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .rights-list {
            display: grid;
            gap: 1rem;
        }

        .right-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: .75rem;
        }

        .right-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: .5rem;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .right-content h3 {
            font-size: 1rem;
            margin-bottom: .25rem;
        }

        .right-content p {
            font-size: .875rem;
            color: var(--text-secondary);
        }

        /* ── Audit-trail sidebar panel ──────────────────── */
        .audit-panel {
            overflow: hidden;
        }

        .audit-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .audit-panel-title {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: .2rem;
        }

        .audit-panel-sub {
            font-size: .8125rem;
            color: var(--text-secondary);
        }

        .audit-panel-link {
            font-size: .8125rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            white-space: nowrap;
            margin-left: 1rem;
        }

        .audit-panel-link:hover {
            text-decoration: underline;
        }

        /* Filter row */
        .audit-filters {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            margin-bottom: 1rem;
        }

        .audit-filter-select {
            width: 100%;
            padding: .5rem .75rem;
            border: 1px solid var(--border-color);
            border-radius: .5rem;
            font-size: .8125rem;
            background: white;
            color: var(--text-primary);
        }

        /* Timeline entries */
        .audit-timeline {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .audit-entry {
            display: flex;
            gap: .75rem;
            padding: .875rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .audit-entry:last-child {
            border-bottom: none;
        }

        .audit-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: .3rem;
        }

        .audit-dot.granted {
            background: var(--success-color);
        }

        .audit-dot.revoked {
            background: var(--danger-color);
        }

        .audit-dot.updated {
            background: var(--warning-color);
        }

        .audit-dot.expired {
            background: var(--text-secondary);
        }

        .audit-entry-body {
            flex: 1;
            min-width: 0;
        }

        .audit-badge {
            display: inline-block;
            padding: .2rem .55rem;
            border-radius: .35rem;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: .25rem;
        }

        .audit-badge.granted {
            background: #d1fae5;
            color: #065f46;
        }

        .audit-badge.revoked {
            background: #fee2e2;
            color: #991b1b;
        }

        .audit-badge.updated {
            background: #fef3c7;
            color: #92400e;
        }

        .audit-badge.expired {
            background: #f3f4f6;
            color: #4b5563;
        }

        .audit-consent-name {
            font-size: .875rem;
            font-weight: 600;
        }

        .audit-meta {
            font-size: .75rem;
            color: var(--text-secondary);
            margin-top: .2rem;
        }

        .audit-state-change {
            font-size: .75rem;
            color: var(--text-secondary);
            margin-top: .25rem;
        }

        .audit-timestamp {
            font-size: .7rem;
            color: var(--text-secondary);
            white-space: nowrap;
            flex-shrink: 0;
            margin-top: .3rem;
        }

        .audit-empty {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--text-secondary);
            font-size: .875rem;
        }

        .audit-loading {
            padding: 1.5rem 0;
            color: var(--text-secondary);
            font-size: .875rem;
            text-align: center;
        }

        .audit-error {
            color: var(--danger-color);
            font-size: .875rem;
            padding: 1rem 0;
        }

        /* ── Responsive ─────────────────────────────────── */
        @media (max-width: 1100px) {
            .page-layout {
                grid-template-columns: 1fr;
            }

            .page-layout-right {
                position: static;
                order: -1; /* audit trail above consent items on small screens */
            }
        }

        @media (max-width: 768px) {
            .page-layout {
                padding: 1rem;
            }

            .consent-header {
                flex-direction: column;
                gap: 1rem;
            }

            .actions-bar {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .btn-group {
                flex-direction: column;
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

<main class="page-layout">

    <!-- ── LEFT COLUMN: consent options ──────────────────── -->
    <div class="page-layout-left">

        <div class="card page-header">
            <h1 class="page-title"><span>🔒</span> Privacy & Consent Preferences</h1>
            <p class="page-subtitle">
                Control how your personal data is used. You can change these settings at any time.
            </p>
        </div>

        <div id="alert-container"></div>

        <div class="card actions-bar">
            <div>
                <strong>Last Updated:</strong>
                <span id="last-updated">Never</span>
            </div>
            <div class="btn-group">
                <button id="save-btn" class="btn btn-primary">💾 Save All Changes</button>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/consent/download-data"
                   class="btn btn-secondary">
                    📥 Download My Data
                </a>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/consent/audit-trail"
                   class="btn btn-secondary">
                    📋 Full History
                </a>
            </div>
        </div>

        <!-- Dynamic consent categories injected here -->
        <div id="consentsContainer"></div>

        <!-- Static communication preferences -->
        <div class="card">
            <div class="category-header">
                <div class="category-icon preferences">📧</div>
                <div>
                    <h2 class="category-title">Email & Communication Preferences</h2>
                    <p style="color:var(--text-secondary);font-size:.875rem;">
                        Control what types of emails and communications you receive
                    </p>
                </div>
            </div>

            <?php
            $commPrefs = [
                    ['key' => 'marketing_emails', 'label' => 'Marketing Emails', 'desc' => 'Receive promotional content, product updates, and marketing communications', 'default' => true, 'hint' => null],
                    ['key' => 'newsletter', 'label' => 'Newsletter Subscription', 'desc' => 'Receive our regular newsletter with updates, articles, and curated content', 'default' => true, 'hint' => ['icon' => '📬', 'link' => '/' . (\App\Framework\Support\SiteContext::slug()) . '/member/subscriptions/preferences', 'text' => 'Manage newsletter preferences →']],
                    ['key' => 'special_offers', 'label' => 'Special Offers & Promotions', 'desc' => "Be the first to know about exclusive deals, discounts, and special offers", 'default' => true, 'hint' => null],
                    ['key' => 'product_updates', 'label' => 'Product Updates', 'desc' => 'Get notified about new features, improvements, and product announcements', 'default' => true, 'hint' => null],
                    ['key' => 'third_party_communications', 'label' => 'Third-Party Communications', 'desc' => 'Allow carefully selected partners to send you relevant offers and information', 'default' => false, 'hint' => ['icon' => '⚠️', 'text' => 'We never sell your data. Partners are carefully vetted.']],
            ];
            foreach ($commPrefs as $pref):
                $enabled = $member->getCommunicationPreference($pref['key'], $pref['default']);
                ?>
                <div class="consent-item">
                    <div class="consent-header">
                        <div class="consent-info">
                            <div class="consent-name"><?= htmlspecialchars($pref['label']) ?></div>
                            <div class="consent-description"><?= htmlspecialchars($pref['desc']) ?></div>
                            <span class="consent-status <?= $enabled ? 'granted' : 'not-granted' ?>">
                            <?= $enabled ? '✓ Active' : '✕ Not Active' ?>
                        </span>
                            <?php if ($pref['hint']): ?>
                                <div class="consent-meta">
                                    <div class="meta-item">
                                        <span><?= $pref['hint']['icon'] ?></span>
                                        <span>
                                    <?php if (isset($pref['hint']['link'])): ?>
                                        <a href="<?= htmlspecialchars($pref['hint']['link']) ?>"
                                           style="color:var(--primary-color);text-decoration:none;">
                                            <?= htmlspecialchars($pref['hint']['text']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($pref['hint']['text']) ?>
                                    <?php endif ?>
                                </span>
                                    </div>
                                </div>
                            <?php endif ?>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox"
                                    <?= $enabled ? 'checked' : '' ?>
                                   data-pref-key="<?= htmlspecialchars($pref['key']) ?>">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            <?php endforeach ?>
        </div>

        <!-- Data rights -->
        <div class="card">
            <h2 class="rights-title"><span>⚖️</span> Your Data Rights</h2>
            <div class="rights-list">
                <?php
                $rights = [
                        ['📥', 'Right to Access', 'Download a copy of all your personal data we hold'],
                        ['✏️', 'Right to Rectification', 'Update or correct any inaccurate personal information'],
                        ['🗑️', 'Right to Erasure', 'Request deletion of your personal data (subject to legal obligations)'],
                        ['🚫', 'Right to Object', 'Object to processing of your data for specific purposes'],
                ];
                foreach ($rights as [$icon, $title, $desc]):
                    ?>
                    <div class="right-item">
                        <div class="right-icon"><?= $icon ?></div>
                        <div class="right-content">
                            <h3><?= htmlspecialchars($title) ?></h3>
                            <p><?= htmlspecialchars($desc) ?></p>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
            <div style="margin-top:1.5rem;text-align:center;">
                <button id="delete-btn" class="btn btn-danger">🗑️ Request Account Deletion</button>
            </div>
        </div>
    </div>

    <!-- ── RIGHT COLUMN: audit trail ─────────────────────── -->
    <div class="page-layout-right">
        <div class="card audit-panel">
            <div class="audit-panel-header">
                <div>
                    <div class="audit-panel-title">📋 Consent History</div>
                    <div class="audit-panel-sub">Every change to your consent preferences</div>
                </div>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/consent/audit-trail"
                   class="audit-panel-link">Full page →</a>
            </div>

            <div class="audit-filters">
                <select class="audit-filter-select" id="auditActionFilter">
                    <option value="">All Actions</option>
                    <option value="granted">Granted</option>
                    <option value="revoked">Revoked</option>
                    <option value="updated">Updated</option>
                    <option value="expired">Expired</option>
                </select>
                <select class="audit-filter-select" id="auditConsentFilter">
                    <option value="">All Consents</option>
                </select>
            </div>

            <div id="audit-panel-body">
                <div class="audit-loading">Loading history…</div>
            </div>
        </div>
    </div>

</main>

<script>
    /**
     * Global Configuration & Data
     */
    const API_BASE = '/api/<?= $site->slug ?>';
    const MEMBER_ID = <?= (int)$member->id ?>;

    const CATEGORY_INFO = {
        marketing: {
            icon: '📢',
            title: 'Marketing & Communications',
            desc: 'How we keep you updated with news, offers, and personalized content.'
        },
        technical: {
            icon: '⚙️',
            title: 'Technical & Functional',
            desc: 'Necessary preferences for the website to function as expected.'
        },
        privacy: {
            icon: '🔒',
            title: 'Data & Privacy',
            desc: 'Manage how your personal data is handled and processed.'
        },
        other: {
            icon: '📄',
            title: 'Other Preferences',
            desc: 'Miscellaneous consent settings.'
        }
    };

    /**
     * Component: Consent Toggle Item
     */
    class ConsentItem {
        constructor(item, manager) {
            this.raw = item;
            this.data = item.consent_type;
            this.manager = manager;
        }

        render() {
            const pendingValue = this.manager.store.state.pendingChanges[this.data.code];
            const isGranted = pendingValue !== undefined
                ? Boolean(Number(pendingValue))
                : (this.raw.is_granted === true || this.raw.is_granted === 1 || this.raw.is_granted === '1');
            const isLocked = this.raw.is_locked === true || this.raw.is_locked === 1;

            const statusBadge = UI.el('span', {
                className: `consent-status ${isGranted ? 'granted' : 'not-granted'}`
            }, [isGranted ? '✓ Active' : '✕ Not Active']);

            const inputProps = {
                type: 'checkbox',
                onchange: (e) => {
                    const checked = e.target.checked;

                    // Update the badge UI immediately (Reactive)
                    statusBadge.textContent = checked ? '✓ Active' : '✕ Not Active';
                    statusBadge.className = `consent-status ${checked ? 'granted' : 'not-granted'}`;

                    // Sync with API
                    this.manager.toggleConsent(this.data.code, checked);
                }
            };

            if (isGranted) inputProps.checked = true;
            if (isLocked) inputProps.disabled = 'disabled';

            return UI.el('div', {className: 'consent-item'}, [
                UI.el('div', {className: 'consent-header'}, [
                    UI.el('div', {className: 'consent-info'}, [
                        UI.el('div', {className: 'consent-name'}, [
                            this.data.name,
                            isLocked ? UI.el('span', {className: 'badge-required'}, ['REQUIRED']) : null
                        ]),
                        UI.el('div', {className: 'consent-description'}, [this.data.description]),
                        statusBadge, // Insert the pre-defined element here
                        this.renderMeta()
                    ]),
                    UI.el('label', {className: `toggle-switch ${isLocked ? 'disabled' : ''}`}, [
                        UI.el('input', inputProps),
                        UI.el('span', {className: 'toggle-slider'})
                    ])
                ])
            ]);
        }

        renderMeta() {
            if (this.data.code === 'newsletter') {
                return UI.el('div', {className: 'consent-meta'}, [
                    UI.el('div', {className: 'meta-item'}, [
                        UI.el('span', {}, ['📬 ']),
                        UI.el('a', {
                            href: `/${SITE_SLUG}/member/subscriptions/preferences`,
                            style: {color: 'var(--primary-color)', textDecoration: 'none'}
                        }, ['Manage newsletter preferences →'])
                    ])
                ]);
            }
            return null;
        }
    }

    /**
     * Component: Category Card
     */
    class CategoryCard {
        constructor(categoryKey, items, manager) {
            this.categoryKey = categoryKey;
            this.items = items;
            this.manager = manager;
            // Map your API keys (essential, functional, etc.) to the UI display info
            const infoMap = {
                essential: {icon: '🔒', title: 'Essential', desc: 'Required for the site to function.'},
                functional: {icon: '⚙️', title: 'Functional', desc: 'Enhanced features and personalization.'},
                analytics: {icon: '📈', title: 'Analytics', desc: 'Help us improve by understanding usage.'},
                marketing: {icon: '📢', title: 'Marketing', desc: 'News, offers, and targeted content.'},
                preferences: {icon: '📄', title: 'Preferences', desc: 'Your communication settings.'}
            };
            this.info = infoMap[categoryKey] || {icon: '📄', title: categoryKey, desc: ''};
        }

        render() {
            return UI.el('div', {className: 'card'}, [
                UI.el('div', {className: 'category-header'}, [
                    UI.el('div', {className: `category-icon ${this.categoryKey}`}, [this.info.icon]),
                    UI.el('div', {}, [
                        UI.el('h2', {className: 'category-title'}, [this.info.title]),
                        UI.el('p', {style: {color: 'var(--text-secondary)', fontSize: '.875rem'}}, [this.info.desc])
                    ])
                ]),
                // Loop through the items array provided by the API for this category
                ...this.items.map(item => new ConsentItem(item, this.manager).render())
            ]);
        }
    }

    /**
     * Orchestrator: Privacy Manager
     */
    class PrivacyStore {
        constructor() {
            this.state = {
                groupedConsents: {},
                audits: [],
                pendingChanges: {},
                loadingConsents: false,
                loadingAudit: false,
                saving: false,
                error: null,
                auditFilters: {
                    action: '',
                    consent: '',
                },
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

    class PrivacyManager {
        constructor() {
            this.prefsContainer = document.getElementById('consentsContainer');
            this.auditContainer = document.getElementById('audit-panel-body');
            this.store = new PrivacyStore();
            this.store.subscribe(state => this.render(state));
            this.init();
        }

        async init() {
            this.prefsContainer.innerHTML = '<div class="loading">Loading your preferences...</div>';
            await Promise.all([this.loadConsents(), this.loadAudit()]);
            this.wireFilters();
            this.wireAccountActions();
            this.wireSaveButton();
        }

        async loadConsents() {
            try {
                this.store.setState({loadingConsents: true});
                const res = await api(`${API_BASE}/member/consent?member_id=${MEMBER_ID}`);
                this.store.setState({
                    groupedConsents: res.items || {},
                    loadingConsents: false,
                });
                this.populateConsentFilter(res.items || {});
            } catch (_) {
                this.store.setState({loadingConsents: false});
                UI.toast('Failed to load preferences', 'error');
            }
        }

        render(state) {
            if (!state.loadingConsents) {
                this.renderConsents(state.groupedConsents);
            }

            if (!state.loadingAudit) {
                this.renderAudit(state.audits);
            }

            const savePrompt = document.getElementById('savePrompt');
            if (savePrompt) {
                savePrompt.classList.toggle('active', Object.keys(state.pendingChanges).length > 0);
            }
        }

        renderConsents(groupedData) {
            const categories = Object.keys(groupedData);

            if (categories.length === 0) {
                UI.render(this.prefsContainer, UI.emptyState({title: 'No preferences found'}));
                return;
            }

            // Map the object entries directly to CategoryCards
            const cards = Object.entries(groupedData).map(([category, items]) => {
                return new CategoryCard(category, items, this).render();
            });

            UI.render(this.prefsContainer, cards);
        }

        populateConsentFilter(groupedData) {
            const select = document.getElementById('auditConsentFilter');
            if (!select) return;

            const firstOption = select.options[0];
            select.innerHTML = '';
            select.appendChild(firstOption);

            // Flatten the grouped data to get all consent types for the filter
            Object.values(groupedData).flat().forEach(item => {
                const c = item.consent_type;
                select.appendChild(UI.el('option', {value: c.code}, [c.name]));
            });
        }

        wireSaveButton() {
            const btn = document.getElementById('save-btn');
            if (btn) {
                btn.onclick = () => this.saveAllConsents();
            }
        }

        toggleConsent(code, isGranted) {
            this.store.setState({
                pendingChanges: {
                    ...this.store.state.pendingChanges,
                    [code]: isGranted ? 1 : 0,
                }
            });
        }

        async saveAllConsents() {
            const codes = Object.keys(this.store.state.pendingChanges);
            if (codes.length === 0) {
                UI.toast('No changes to save', 'error');
                return;
            }

            try {
                this.store.setState({saving: true});
                await api(`${API_BASE}/member/consent/update`, {
                    method: 'POST',
                    body: JSON.stringify({
                        member_id: MEMBER_ID,
                        consents: this.store.state.pendingChanges,
                        source: 'web_portal'
                    })
                });

                UI.toast('All preferences have been synchronized', 'success');
                window.scrollTo({top: 0, behavior: 'smooth'});

                this.store.setState({pendingChanges: {}});

                await Promise.all([this.loadConsents(), this.loadAudit()]);

            } catch (e) {
                UI.toast('Failed to save: ' + e.message, 'error');
            } finally {
                this.store.setState({saving: false});
            }
        }

        async loadAudit() {
            try {
                this.store.setState({loadingAudit: true});
                const data = await api(`${API_BASE}/member/consent/audit-history?member_id=${MEMBER_ID}`);
                this.store.setState({
                    audits: data.items || [],
                    loadingAudit: false,
                });
            } catch (_) {
                this.store.setState({loadingAudit: false});
            }
        }

        renderAudit(items) {
            const actionFilter = this.store.state.auditFilters.action;
            const typeFilter = this.store.state.auditFilters.consent;
            const filtered = items.filter(e => {
                return (!actionFilter || e.action === actionFilter)
                    && (!typeFilter || e.consent_type?.code === typeFilter);
            });
            UI.render(this.auditContainer, filtered.length
                ? filtered.map(e => new AuditSidebarEntry(e).render())
                : [UI.el('div', {className: 'empty-state'}, [
                    UI.el('p', {}, ['No audit entries match the selected filters.'])
                ])]);
        }

        wireFilters() {
            ['auditActionFilter', 'auditConsentFilter'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.onchange = () => this.store.setState({
                        auditFilters: {
                            action: document.getElementById('auditActionFilter').value,
                            consent: document.getElementById('auditConsentFilter').value,
                        }
                    });
                }
            });
        }

        wireAccountActions() {
            window.downloadData = async () => {
                try {
                    const res = await api(`${API_BASE}/member/export-data?member_id=${MEMBER_ID}`, {method: 'POST'});
                    if (res.download_url) window.location.href = res.download_url;
                } catch (e) {
                    UI.toast('Export failed', 'error');
                }
            };
            window.confirmDeleteAccount = () => {
                if (confirm("Are you sure? This will revoke all consents and delete your account.")) {
                    api(`${API_BASE}/member/delete-account`, {
                        method: 'POST',
                        body: JSON.stringify({member_id: MEMBER_ID})
                    })
                        .then(() => window.location.href = '/logout');
                }
            };
        }
    }

    /** * Sidebar Audit Component (unchanged logic, just ensuring property names match)
     */
    class AuditSidebarEntry {
        constructor(e) {
            this.e = e;
        }

        render() {
            const action = this.e.action.toLowerCase();
            // Access name from nested consent_type object
            const name = this.e.consent_type ? this.e.consent_type.name : 'Unknown';

            return UI.el('div', {className: 'audit-entry'}, [
                UI.el('div', {className: `audit-dot ${action}`}),
                UI.el('div', {className: 'audit-entry-body'}, [
                    UI.el('span', {className: `audit-badge ${action}`}, [this.e.action]),
                    UI.el('div', {className: 'audit-consent-name'}, [name]),
                    UI.el('div', {className: 'audit-timestamp'}, [
                        new Date(this.e.created_at.replace(/-/g, "/")).toLocaleDateString('en-GB', {
                            day: 'numeric',
                            month: 'short'
                        })
                    ])
                ])
            ]);
        }
    }

    // Initialize on Load
    document.addEventListener('DOMContentLoaded', () => {
        window.privacyApp = new PrivacyManager();
    });
</script>
</body>
</html>
