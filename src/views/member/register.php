<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Registration - <?= htmlspecialchars($site->name ?? 'Site') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .message {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .required {
            color: #e74c3c;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control.error {
            border-color: #e74c3c;
        }

        .error-message {
            color: #e74c3c;
            font-size: 13px;
            margin-top: 6px;
            display: block;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
            flex: 1;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .form-footer {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        .form-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
            padding-left: 4px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Create Your Account</h1>
    <p class="subtitle">Join <?= htmlspecialchars($site->name ?? 'our community') ?> today</p>

    <?php if($errors = errors()) {
        foreach ($errors as $error) { ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php }
    }
    ?>

    <?php if ($msg = message()): ?>
        <div class="message success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($generalError = error('general')): ?>
        <div class="message error"><?= htmlspecialchars($generalError) ?></div>
    <?php endif; ?>

    <form method="POST" action="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/register">
        @csrf
        <div class="form-group">
            <label for="first_name">
                First Name <span class="required">*</span>
            </label>
            <input
                    type="text"
                    id="first_name"
                    name="first_name"
                    class="form-control <?= hasError('first_name') ? 'error' : '' ?>"
                    value="<?= htmlspecialchars(old('first_name', '')) ?>"
                    required
                    autofocus
            >
            <?php if ($err = error('first_name')): ?>
                <span class="error-message"><?= htmlspecialchars($err) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="last_name">
                Last Name <span class="required">*</span>
            </label>
            <input
                    type="text"
                    id="last_name"
                    name="last_name"
                    class="form-control <?= hasError('last_name') ? 'error' : '' ?>"
                    value="<?= htmlspecialchars(old('last_name', '')) ?>"
                    required
            >
            <?php if ($err = error('last_name')): ?>
                <span class="error-message"><?= htmlspecialchars($err) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">
                Email Address <span class="required">*</span>
            </label>
            <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control <?= hasError('email') ? 'error' : '' ?>"
                    value="<?= htmlspecialchars(old('email', '')) ?>"
                    required
            >
            <?php if ($err = error('email')): ?>
                <span class="error-message"><?= htmlspecialchars($err) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password">
                Password <span class="required">*</span>
            </label>
            <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control <?= hasError('password') ? 'error' : '' ?>"
                    required
            >
            <div class="password-requirements">
                Must be at least 8 characters long
            </div>
            <?php if ($err = error('password')): ?>
                <span class="error-message"><?= htmlspecialchars($err) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password_confirmation">
                Confirm Password <span class="required">*</span>
            </label>
            <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    class="form-control <?= hasError('password_confirmation') ? 'error' : '' ?>"
                    required
            >
            <?php if ($err = error('password_confirmation')): ?>
                <span class="error-message"><?= htmlspecialchars($err) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <div class="checkbox-group">
                <input
                        type="checkbox"
                        id="terms"
                        name="terms"
                        value="1"
                    <?= old('terms') ? 'checked' : '' ?>
                        required
                >
                <label for="terms">
                    I agree to the <a href="/terms" target="_blank">Terms of Service</a>
                    and <a href="/privacy" target="_blank">Privacy Policy</a>
                    <span class="required">*</span>
                </label>
            </div>
            <?php if ($err = error('terms')): ?>
                <span class="error-message"><?= htmlspecialchars($err) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn">Create Account</button>
    </form>

    <div class="form-footer">
        Already have an account? <a href="/member/login">Sign in here</a>
    </div>
</div>
</body>
</html>