<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gifted Articles - <?= htmlspecialchars($site->name) ?></title>
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
    </style>
</head>
<body>
@include('member._header')

<div class="container" style="margin-top: 40px;">
    <div class="card">
        <h2>Your Gift Allowance</h2>
        <div class="info-row">
            <span class="info-label">Remaining Gifts</span>
            <span class="info-value" style="font-size: 24px; color: #667eea;">
                <?= $allowance['remaining_gifts'] ?> / <?= $allowance['annual_limit'] ?>
            </span>
        </div>
    </div>

    <div class="card">
        <h2>Received Gifts (<?= $receivedGifts->count() ?>)</h2>
        <?php if ($receivedGifts->count() > 0): ?>
            <div style="display: grid; gap: 16px;">
                <?php foreach ($receivedGifts as $gift): ?>
                    <div style="padding: 16px; background: #f8fafc; border-radius: 8px;">
                        <h3><?= htmlspecialchars($gift->page->title) ?></h3>
                        <p>Gifted by: <?= htmlspecialchars($gift->giftedBy->full_name) ?></p>
                        <p>Received: <?= $gift->gifted_at->format('M d, Y') ?></p>
                        <?php if ($gift->personal_message): ?>
                            <p style="font-style: italic;">"<?= htmlspecialchars($gift->personal_message) ?>"</p>
                        <?php endif; ?>

                        <?php if ($gift->status === 'pending'): ?>
                            <a href="/gift/<?= htmlspecialchars($gift->gift_token) ?>" class="btn btn-primary">
                                Claim & Read Article
                            </a>
                        <?php else: ?>
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/<?= htmlspecialchars($gift->page->slug) ?>"
                               class="btn btn-primary">
                                Read Article
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No received gifts yet</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Given Gifts (<?= $givenGifts->count() ?>)</h2>
        <?php if ($givenGifts->count() > 0): ?>
            <table class="history-table">
                <thead>
                <tr>
                    <th>Article</th>
                    <th>Recipient</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($givenGifts as $gift): ?>
                    <tr>
                        <td><?= htmlspecialchars($gift->page->title) ?></td>
                        <td><?= htmlspecialchars($gift->recipient_email) ?></td>
                        <td>
                                <span class="badge badge-<?= $gift->isClaimed() ? 'success' : 'warning' ?>">
                                    <?= ucfirst($gift->status) ?>
                                </span>
                        </td>
                        <td><?= $gift->gifted_at->format('M d, Y') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No gifts given yet</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>