<?php
/**
 * @var \App\Models\Site $site
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Link - <?= htmlspecialchars($site->name) ?></title>
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
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #f5c6cb;
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
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="icon">⚠️</div>
        <h1>Invalid Unsubscribe Link</h1>

        <div class="error-message">
            The unsubscribe link you clicked is invalid or has expired.
        </div>

        <p>
            This could happen if:
        </p>
        <ul style="text-align: left; color: #7f8c8d; margin: 20px auto; max-width: 300px;">
            <li>The link has already been used</li>
            <li>The link has expired</li>
            <li>The link was copied incorrectly</li>
        </ul>

        <p>
            If you're a member, you can manage your subscription preferences by logging in.
        </p>

        <a href="/member/login" class="btn btn-primary">
            Go to Login
        </a>
    </div>
</div>
</body>
</html>