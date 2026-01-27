<?php
/**
 * @var \App\Models\Site $site
 * @var \App\Models\Newsletter $newsletter
 * @var string $html
 * @var string|null $token
 */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($newsletter->subject ?? 'Newsletter') ?> - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f8f9fa;
            color: #2c3e50;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 32px;
            font-size: 15px;
        }

        .back-link:hover {
            color: #5a67d8;
        }

        .newsletter-header {
            background: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .newsletter-date {
            font-size: 14px;
            color: #667eea;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }

        .newsletter-title {
            font-size: 36px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .newsletter-meta {
            display: flex;
            gap: 24px;
            font-size: 14px;
            color: #718096;
        }

        .newsletter-content {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 32px;
        }

        .newsletter-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .newsletter-content h1,
        .newsletter-content h2,
        .newsletter-content h3 {
            color: #1a202c;
            margin-top: 32px;
            margin-bottom: 16px;
            line-height: 1.3;
        }

        .newsletter-content h1 {
            font-size: 32px;
        }

        .newsletter-content h2 {
            font-size: 26px;
        }

        .newsletter-content h3 {
            font-size: 22px;
        }

        .newsletter-content p {
            margin-bottom: 16px;
            color: #4a5568;
            font-size: 16px;
        }

        .newsletter-content a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .newsletter-content a:hover {
            color: #5a67d8;
            text-decoration: underline;
        }

        .newsletter-content ul,
        .newsletter-content ol {
            margin-bottom: 16px;
            padding-left: 24px;
        }

        .newsletter-content li {
            margin-bottom: 8px;
            color: #4a5568;
        }

        .newsletter-footer {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .newsletter-footer p {
            font-size: 14px;
            color: #718096;
            margin-bottom: 16px;
        }

        .unsubscribe-link {
            color: #718096;
            text-decoration: none;
            font-size: 13px;
        }

        .unsubscribe-link:hover {
            color: #4a5568;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .newsletter-header,
            .newsletter-content,
            .newsletter-footer {
                padding: 24px;
            }

            .newsletter-title {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters" class="back-link">
        ← Back to All Newsletters
    </a>

    <div class="newsletter-header">
        <div class="newsletter-date">
            <?= $newsletter->created_at->format('F d, Y') ?>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <h1 class="newsletter-title">
                <?= htmlspecialchars($newsletter->title ?? 'Newsletter') ?>
            </h1>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/<?= $newsletter->id ?>/download<?= $token ? '?token=' . $token : '' ?>"
               class="download-btn"
               style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
                📥 Download PDF
            </a>
        </div>
        <div class="newsletter-meta">
            <span>📧 Newsletter</span>
            <?php if ($newsletter->isAutomated()): ?>
                <span>🤖 Automated</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="newsletter-content">
        <?= $html ?>
    </div>

    <div class="newsletter-footer">
        <p>
            You're receiving this because you subscribed to <?= htmlspecialchars($site->name) ?>
        </p>
        <?php if ($token): ?>
            <a href="/member/subscriptions/unsubscribe/<?= $token ?>" class="unsubscribe-link">
                Unsubscribe from future emails
            </a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>