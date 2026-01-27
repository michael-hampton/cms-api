<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Complete</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .success-card {
            background: white;
            border-radius: 16px;
            padding: 48px;
            max-width: 600px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .success-icon {
            font-size: 80px;
            margin-bottom: 24px;
            animation: bounce 1s ease;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        h1 {
            font-size: 32px;
            color: #1a202c;
            margin-bottom: 16px;
        }

        .access-info {
            background: #f7fafc;
            border-radius: 8px;
            padding: 24px;
            margin: 32px 0;
            text-align: left;
        }

        .access-info h3 {
            margin-top: 0;
            color: #667eea;
        }

        .access-info ul {
            list-style: none;
            padding: 0;
        }

        .access-info li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-primary {
            display: inline-block;
            padding: 16px 32px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<div class="success-card">
    <div class="success-icon">🎉</div>
    <h1>Purchase Complete!</h1>
    <p>Thank you for your purchase. You now have access to:</p>

    <div class="access-info">
        <h3><?= htmlspecialchars($content->title ?? 'Premium Content') ?></h3>
        <ul>
            <li>✅ Full archive access</li>
            <li>📅 Valid until: <?= $access->expires_at->format('F d, Y') ?></li>
            <li>💾 Download all editions</li>
            <li>📧 Confirmation email sent</li>
        </ul>
    </div>

    <a href="/newsletters/<?= $content->id ?>/archive" class="btn-primary">
        View Your Content
    </a>

    <p style="margin-top: 24px; font-size: 14px; color: #718096;">
        You can access this content anytime from your account dashboard
    </p>
</div>
</body>
</html>