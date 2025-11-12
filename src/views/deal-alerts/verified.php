<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verified - Deal Alerts</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f3f3f3;
        }
        .container {
            max-width: 500px;
            background: white;
            padding: 3rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.5rem;
            background: #4caf50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg {
            color: white;
        }
        h1 {
            color: #0f1111;
            margin: 0 0 1rem;
        }
        p {
            color: #565959;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.75rem 2rem;
            background: #ff9900;
            color: #0f1111;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #ff8f00;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
    </div>
    <h1>Email Verified!</h1>
    <p><?= htmlspecialchars($message ?? 'Your email has been verified successfully. You will now receive deal alerts.') ?></p>
    <a href="/<?= $siteName ?? '' ?>/deals" class="btn">Browse Deals</a>
</div>
</body>
</html>