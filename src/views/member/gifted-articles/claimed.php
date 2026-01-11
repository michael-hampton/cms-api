<?php
/**
 * @var Member $member
 * @var Site $site
 * @var GiftedArticle $gift
 * @var string $message
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Claimed - <?= htmlspecialchars($site->name) ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #4CAF50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease-out;
        }

        .success-icon svg {
            width: 50px;
            height: 50px;
            stroke: white;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            animation: checkmark 0.8s ease-out 0.3s forwards;
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes checkmark {
            to {
                stroke-dashoffset: 0;
            }
        }

        h1 {
            color: #2c3e50;
            font-size: 32px;
            margin: 0 0 15px 0;
            font-weight: 600;
        }

        .message {
            font-size: 18px;
            color: #555;
            margin-bottom: 30px;
        }

        .gift-details {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: left;
        }

        .gift-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .gift-detail-row:last-child {
            border-bottom: none;
        }

        .gift-detail-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
        }

        .gift-detail-value {
            color: #2c3e50;
            font-size: 14px;
            text-align: right;
            max-width: 60%;
        }

        .article-title {
            font-weight: 600;
            color: #4CAF50;
        }

        .gifter-name {
            font-weight: 500;
            color: #667eea;
        }

        .personal-message {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            text-align: left;
        }

        .personal-message-label {
            font-size: 12px;
            text-transform: uppercase;
            color: #856404;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .personal-message-text {
            color: #856404;
            font-style: italic;
            line-height: 1.6;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-top: 25px;
            border-radius: 4px;
            text-align: left;
            font-size: 14px;
            color: #1565c0;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }

            .message {
                font-size: 16px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .gift-detail-row {
                flex-direction: column;
                gap: 5px;
            }

            .gift-detail-value {
                max-width: 100%;
                text-align: left;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="success-icon">
        <svg viewBox="0 0 52 52">
            <polyline points="14 27 22 35 38 19"/>
        </svg>
    </div>

    <h1>🎁 Gift Claimed Successfully!</h1>
    <p class="message"><?= htmlspecialchars($message) ?></p>

    <div class="gift-details">
        <div class="gift-detail-row">
            <span class="gift-detail-label">Article</span>
            <span class="gift-detail-value article-title">
                    <?= htmlspecialchars($gift->page->title ?? 'Article') ?>
                </span>
        </div>
        <div class="gift-detail-row">
            <span class="gift-detail-label">Gifted By</span>
            <span class="gift-detail-value gifter-name">
                    <?= htmlspecialchars($gift->giftedBy->name ?? $gift->giftedBy->email ?? 'A friend') ?>
                </span>
        </div>
        <div class="gift-detail-row">
            <span class="gift-detail-label">Gifted On</span>
            <span class="gift-detail-value">
                    <?= $gift->gifted_at ? $gift->gifted_at->format('F j, Y') : 'Recently' ?>
                </span>
        </div>
        <div class="gift-detail-row">
            <span class="gift-detail-label">Claimed On</span>
            <span class="gift-detail-value">
                    <?= $gift->claimed_at ? $gift->claimed_at->format('F j, Y \a\t g:i A') : 'Just now' ?>
                </span>
        </div>
    </div>

    <?php if (!empty($gift->personal_message)): ?>
        <div class="personal-message">
            <div class="personal-message-label">Personal Message</div>
            <div class="personal-message-text">
                "<?= nl2br(htmlspecialchars($gift->personal_message)) ?>"
            </div>
        </div>
    <?php endif; ?>

    <div class="btn-group">
        <a href="/<?= htmlspecialchars($site->slug) ?>/<?= htmlspecialchars($gift->page->slug ?? '') ?>"
           class="btn btn-primary">
            Read Article Now
        </a>
        <a href="/member/gifted-articles" class="btn btn-secondary">
            View All My Gifts
        </a>
    </div>

    <div class="info-box">
        <strong>What's Next?</strong>
        You now have full access to this article. You can read it anytime from your account or by clicking the link
        above. All your gifted articles are available in your <a href="/member/gifted-articles"
                                                                 style="color: #1565c0; font-weight: 600;">Gifted
            Articles</a> section.
    </div>
</div>
</body>
</html>