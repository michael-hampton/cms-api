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
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease-out;
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

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .consent-category {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

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
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .category-icon.essential {
            background: linear-gradient(135deg, #ef444420 0%, #dc262620 100%);
        }

        .category-icon.functional {
            background: linear-gradient(135deg, #3b82f620 0%, #2563eb20 100%);
        }

        .category-icon.analytics {
            background: linear-gradient(135deg, #f59e0b20 0%, #d9770620 100%);
        }

        .category-icon.marketing {
            background: linear-gradient(135deg, #10b98120 0%, #059f6920 100%);
        }

        .category-icon.preferences {
            background: linear-gradient(135deg, #8b5cf620 0%, #7c3aed20 100%);
        }

        .category-title {
            font-size: 1.5rem;
            font-weight: 600;
            text-transform: capitalize;
        }

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
            margin-bottom: 0.75rem;
        }

        .consent-info {
            flex: 1;
        }

        .consent-name {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .required-badge {
            background: var(--danger-color);
            color: white;
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .consent-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }

        .consent-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.75rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: 0.3s;
            border-radius: 28px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        .toggle-switch input:checked + .toggle-slider {
            background-color: var(--success-color);
        }

        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(28px);
        }

        .toggle-switch input:disabled + .toggle-slider {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .consent-status {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .consent-status.granted {
            background: #d1fae5;
            color: #065f46;
        }

        .consent-status.not-granted {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions-bar {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
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

        .data-rights {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .rights-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
            border-radius: 0.75rem;
        }

        .right-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.5rem;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .right-content h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .right-content p {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .container {
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
        }
    </style>
</head>
<body>

@include('member._header')


<main class="container">
    <div class="page-header">
        <h1 class="page-title">
            <span>🔒</span>
            Privacy & Consent Preferences
        </h1>
        <p class="page-subtitle">
            Control how your personal data is used across our platform. You can change these settings at any time.
        </p>
    </div>

    <div id="alert-container"></div>

    <div class="actions-bar">
        <div>
            <strong>Last Updated:</strong>
            <span id="last-updated">Never</span>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button onclick="saveAllConsents()" class="btn btn-primary">
                💾 Save All Changes
            </button>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/consent/download-data"
               class="btn btn-secondary">
                📥 Download My Data
            </a>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/consent/audit-trail"
               class="btn btn-secondary">
                📋 View History
            </a>
        </div>
    </div>

    <?php
    $categoryInfo = [
            'essential' => ['icon' => '🔒', 'title' => 'Essential', 'description' => 'Required for the website to function'],
            'functional' => ['icon' => '⚙️', 'title' => 'Functional', 'description' => 'Enhance your experience'],
            'analytics' => ['icon' => '📊', 'title' => 'Analytics', 'description' => 'Help us improve our service'],
            'marketing' => ['icon' => '📢', 'title' => 'Marketing', 'description' => 'Personalized content and offers'],
            'preferences' => ['icon' => '🎨', 'title' => 'Preferences', 'description' => 'Remember your settings']
    ];

    foreach ($consents as $category => $categoryConsents):
        $info = $categoryInfo[$category] ?? ['icon' => '📄', 'title' => ucfirst($category), 'description' => ''];
        ?>
        <div class="consent-category">
            <div class="category-header">
                <div class="category-icon <?= $category ?>">
                    <?= $info['icon'] ?>
                </div>
                <div>
                    <h2 class="category-title"><?= $info['title'] ?></h2>
                    <p style="color: var(--text-secondary); font-size: 0.875rem;">
                        <?= $info['description'] ?>
                    </p>
                </div>
            </div>

            <?php foreach ($categoryConsents as $consent): ?>
                <div class="consent-item" data-consent-code="<?= htmlspecialchars($consent['consent_type']['code']) ?>">
                    <div class="consent-header">
                        <div class="consent-info">
                            <div class="consent-name">
                                <?= htmlspecialchars($consent['consent_type']['name']) ?>
                                <?php if ($consent['consent_type']['required']): ?>
                                    <span class="required-badge">Required</span>
                                <?php endif; ?>
                            </div>
                            <div class="consent-description">
                                <?= htmlspecialchars($consent['consent_type']['description']) ?>
                            </div>

                            <?php if ($consent['is_granted']): ?>
                                <span class="consent-status granted">
                            ✓ Active
                        </span>
                            <?php else: ?>
                                <span class="consent-status not-granted">
                            ✕ Not Active
                        </span>
                            <?php endif; ?>

                            <div class="consent-meta">
                                <?php if ($consent['granted_at']): ?>
                                    <div class="meta-item">
                                        <span>📅</span>
                                        <span>Granted: <?= $consent['granted_at']->format('M j, Y') ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($consent['expires_at']): ?>
                                    <div class="meta-item">
                                        <span>⏰</span>
                                        <span>Expires: <?= $consent['expires_at']->format('M j, Y') ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($consent['consent_type']['retention_days']): ?>
                                    <div class="meta-item">
                                        <span>🗄️</span>
                                        <span>Data retained for <?= $consent['consent_type']['retention_days'] ?> days</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <label class="toggle-switch">
                            <input
                                    type="checkbox"
                                    <?= $consent['is_granted'] ? 'checked' : '' ?>
                                    <?= $consent['consent_type']['required'] ? 'disabled' : '' ?>
                                    onchange="toggleConsent(this, '<?= htmlspecialchars($consent['consent_type']['code']) ?>')"
                            >
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <!-- Global Communication Preferences Section -->
    <div class="consent-category">
        <div class="category-header">
            <div class="category-icon preferences">
                📧
            </div>
            <div>
                <h2 class="category-title">Email & Communication Preferences</h2>
                <p style="color: var(--text-secondary); font-size: 0.875rem;">
                    Control what types of emails and communications you receive from us
                </p>
            </div>
        </div>

        <div class="consent-item">
            <div class="consent-header">
                <div class="consent-info">
                    <div class="consent-name">
                        Marketing Emails
                    </div>
                    <div class="consent-description">
                        Receive promotional content, product updates, and marketing communications
                    </div>

                    <?php
                    $marketingEnabled = $member->getCommunicationPreference('marketing_emails', true);
                    ?>
                    <span class="consent-status <?= $marketingEnabled ? 'granted' : 'not-granted' ?>">
                    <?= $marketingEnabled ? '✓ Active' : '✕ Not Active' ?>
                </span>

                    <div class="consent-meta">
                        <div class="meta-item">
                            <span>ℹ️</span>
                            <span>Controls all marketing and promotional emails</span>
                        </div>
                    </div>
                </div>

                <label class="toggle-switch">
                    <input
                            type="checkbox"
                            <?= $marketingEnabled ? 'checked' : '' ?>
                            onchange="toggleCommunicationPreference(this, 'marketing_emails')"
                            data-preference="marketing_emails"
                    >
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="consent-item">
            <div class="consent-header">
                <div class="consent-info">
                    <div class="consent-name">
                        Newsletter Subscription
                    </div>
                    <div class="consent-description">
                        Receive our regular newsletter with updates, articles, and curated content
                    </div>

                    <?php
                    $newsletterEnabled = $member->getCommunicationPreference('newsletter', true);
                    ?>
                    <span class="consent-status <?= $newsletterEnabled ? 'granted' : 'not-granted' ?>">
                    <?= $newsletterEnabled ? '✓ Active' : '✕ Not Active' ?>
                </span>

                    <div class="consent-meta">
                        <div class="meta-item">
                            <span>📬</span>
                            <span>
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions/preferences"
                               style="color: var(--primary-color); text-decoration: none;">
                                Manage newsletter preferences →
                            </a>
                        </span>
                        </div>
                    </div>
                </div>

                <label class="toggle-switch">
                    <input
                            type="checkbox"
                            <?= $newsletterEnabled ? 'checked' : '' ?>
                            onchange="toggleCommunicationPreference(this, 'newsletter')"
                            data-preference="newsletter"
                    >
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="consent-item">
            <div class="consent-header">
                <div class="consent-info">
                    <div class="consent-name">
                        Special Offers & Promotions
                    </div>
                    <div class="consent-description">
                        Be the first to know about exclusive deals, discounts, and special offers
                    </div>

                    <?php
                    $offersEnabled = $member->getCommunicationPreference('special_offers', true);
                    ?>
                    <span class="consent-status <?= $offersEnabled ? 'granted' : 'not-granted' ?>">
                    <?= $offersEnabled ? '✓ Active' : '✕ Not Active' ?>
                </span>
                </div>

                <label class="toggle-switch">
                    <input
                            type="checkbox"
                            <?= $offersEnabled ? 'checked' : '' ?>
                            onchange="toggleCommunicationPreference(this, 'special_offers')"
                            data-preference="special_offers"
                    >
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="consent-item">
            <div class="consent-header">
                <div class="consent-info">
                    <div class="consent-name">
                        Product Updates
                    </div>
                    <div class="consent-description">
                        Get notified about new features, improvements, and product announcements
                    </div>

                    <?php
                    $productEnabled = $member->getCommunicationPreference('product_updates', true);
                    ?>
                    <span class="consent-status <?= $productEnabled ? 'granted' : 'not-granted' ?>">
                    <?= $productEnabled ? '✓ Active' : '✕ Not Active' ?>
                </span>
                </div>

                <label class="toggle-switch">
                    <input
                            type="checkbox"
                            <?= $productEnabled ? 'checked' : '' ?>
                            onchange="toggleCommunicationPreference(this, 'product_updates')"
                            data-preference="product_updates"
                    >
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="consent-item">
            <div class="consent-header">
                <div class="consent-info">
                    <div class="consent-name">
                        Third-Party Communications
                    </div>
                    <div class="consent-description">
                        Allow carefully selected partners to send you relevant offers and information
                    </div>

                    <?php
                    $thirdPartyEnabled = $member->getCommunicationPreference('third_party_communications', false);
                    ?>
                    <span class="consent-status <?= $thirdPartyEnabled ? 'granted' : 'not-granted' ?>">
                    <?= $thirdPartyEnabled ? '✓ Active' : '✕ Not Active' ?>
                </span>

                    <div class="consent-meta">
                        <div class="meta-item">
                            <span>⚠️</span>
                            <span>We never sell your data. Partners are carefully vetted.</span>
                        </div>
                    </div>
                </div>

                <label class="toggle-switch">
                    <input
                            type="checkbox"
                            <?= $thirdPartyEnabled ? 'checked' : '' ?>
                            onchange="toggleCommunicationPreference(this, 'third_party_communications')"
                            data-preference="third_party_communications"
                    >
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>

    <div class="data-rights">
        <h2 class="rights-title">
            <span>⚖️</span>
            Your Data Rights
        </h2>
        <div class="rights-list">
            <div class="right-item">
                <div class="right-icon">📥</div>
                <div class="right-content">
                    <h3>Right to Access</h3>
                    <p>Download a copy of all your personal data we hold</p>
                </div>
            </div>
            <div class="right-item">
                <div class="right-icon">✏️</div>
                <div class="right-content">
                    <h3>Right to Rectification</h3>
                    <p>Update or correct any inaccurate personal information</p>
                </div>
            </div>
            <div class="right-item">
                <div class="right-icon">🗑️</div>
                <div class="right-content">
                    <h3>Right to Erasure</h3>
                    <p>Request deletion of your personal data (subject to legal obligations)</p>
                </div>
            </div>
            <div class="right-item">
                <div class="right-icon">🚫</div>
                <div class="right-content">
                    <h3>Right to Object</h3>
                    <p>Object to processing of your data for specific purposes</p>
                </div>
            </div>
        </div>
        <div style="margin-top: 1.5rem; text-align: center;">
            <button onclick="requestDataDeletion()" class="btn btn-danger">
                🗑️ Request Account Deletion
            </button>
        </div>
    </div>
</main>

<script>
    const SITE = '<?= $site->slug ?? 'default' ?>';
    let pendingChanges = {};
    let communicationChanges = {};

    function toggleConsent(checkbox, consentCode) {
        pendingChanges[consentCode] = checkbox.checked;
        updateLastModified();
        showSavePrompt();
    }

    function toggleCommunicationPreference(checkbox, preferenceKey) {
        communicationChanges[preferenceKey] = checkbox.checked;
        updateLastModified();
        showSavePrompt();
    }

    async function saveAllConsents() {
        if (Object.keys(pendingChanges).length === 0 && Object.keys(communicationChanges).length === 0) {
            showAlert('No changes to save', 'error');
            return;
        }

        try {
            // Save consents
            if (Object.keys(pendingChanges).length > 0) {
                const consentResponse = await fetch(`/${SITE}/member/consent/update`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        consents: pendingChanges
                    })
                });

                const consentData = await consentResponse.json();
                if (!consentData.success) {
                    throw new Error(consentData.message);
                }
            }

            // Save communication preferences
            if (Object.keys(communicationChanges).length > 0) {
                const commResponse = await fetch(`/${SITE}/member/communication-preferences`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        preferences: communicationChanges
                    })
                });

                const commData = await commResponse.json();
                if (!commData.success) {
                    throw new Error(commData.message);
                }
            }

            showAlert('✓ All preferences saved successfully', 'success');
            pendingChanges = {};
            communicationChanges = {};
            hideSavePrompt();
            setTimeout(() => location.reload(), 1500);

        } catch (error) {
            console.error('Error saving preferences:', error);
            showAlert('✕ Failed to save preferences: ' + error.message, 'error');
        }
    }

    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alert-container');
        alertContainer.innerHTML = `
        <div class="alert alert-${type}">
            ${message}
        </div>
    `;

        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);

        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function updateLastModified() {
        document.getElementById('last-updated').textContent = new Date().toLocaleString();
    }

    function showSavePrompt() {
        // Could add a sticky bar or notification
    }

    function hideSavePrompt() {
        // Hide the save prompt
    }

    async function requestDataDeletion() {
        if (!confirm('Are you sure you want to request account deletion? This action cannot be undone and will permanently delete all your data.')) {
            return;
        }

        try {
            const response = await fetch(`/${SITE}/member/consent/withdrawal-request`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    type: 'complete_deletion'
                })
            });

            const data = await response.json();

            if (data.success) {
                showAlert('✓ Deletion request submitted. We will process your request within 30 days.', 'success');
            } else {
                showAlert('✕ Failed to submit request: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error requesting deletion:', error);
            showAlert('✕ Failed to submit request', 'error');
        }
    }
</script>
</body>
</html>