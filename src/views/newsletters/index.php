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
    <title>Newsletters - <?= htmlspecialchars($site->name) ?></title>
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
            max-width: 1200px;
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

        .newsletters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 32px;
            margin-bottom: 60px;
        }

        .newsletter-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .newsletter-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        }

        .newsletter-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }

        .newsletter-content {
            padding: 24px;
        }

        .newsletter-date {
            font-size: 13px;
            color: #667eea;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .newsletter-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .newsletter-excerpt {
            font-size: 15px;
            color: #718096;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .newsletter-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }

        .newsletter-status {
            font-size: 13px;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }

        .status-published {
            background: #d4edda;
            color: #155724;
        }

        .status-draft {
            background: #fff3cd;
            color: #856404;
        }

        .read-more {
            color: #667eea;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .read-more:hover {
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

            .newsletters-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📰 Our Newsletters</h1>
        <p>Stay updated with our latest news and insights</p>
    </div>

    <?php if ($newsletters->count() > 0): ?>
        <div class="newsletters-grid">
            <?php foreach ($newsletters as $newsletter): ?>
                <div class="newsletter-card"
                     onclick="window.location.href='/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/<?= $newsletter->id ?>'">
                    <div class="newsletter-image">
                        📧
                    </div>
                    <div class="newsletter-content">
                        <div class="newsletter-date">
                            <?= $newsletter->created_at?->format('F d, Y') ?>
                        </div>
                        <h2 class="newsletter-title">
                            <?= htmlspecialchars($newsletter->title ?? 'Untitled Newsletter') ?>
                        </h2>
                        <?php if (!empty($newsletter->content)): ?>
                            <p class="newsletter-excerpt">
                                <?= htmlspecialchars(substr($newsletter->content, 0, 120)) ?>...
                            </p>
                        <?php endif; ?>
                        <div class="newsletter-footer">
                            <!--                                <span class="newsletter-status -->
                            <?php //= $newsletter->status === 'published' ? 'status-published' : 'status-draft' ?><!--">-->
                            <!--                                    --><?php //= ucfirst($newsletter->status) ?>
                            <!--                                </span>-->
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/<?= $newsletter->id ?>"
                               class="read-more"
                               onclick="event.stopPropagation()">
                                Read More →
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <h2>No Newsletters Yet</h2>
            <p>Check back soon for our latest updates!</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>