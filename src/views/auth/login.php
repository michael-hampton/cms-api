<?php
// src/views/auth/login.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst(htmlspecialchars($portal)) ?> Login</title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f4f5f7;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-size: 14px;
        }

        .login-card {
            background: #fff;
            border: 1px solid #dde1e7;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
            padding: 36px 40px;
            width: 100%;
            max-width: 400px;
        }

        .login-logo {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #111827;
        }

        .login-sub {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 28px;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 16px;
        }

        label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        input[type="email"],
        input[type="password"] {
            padding: 9px 12px;
            border: 1px solid #dde1e7;
            border-radius: 6px;
            font-size: 14px;
            color: #111827;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .btn-submit {
            width: 100%;
            padding: 10px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 8px;
            transition: background .15s;
        }

        .btn-submit:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-logo">
        <?= $portal === 'crm' ? '&#9635; CRM' : '&#9741; Merchant Hub' ?>
    </div>
    <div class="login-sub">Sign in to continue</div>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn-submit">Sign In</button>
    </form>
</div>
</body>
</html>