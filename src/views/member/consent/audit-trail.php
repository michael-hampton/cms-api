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
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav a {
            color: var(--text-secondary);
            text-decoration: none;
            margin-left: 1.5rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav a:hover {
            color: var(--primary-color);
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
        }

        .filters {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .filter-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.9375rem;
        }

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
            transition: all 0.3s;
        }

        .timeline-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateX(4px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .action-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
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
            margin-bottom: 0.5rem;
        }

        .consent-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .detail-value {
            font-size: 0.9375rem;
            color: var(--text-primary);
        }

        .state-change {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .state-box {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
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
            opacity: 0.5;
        }

        .export-btn {
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .export-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .timeline::before {
                left: 1rem;
            }

            .timeline-item {
                padding-left: 3rem;
            }

            .timeline-marker {
                left: 0.375rem;
            }

            .filters {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-content">
        <a href="/" class="logo"><?= htmlspecialchars($site->name) ?></a>
        <nav class="nav">
            <a href="/member/dashboard">Dashboard</a>
            <a href="/member/consent">Privacy Settings</a>
            <a href="/member/logout">Logout</a>
        </nav>
    </div>
</header>

<main class="container">
    <div class="page-header">
        <h1 class="page-title">
            <span>📋</span>
            Consent History
        </h1>
        <p class="page-subtitle">
            Complete audit trail of all changes to your consent preferences
        </p>
    </div>

    <div class="filters">
        <div class="filter-group">
            <label class="filter-label">Filter by Action</label>
            <select class="filter-input" id="actionFilter" onchange="filterAuditTrail()">
                <option value="">All Actions</option>
                <option value="granted">Granted</option>
                <option value="revoked">Revoked</option>
                <option value="updated">Updated</option>
                <option value="expired">Expired</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Filter by Consent</label>
            <select class="filter-input" id="consentFilter" onchange="filterAuditTrail()">
                <option value="">All Consents</option>
                <!-- Options populated dynamically -->
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Date Range</label>
            <input type="date" class="filter-input" id="dateFilter" onchange="filterAuditTrail()">
        </div>
        <div class="filter-group" style="display: flex; align-items: flex-end;">
            <a href="/member/consent/download" class="export-btn">
                📥 Export Data
            </a>
        </div>
    </div>

    <?php if (empty($auditTrail)): ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>No Consent History</h3>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                Your consent preference changes will appear here
            </p>
        </div>
    <?php else: ?>
        <div class="timeline" id="auditTimeline">
            <?php foreach ($auditTrail as $entry): ?>
                <div class="timeline-item"
                     data-action="<?= htmlspecialchars($entry['action']) ?>"
                     data-consent="<?= htmlspecialchars($entry['consentType']['code']) ?>"
                     data-date="<?= $entry['created_at']->format('Y-m-d') ?>">
                    <div class="timeline-marker <?= htmlspecialchars($entry['action']) ?>"></div>

                    <div class="timeline-card">
                        <div class="card-header">
                            <div>
                        <span class="action-badge <?= htmlspecialchars($entry['action']) ?>">
                            <?= htmlspecialchars($entry['action']) ?>
                        </span>
                                <h3 class="consent-name">
                                    <?= htmlspecialchars($entry['consentType']['name']) ?>
                                </h3>
                            </div>
                            <div style="text-align: right; font-size: 0.875rem; color: var(--text-secondary);">
                                <?= $entry['created_at']->format('M j, Y') ?><br>
                                <?= $entry['created_at']->format('g:i A') ?>
                            </div>
                        </div>

                        <div class="consent-details">
                            <div class="detail-item">
                                <span class="detail-label">Source</span>
                                <span class="detail-value">
                            <?php
                            $sourceIcons = [
                                    'web' => '🌐',
                                    'email' => '📧',
                                    'api' => '⚙️',
                                    'admin' => '👤',
                                    'system' => '🤖'
                            ];
                            echo $sourceIcons[$entry['source']] ?? '';
                            echo ' ' . ucfirst($entry['source']);
                            ?>
                        </span>
                            </div>

                            <?php if ($entry['ip_address']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">IP Address</span>
                                    <span class="detail-value"><?= htmlspecialchars($entry['ip_address']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($entry['adminUser']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Admin User</span>
                                    <span class="detail-value"><?= htmlspecialchars($entry['adminUser']['email']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($entry['reason']): ?>
                                <div class="detail-item" style="grid-column: 1 / -1;">
                                    <span class="detail-label">Reason</span>
                                    <span class="detail-value"><?= htmlspecialchars($entry['reason']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($entry['previous_state'] !== null): ?>
                            <div class="state-change">
                                <span style="color: var(--text-secondary); font-size: 0.875rem;">Status Changed:</span>
                                <span class="state-box <?= $entry['previous_state'] ? 'granted' : 'revoked' ?>">
                        <?= $entry['previous_state'] ? 'Granted' : 'Not Granted' ?>
                    </span>
                                <span style="color: var(--text-secondary);">→</span>
                                <span class="state-box <?= $entry['new_state'] ? 'granted' : 'revoked' ?>">
                        <?= $entry['new_state'] ? 'Granted' : 'Not Granted' ?>
                    </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
    function filterAuditTrail() {
        const actionFilter = document.getElementById('actionFilter').value;
        const consentFilter = document.getElementById('consentFilter').value;
        const dateFilter = document.getElementById('dateFilter').value;

        const items = document.querySelectorAll('.timeline-item');

        items.forEach(item => {
            let show = true;

            if (actionFilter && item.dataset.action !== actionFilter) {
                show = false;
            }

            if (consentFilter && item.dataset.consent !== consentFilter) {
                show = false;
            }

            if (dateFilter && item.dataset.date !== dateFilter) {
                show = false;
            }

            item.style.display = show ? 'block' : 'none';
        });
    }

    // Populate consent filter options
    window.addEventListener('DOMContentLoaded', () => {
        const auditItems = document.querySelectorAll('.timeline-item');
        const uniqueConsents = new Set();

        auditItems.forEach(item => {
            uniqueConsents.add(item.dataset.consent);
        });

        const consentFilter = document.getElementById('consentFilter');
        uniqueConsents.forEach(consent => {
            const option = document.createElement('option');
            option.value = consent;
            option.textContent = consent.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            consentFilter.appendChild(option);
        });
    });
</script>
</body>
</html>