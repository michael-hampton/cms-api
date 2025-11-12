<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Error - Deal Alerts</title>
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
            background: #f44336;
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
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </div>
    <h1>Verification Failed</h1>
    <p><?= htmlspecialchars($message ?? 'We could not verify your email. The link may be invalid or expired.') ?></p>
    <a href="/<?= $siteName ?? '' ?>/deals" class="btn">Back to Deals</a>
</div>
</body>
</html>