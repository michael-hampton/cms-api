<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --bg-light: #f9fafb;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: center;
        }

        .benefits-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1.5rem;
            padding: 3rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .benefits-header {
            margin-bottom: 2rem;
        }

        .benefits-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }

        .benefits-header p {
            color: var(--text-secondary);
            font-size: 1.125rem;
            line-height: 1.6;
        }

        .benefits-grid {
            display: grid;
            gap: 1.5rem;
        }

        .benefit-item {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            padding: 1.25rem;
            background: var(--bg-light);
            border-radius: 1rem;
            transition: all 0.3s ease;
        }

        .benefit-item:hover {
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateX(4px);
        }

        .benefit-icon {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .benefit-content h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.375rem;
        }

        .benefit-content p {
            font-size: 0.9375rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .stats-banner {
            display: flex;
            justify-content: space-around;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-top: 2rem;
        }

        .stat-item {
            text-align: center;
            color: white;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .login-container {
            width: 100%;
        }

        .login-card {
            background: white;
            border-radius: 1.5rem;
            padding: 3rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .login-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }

        .login-header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }

        .message {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message::before {
            content: '✓';
            width: 1.5rem;
            height: 1.5rem;
            background: #10b981;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            width: 20px;
            height: 20px;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid var(--border);
            border-radius: 0.75rem;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            background: var(--bg-light);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .error {
            color: var(--danger);
            font-size: 0.8125rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .error::before {
            content: '⚠';
        }

        .btn {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.15);
        }

        .btn:active {
            transform: translateY(0);
        }

        .login-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .login-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .login-links a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .login-links a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .signup-prompt {
            margin-top: 1.5rem;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }

        .signup-prompt a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }

        .signup-prompt a:hover {
            text-decoration: underline;
        }

        @media (max-width: 968px) {
            .page-container {
                grid-template-columns: 1fr;
            }

            .benefits-section {
                order: 2;
            }

            .login-container {
                order: 1;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }

            .login-card,
            .benefits-section {
                padding: 2rem 1.5rem;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }

            .benefits-header h2 {
                font-size: 1.5rem;
            }

            .login-links {
                flex-direction: column;
                align-items: center;
            }

            .stats-banner {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="page-container">
    <!-- Login Form -->
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">👤</div>
                <h1>Welcome Back</h1>
                <p>Sign in to access your account</p>
            </div>

            <?php if ($msg = message()): ?>
                <div class="message"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form id="loginForm">
                @csrf
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input type="email"
                               id="email"
                               name="email"
                               class="form-control"
                               value="<?= old('email', '') ?>"
                               placeholder="you@example.com"
                               required
                               autocomplete="email"
                               autofocus>
                    </div>
                    <?php if ($err = error('email')): ?>
                        <div class="error"><?= htmlspecialchars($err) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control"
                               placeholder="Enter your password"
                               required
                               autocomplete="current-password">
                    </div>
                    <?php if ($err = error('password')): ?>
                        <div class="error"><?= htmlspecialchars($err) ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn" id="loginBtn">Sign In</button>
                <div id="loginError" style="display:none;" class="error"></div>

                <div class="login-footer">
                    <div class="login-links">
                        <a href="/member/forgot-password">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" style="display: inline; vertical-align: middle; margin-right: 4px;">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="16" x2="12" y2="12"/>
                                <line x1="12" y1="8" x2="12.01" y2="8"/>
                            </svg>
                            Forgot Password?
                        </a>
                    </div>

                    <div class="signup-prompt">
                        Don't have an account?
                        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/register">Create one now
                            →</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Benefits Section -->
    <div class="benefits-section">
        <div class="benefits-header">
            <h2>Join The Club</h2>
            <p>Become part of our community and unlock exclusive features</p>
        </div>

        <div class="benefits-grid">
            <div class="benefit-item">
                <div class="benefit-icon">📊</div>
                <div class="benefit-content">
                    <h3>Polls</h3>
                    <p>Voice your opinion on the latest topics and see what the community thinks</p>
                </div>
            </div>

            <div class="benefit-item">
                <div class="benefit-icon">🔮</div>
                <div class="benefit-content">
                    <h3>Predictors</h3>
                    <p>Make predictions and track your accuracy against other members</p>
                </div>
            </div>

            <div class="benefit-item">
                <div class="benefit-icon">⚔️</div>
                <div class="benefit-content">
                    <h3>Challenge a Friend</h3>
                    <p>Send challenges to friends and compete for bragging rights</p>
                </div>
            </div>

            <div class="benefit-item">
                <div class="benefit-icon">🏆</div>
                <div class="benefit-content">
                    <h3>Member Competitions</h3>
                    <p>Exclusive competitions and prizes only available to members</p>
                </div>
            </div>
        </div>

        <div class="stats-banner">
            <div class="stat-item">
                <span class="stat-number">17</span>
                <span class="stat-label">MEMBER FEATURES</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">24/7</span>
                <span class="stat-label">ACCESS AVAILABLE</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">5K+</span>
                <span class="stat-label">ACTIVE MEMBERS</span>
            </div>
        </div>
    </div>
</div>

<script>
    const SITE_SLUG = '<?= htmlspecialchars(\App\Framework\Support\SiteContext::slug()) ?>';

    document.getElementById('loginForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const btn = document.getElementById('loginBtn');
        const errorEl = document.getElementById('loginError');
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        btn.disabled = true;
        btn.textContent = 'Signing in…';
        errorEl.style.display = 'none';

        try {
            const res = await fetch(`/api/${SITE_SLUG}/member/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({email, password}),
            });

            const data = await res.json();

            if (data.success && data.token) {
                alert('mike ' + SITE_SLUG)
                try {
                    localStorage.setItem(`member_api_token:${SITE_SLUG}`, data.token);

                    console.log('stored', localStorage.getItem(`member_api_token:${SITE_SLUG}`));

                    window.location.href = `/${SITE_SLUG}/member/dashboard`;
                } catch (e) {
                    console.error(e);
                }
            } else {
                errorEl.textContent = data.message || 'Invalid credentials. Please try again.';
                errorEl.style.display = 'flex';
                btn.disabled = false;
                btn.textContent = 'Sign In';
            }
        } catch {
            errorEl.textContent = 'An error occurred. Please try again.';
            errorEl.style.display = 'flex';
            btn.disabled = false;
            btn.textContent = 'Sign In';
        }
    });
</script>
</body>
</html>