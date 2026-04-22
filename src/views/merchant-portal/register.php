<?php
// src/views/merchant-portal/register.php
$old = $old ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Hub — Register</title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f4f5f7;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-size: 14px;
            padding: 24px 16px;
        }

        .card {
            background: #fff;
            border: 1px solid #dde1e7;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
            padding: 36px 40px;
            width: 100%;
            max-width: 460px;
        }

        .logo {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #111827
        }

        .sub {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 28px
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

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #166534;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 16px
        }

        label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        input[type="text"], input[type="email"],
        input[type="password"], input[type="tel"] {
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px
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
            background: #1d4ed8
        }

        .login-link {
            text-align: center;
            font-size: 13px;
            color: #6b7280;
            margin-top: 20px;
        }

        .login-link a {
            color: #2563eb;
            text-decoration: none
        }

        .login-link a:hover {
            text-decoration: underline
        }

        .hint {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 3px
        }

        @media (max-width: 480px) {
            .card {
                padding: 24px 20px
            }

            .form-row {
                grid-template-columns: 1fr
            }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">&#9741; Merchant Hub</div>
    <div class="sub">Create your merchant account</div>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>

    <?php if (!empty($success)): ?>
        <div class="alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif ?>

    <form method="POST" action="/merchant/register">

        <div class="form-row">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                       autocomplete="name" required>
            </div>
            <div class="form-group">
                <label for="company_name">Company Name</label>
                <input type="text" id="company_name" name="company_name"
                       value="<?= htmlspecialchars($old['company_name'] ?? '') ?>"
                       autocomplete="organization">
            </div>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                   autocomplete="email" required>
            <span class="hint">We'll check if you're an existing contact.</span>
        </div>

        <div class="form-group">
            <label for="phone">Phone (optional)</label>
            <input type="tel" id="phone" name="phone"
                   value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                   autocomplete="tel">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="new-password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       autocomplete="new-password" required>
            </div>
        </div>

        <button type="submit" class="btn-submit">Create Account</button>
    </form>

    <p class="login-link">
        Already have an account? <a href="/merchant/login">Sign in</a>
    </p>
</div>
</body>
</html>