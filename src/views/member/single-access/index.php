<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Access - <?= htmlspecialchars($site->name ?? 'Site') ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .subtitle {
            color: #666;
            margin: 0;
        }

        .access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .access-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .access-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .content-type {
            display: inline-block;
            padding: 4px 12px;
            background: #e7f3ff;
            color: #0066cc;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .content-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 10px 0;
        }

        .access-info {
            color: #666;
            font-size: 14px;
            margin: 10px 0;
        }

        .expiry {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .expiry.expiring-soon {
            background: #fff3cd;
            border-left: 3px solid #ffc107;
        }

        .expiry.expired {
            background: #f8d7da;
            border-left: 3px solid #dc3545;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h2 {
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-expiring {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>My Content Access</h1>
    <p class="subtitle">Manage your purchased content and subscriptions</p>
</div>

<?php if (empty($access_list)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📦</div>
        <h2>No Content Purchased Yet</h2>
        <p>You haven't purchased access to any premium content yet.</p>
        <a href="/" class="btn">Browse Content</a>
    </div>
<?php else: ?>
    <div class="access-grid">
        <?php foreach ($access_list as $access): ?>
            <?php
            $expiresAt = isset($access['expires_at']) ? new DateTime($access['expires_at']) : null;
            $now = new DateTime();
            $isExpiringSoon = false;
            $daysLeft = null;

            if ($expiresAt) {
                $diff = $now->diff($expiresAt);
                $daysLeft = $diff->days;
                $isExpiringSoon = $daysLeft <= 7 && $daysLeft > 0;
            }
            ?>
            <div class="access-card">
                <span class="content-type"><?= htmlspecialchars(ucfirst($access['content_type'])) ?></span>

                <?php if ($access['is_valid']): ?>
                    <span class="status-badge status-active">Active</span>
                <?php elseif ($isExpiringSoon): ?>
                    <span class="status-badge status-expiring">Expiring Soon</span>
                <?php endif; ?>

                <h3 class="content-title"><?= htmlspecialchars($access['content_title']) ?></h3>

                <div class="access-info">
                    <p><strong>Purchased:</strong> <?= date('M j, Y', strtotime($access['purchased_at'])) ?></p>
                </div>

                <?php if ($expiresAt): ?>
                    <div class="expiry <?= $isExpiringSoon ? 'expiring-soon' : '' ?>">
                        <span>⏰</span>
                        <div>
                            <strong>Expires:</strong> <?= $expiresAt->format('M j, Y') ?>
                            <?php if ($daysLeft !== null && $daysLeft > 0): ?>
                                <br><small><?= $daysLeft ?> days remaining</small>
                            <?php elseif ($daysLeft === 0): ?>
                                <br><small>Expires today</small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="expiry">
                        <span>♾️</span>
                        <div><strong>Lifetime Access</strong></div>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 15px;">
                    <a href="#" class="btn">View Content</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</body>
</html>