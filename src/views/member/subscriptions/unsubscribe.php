<?php
/**
 * @var \App\Models\Site $site
 * @var \App\Models\MemberSubscriptionPreference $preference
 * @var string $token
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
            line-height: 1.6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
        }

        .card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        p {
            color: #7f8c8d;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 5px;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }

        .sad-emoji {
            font-size: 48px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="icon">📧</div>
        <h1>Unsubscribe from Emails</h1>
        <p>
            We're sorry to see you go! If you unsubscribe, you will no longer receive
            email notifications and updates from <?= htmlspecialchars($site->name) ?>.
        </p>

        <div class="sad-emoji">😢</div>

        <p>
            Are you sure you want to unsubscribe from all emails?
        </p>

        <div class="btn-group">
            <form method="POST" action="/member/subscriptions/unsubscribe/<?= htmlspecialchars($token) ?>">
                <button type="submit" class="btn btn-danger">
                    Yes, Unsubscribe Me
                </button>
            </form>

            <a href="/member/subscriptions/preferences" class="btn btn-secondary">
                No, Keep My Subscription
            </a>
        </div>

        <p style="margin-top: 30px; font-size: 14px;">
            Want to customize what you receive instead?
            <a href="/member/subscriptions/preferences" style="color: #3498db; text-decoration: none;">
                Update your preferences
            </a>
        </p>
    </div>
</div>
</body>
</html>