<!-- views/member/login.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Member Login</title>
    <style>
        .error { color: red; font-size: 14px; }
        .message { color: green; padding: 10px; background: #d4edda; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 8px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
<div class="container">
    <h1>Member Login</h1>

    <?php if ($msg = message()): ?>
        <div class="message"><?= $msg ?></div>
    <?php endif; ?>

    <form method="POST" action="/member/login">
        @csrf
        <div class="form-group">
            <label>Email</label>
            <input type="email"
                   name="email"
                   class="form-control"
                   value="<?= old('email', '') ?>"
                   required>
            <?php if ($err = error('email')): ?>
                <div class="error"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password"
                   name="password"
                   class="form-control"
                   required>
            <?php if ($err = error('password')): ?>
                <div class="error"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn">Login</button>
    </form>

    <p>
        <a href="/member/forgot-password">Forgot Password?</a> |
        <a href="/member/register">Sign Up</a>
    </p>
</div>
</body>
</html>