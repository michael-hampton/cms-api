<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Already Have Access</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }

        .message-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 30px;
            margin: 40px 0;
            text-align: center;
        }

        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        h1 {
            color: #155724;
            margin-bottom: 10px;
        }

        .access-info {
            background: white;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #218838;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
<div class="message-box">
    <div class="icon">✓</div>
    <h1>You Already Have Access!</h1>
    <p>You have already purchased access to this <?= htmlspecialchars($content_type) ?>.</p>

    <?php if (isset($access) && $access): ?>
        <div class="access-info">
            <p><strong>Purchased:</strong> <?= $access->purchased_at->format('F j, Y') ?></p>
            <?php if ($access->expires_at): ?>
                <p><strong>Expires:</strong> <?= $access->expires_at->format('F j, Y') ?></p>
                <?php
                $now = new DateTime();
                $expires = $access->expires_at;
                $diff = $now->diff($expires);
                $daysLeft = $diff->days;
                ?>
                <p><strong>Days Remaining:</strong> <?= $daysLeft ?> days</p>
            <?php else: ?>
                <p><strong>Access:</strong> Lifetime</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div>
        <a href="/<?= htmlspecialchars($site->slug) ?>/member/single-access" class="btn btn-secondary">
            View All Access
        </a>
        <a href="/" class="btn">
            Go to Content
        </a>
    </div>
</div>
</body>
</html>