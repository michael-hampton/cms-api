<?php
/**
 * @var Site $site
 * @var string $message
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Claim Error - <?= htmlspecialchars($site->name) ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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

        .error-icon {
            width: 80px;
            height: 80px;
            background: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: shake 0.5s ease-out;
        }

        .error-icon svg {
            width: 50px;
            height: 50px;
            stroke: white;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }

        @keyframes shake {
            0%, 100% {
                transform: translateX(0);
            }
            10%, 30%, 50%, 70%, 90% {
                transform: translateX(-10px);
            }
            20%, 40%, 60%, 80% {
                transform: translateX(10px);
            }
        }

        h1 {
            color: #2c3e50;
            font-size: 32px;
            margin: 0 0 15px 0;
            font-weight: 600;
        }

        .error-message {
            font-size: 18px;
            color: #555;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            border-radius: 4px;
            color: #721c24;
        }

        .common-issues {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: left;
        }

        .common-issues h2 {
            font-size: 18px;
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .issue-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .issue-list li {
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .issue-list li:last-child {
            border-bottom: none;
        }

        .issue-icon {
            color: #ffc107;
            font-size: 20px;
            flex-shrink: 0;
        }

        .issue-text {
            color: #555;
            font-size: 14px;
            line-height: 1.5;
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

        .btn-danger {
            background: white;
            color: #dc3545;
            border: 2px solid #dc3545;
        }

        .btn-danger:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
        }

        .help-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin-top: 25px;
            border-radius: 4px;
            text-align: left;
            font-size: 14px;
            color: #1565c0;
        }

        .help-box strong {
            display: block;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .help-box a {
            color: #1565c0;
            font-weight: 600;
            text-decoration: underline;
        }

        .help-box a:hover {
            color: #0d47a1;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }

            .error-message {
                font-size: 16px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="error-icon">
        <svg viewBox="0 0 52 52">
            <line x1="16" y1="16" x2="36" y2="36"/>
            <line x1="36" y1="16" x2="16" y2="36"/>
        </svg>
    </div>

    <h1>Unable to Claim Gift</h1>

    <div class="error-message">
        <?= htmlspecialchars($message) ?>
    </div>

    <div class="common-issues">
        <h2>Common Reasons This Might Happen:</h2>
        <ul class="issue-list">
            <li>
                <span class="issue-icon">⏰</span>
                <span class="issue-text">
                        <strong>Gift Link Expired:</strong> The gift may have exceeded its validity period. Gift links typically expire after a certain time to maintain security.
                    </span>
            </li>
            <li>
                <span class="issue-icon">✉️</span>
                <span class="issue-text">
                        <strong>Wrong Email Address:</strong> You may be logged in with a different email address than the one the gift was sent to. Try logging in with the email address where you received the gift.
                    </span>
            </li>
            <li>
                <span class="issue-icon">👥</span>
                <span class="issue-text">
                        <strong>Already Claimed:</strong> This gift may have already been claimed by another user. Each gift can only be claimed once.
                    </span>
            </li>
            <li>
                <span class="issue-icon">🔗</span>
                <span class="issue-text">
                        <strong>Invalid Link:</strong> The gift link may be incomplete or incorrect. Make sure you're using the full link that was shared with you.
                    </span>
            </li>
        </ul>
    </div>

    <div class="btn-group">
        <a href="/member/login" class="btn btn-primary">
            Try Different Account
        </a>
        <a href="/<?= htmlspecialchars($site->slug) ?>" class="btn btn-secondary">
            Browse Articles
        </a>
        <a href="javascript:history.back()" class="btn btn-danger">
            Go Back
        </a>
    </div>

    <div class="help-box">
        <strong>Need Help?</strong>
        If you believe you should have access to this gift, please contact the person who sent it to you. They may be
        able to resend the gift or verify the details.
        <br><br>
        You can also check your <a href="/member/gifted-articles">Gifted Articles</a> page to see if you have any other
        unclaimed gifts.
    </div>
</div>
</body>
</html>