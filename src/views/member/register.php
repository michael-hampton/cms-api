<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account</title>
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
            align-items: start;
        }

        .benefits-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1.5rem;
            padding: 3rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 2rem;
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

        .signup-container {
            width: 100%;
        }

        .signup-card {
            background: white;
            border-radius: 1.5rem;
            padding: 3rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .signup-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .signup-icon {
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

        .signup-header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .signup-header p {
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }

        .message {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            margin-top: 0.25rem;
            width: 1.25rem;
            height: 1.25rem;
            cursor: pointer;
        }

        .checkbox-group label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
            cursor: pointer;
        }

        .checkbox-group a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .checkbox-group a:hover {
            text-decoration: underline;
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

        .signup-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .terms-notice {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .terms-notice a {
            color: var(--primary);
            text-decoration: none;
        }

        .terms-notice a:hover {
            text-decoration: underline;
        }

        .signin-prompt {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9375rem;
        }

        .signin-prompt a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }

        .signin-prompt a:hover {
            text-decoration: underline;
        }

        @media (max-width: 968px) {
            .page-container {
                grid-template-columns: 1fr;
            }

            .benefits-section {
                position: static;
                order: 2;
            }

            .signup-container {
                order: 1;
            }
        }

        @media (max-width: 640px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }

            .signup-card,
            .benefits-section {
                padding: 2rem 1.5rem;
            }

            .signup-header h1 {
                font-size: 1.5rem;
            }

            .benefits-header h2 {
                font-size: 1.5rem;
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
    <!-- Sign Up Form -->
    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <div class="signup-icon">✨</div>
                <h1>Create Your Account</h1>
                <p>Join our community and unlock exclusive features</p>
            </div>

            <?php if ($error = error()): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/register">
                @csrf
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <input type="text"
                                   id="first_name"
                                   name="first_name"
                                   class="form-control"
                                   value="<?= old('first_name', '') ?>"
                                   placeholder="John"
                                   required
                                   autofocus>
                        </div>
                        <?php if ($err = error('first_name')): ?>
                            <div class="error"><?= htmlspecialchars($err) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <input type="text"
                                   id="last_name"
                                   name="last_name"
                                   class="form-control"
                                   value="<?= old('last_name', '') ?>"
                                   placeholder="Doe"
                                   required>
                        </div>
                        <?php if ($err = error('last_name')): ?>
                            <div class="error"><?= htmlspecialchars($err) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

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
                               autocomplete="email">
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
                               placeholder="Create a secure password"
                               required
                               autocomplete="new-password">
                    </div>
                    <?php if ($err = error('password')): ?>
                        <div class="error"><?= htmlspecialchars($err) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password"
                               id="password_confirmation"
                               name="password_confirmation"
                               class="form-control"
                               placeholder="Re-enter your password"
                               required
                               autocomplete="new-password">
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox"
                           id="newsletter"
                           name="newsletter_signup"
                           value="1">
                    <label for="newsletter">
                        Please sign up to the Fourfourtwo newsletter
                    </label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox"
                           id="offers"
                           name="marketing_consent"
                           value="1">
                    <label for="offers">
                        Contact me with news and offers from Fourfourtwo and other Future brands
                    </label>
                </div>

                <button type="submit" class="btn">SIGN UP</button>

                <div class="signup-footer">
                    <div class="terms-notice">
                        By submitting your information you agree to the
                        <a href="/terms">Terms & Conditions</a>,
                        <a href="/privacy">Privacy Policy</a>
                        and are aged 16 or over.
                    </div>

                    <div class="signin-prompt">
                        Already have an account?
                        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/login">Sign in →</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Benefits Section -->
    <div class="benefits-section">
        <div class="benefits-header">
            <h2>Join The Club</h2>
            <p>We'll unlock your member features right away and send a confirmation so you can complete your account</p>
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
</body>
</html>