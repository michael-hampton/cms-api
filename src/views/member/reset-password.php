<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
<?php
$resetSuccess = false;
$error = '';

// Get token from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = $_POST['token'];

    // Validate passwords match
    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    }
    // Validate password length
    else if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    }
    // Validate password requirements
    else if (!preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain uppercase, lowercase, and number';
    }
    else {
        // Here you would typically:
        // 1. Verify token is valid and not expired
        // 2. Hash the new password
        // 3. Update password in database
        // 4. Invalidate the reset token

        $resetSuccess = true;
    }
}
?>

<div class="container">
    <?php if (!$resetSuccess): ?>
        <div class="icon-wrapper">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>

        <h1>Reset Password</h1>
        <p class="subtitle">Enter your new password below.</p>

        <form method="POST" action="" id="resetForm">
            @csrf
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-group">
                <label for="password">New Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter new password"
                    required
                    minlength="8"
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    placeholder="Confirm new password"
                    required
                    minlength="8"
                >
                <div class="error-message" id="matchError">Passwords do not match</div>
            </div>

            <?php if ($error): ?>
                <div class="error-message show" style="margin-bottom: 15px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="password-requirements">
                <p>Password must contain:</p>
                <ul id="requirements">
                    <li id="length">• At least 8 characters</li>
                    <li id="uppercase">• One uppercase letter</li>
                    <li id="lowercase">• One lowercase letter</li>
                    <li id="number">• One number</li>
                </ul>
            </div>

            <button type="submit" class="btn" id="submitBtn">Reset Password</button>
        </form>
    <?php else: ?>
        <div class="success-message">
            <div class="success-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            <h1>Password Reset!</h1>
            <p class="success-text">Your password has been successfully reset.</p>

            <button onclick="window.location.href='login.php'" class="btn">
                Back to Login
            </button>
        </div>
    <?php endif; ?>
</div>

<script>
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const matchError = document.getElementById('matchError');
    const submitBtn = document.getElementById('submitBtn');

    // Password validation
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;

            // Check length
            const lengthValid = password.length >= 8;
            document.getElementById('length').classList.toggle('valid', lengthValid);

            // Check uppercase
            const uppercaseValid = /[A-Z]/.test(password);
            document.getElementById('uppercase').classList.toggle('valid', uppercaseValid);

            // Check lowercase
            const lowercaseValid = /[a-z]/.test(password);
            document.getElementById('lowercase').classList.toggle('valid', lowercaseValid);

            // Check number
            const numberValid = /[0-9]/.test(password);
            document.getElementById('number').classList.toggle('valid', numberValid);

            checkPasswordMatch();
        });
    }

    if (confirmInput) {
        confirmInput.addEventListener('input', checkPasswordMatch);
    }

    function checkPasswordMatch() {
        if (confirmInput.value && passwordInput.value !== confirmInput.value) {
            matchError.classList.add('show');
            submitBtn.disabled = true;
        } else {
            matchError.classList.remove('show');
            submitBtn.disabled = false;
        }
    }
</script>
</body>
</html>