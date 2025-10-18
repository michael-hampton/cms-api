<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }

        .icon-wrapper {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }

        .icon-wrapper svg {
            width: 35px;
            height: 35px;
            color: white;
        }

        h1 {
            font-size: 28px;
            color: #1a202c;
            text-align: center;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .subtitle {
            color: #718096;
            text-align: center;
            margin-bottom: 35px;
            font-size: 15px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }

        input[type="password"]:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .password-requirements {
            background: #f7fafc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 25px;
        }

        .password-requirements p {
            font-size: 12px;
            color: #4a5568;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .password-requirements ul {
            list-style: none;
        }

        .password-requirements li {
            font-size: 13px;
            color: #718096;
            margin-bottom: 6px;
            padding-left: 4px;
        }

        .password-requirements li.valid {
            color: #48bb78;
        }

        .error-message {
            color: #e53e3e;
            font-size: 13px;
            margin-top: 6px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .success-message {
            text-align: center;
        }

        .success-icon {
            width: 70px;
            height: 70px;
            background: #48bb78;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }

        .success-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }

        .success-text {
            color: #718096;
            margin-bottom: 30px;
            font-size: 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="icon-wrapper">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
    </div>

    <h1>Change Password</h1>
    <p class="subtitle">Update your account password</p>

    <?php if (isset($_SESSION['errors'])): ?>
        <div class="error-message show">
            <?php foreach ($_SESSION['errors'] as $error): ?>
                <?php echo is_array($error) ? htmlspecialchars($error[0]) : htmlspecialchars($error); ?>
            <?php endforeach; ?>
            <?php unset($_SESSION['errors']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/member/change-password" id="changePasswordForm">
        @csrf
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input
                type="password"
                id="current_password"
                name="current_password"
                placeholder="Enter current password"
                required
            >
        </div>

        <div class="form-group">
            <label for="new_password">New Password</label>
            <input
                type="password"
                id="new_password"
                name="new_password"
                placeholder="Enter new password"
                required
                minlength="8"
            >
        </div>

        <div class="form-group">
            <label for="new_password_confirmation">Confirm New Password</label>
            <input
                type="password"
                id="new_password_confirmation"
                name="new_password_confirmation"
                placeholder="Confirm new password"
                required
                minlength="8"
            >
            <div class="error-message" id="matchError">Passwords do not match</div>
        </div>

        <div class="password-requirements">
            <p>Password must contain:</p>
            <ul id="requirements">
                <li id="length">• At least 8 characters</li>
                <li id="uppercase">• One uppercase letter</li>
                <li id="lowercase">• One lowercase letter</li>
                <li id="number">• One number</li>
            </ul>
        </div>

        <button type="submit" class="btn" id="submitBtn">Change Password</button>
    </form>

    <div class="back-link">
        <a href="/member/dashboard">Back to Dashboard</a>
    </div>
</div>

<script>
    // Same validation script as reset-password.php
</script>
</body>
</html>