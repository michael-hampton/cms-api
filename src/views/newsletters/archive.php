<?php
/**
 * @var \App\Models\Site $site
 * @var \App\Framework\Support\Collection $newsletters
 */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Archive - <?= htmlspecialchars($site->name) ?></title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 60px;
        }

        .header h1 {
            font-size: 42px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 16px;
        }

        .header p {
            font-size: 18px;
            color: #718096;
        }

        .timeline {
            position: relative;
            padding-left: 40px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 40px;
        }

        .timeline-dot {
            position: absolute;
            left: -44px;
            top: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }

        .newsletter-item {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .newsletter-item:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        }

        .newsletter-item-date {
            font-size: 13px;
            color: #667eea;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .newsletter-item-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .newsletter-item-excerpt {
            font-size: 15px;
            color: #718096;
            margin-bottom: 16px;
        }

        .newsletter-item-link {
            color: #667eea;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .newsletter-item-link:hover {
            color: #5a67d8;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 24px;
            opacity: 0.5;
        }

        .empty-state h2 {
            font-size: 24px;
            color: #4a5568;
            margin-bottom: 12px;
        }

        .empty-state p {
            font-size: 16px;
            color: #718096;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 32px;
            }

            .timeline {
                padding-left: 30px;
            }

            .timeline-dot {
                left: -34px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📚 Newsletter Archive</h1>
        <p>Browse our complete newsletter history</p>
    </div>

    <?php if ($newsletters->count() > 0): ?>
        <div class="timeline">
            <?php foreach ($newsletters as $newsletter): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="newsletter-item" onclick="window.location.href='/newsletters/<?= $newsletter->id ?>'">
                        <div class="newsletter-item-date">
                            <?= $newsletter->created_at->format('F d, Y') ?>
                        </div>
                        <h3 class="newsletter-item-title">
                            <?= htmlspecialchars($newsletter->subject ?? 'Untitled Newsletter') ?>
                        </h3>
                        <?php if (!empty($newsletter->preview_text)): ?>
                            <p class="newsletter-item-excerpt">
                                <?= htmlspecialchars(substr($newsletter->preview_text, 0, 150)) ?>...
                            </p>
                        <?php endif; ?>
                        <a href="/newsletters/<?= $newsletter->id ?>" class="newsletter-item-link"
                           onclick="event.stopPropagation()">
                            Read Newsletter →
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <h2>No Archived Newsletters</h2>
            <p>The archive is currently empty</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>