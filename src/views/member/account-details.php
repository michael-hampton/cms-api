<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Details - <?= htmlspecialchars($site->name ?? 'Site') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: var(--primary-dark);
        }

        .page-header {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--text-secondary);
        }

        .message {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-icon {
            font-size: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label .required {
            color: var(--danger-color);
        }

        .form-input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input:disabled {
            background: var(--bg-light);
            color: var(--text-secondary);
            cursor: not-allowed;
        }

        .form-hint {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .info-grid {
            display: grid;
            gap: 1.5rem;
        }

        .info-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            font-size: 0.9375rem;
            color: var(--text-primary);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .badge.success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge.warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.info {
            background: #dbeafe;
            color: #1e40af;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--border-color);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-light);
            color: var(--text-secondary);
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            color: var(--text-primary);
        }

        .role-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .account-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-box {
            background: var(--bg-light);
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header-content {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .info-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<div class="container">
    <div class="breadcrumb">
        <a href="/<?= $site->slug ?>/member/dashboard">Dashboard</a>
        <span>›</span>
        <span>Account Details</span>
    </div>

    <?php if ($msg = message()): ?>
        <div class="message success">
            <span>✓</span>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($error = message()): ?>
        <div class="message error">
            <span>✕</span>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1>Account Details</h1>
        <p>View and manage your personal information and account status</p>
    </div>

    <!-- Personal Information Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <span class="card-icon">👤</span>
                Personal Information
            </h2>
        </div>

        <form method="POST" action="/<?= $site->slug ?>/member/account-details">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="first_name">
                        First Name <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        class="form-input"
                        value="<?= htmlspecialchars($member->first_name ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="last_name">
                        Last Name <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        class="form-input"
                        value="<?= htmlspecialchars($member->last_name ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="display_name">
                        Display Name
                    </label>
                    <input
                        type="text"
                        id="display_name"
                        name="display_name"
                        class="form-input"
                        value="<?= htmlspecialchars($member->display_name ?? '') ?>"
                        placeholder="<?= htmlspecialchars($member->fullName) ?>"
                    >
                    <span class="form-hint">This name will be displayed publicly on your comments and profile</span>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="email">
                        Email Address <span class="required">*</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        value="<?= htmlspecialchars($member->email) ?>"
                        required
                    >
                    <span class="form-hint">Changing your email will require re-verification</span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    💾 Save Changes
                </button>
                <a href="/<?= $site->slug ?>/member/dashboard" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Account Status Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <span class="card-icon">🔐</span>
                Account Status
            </h2>
        </div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Account Status</div>
                <div class="info-value">
                    <?php if ($member->isActive()): ?>
                        <span class="badge success">✓ Active</span>
                    <?php else: ?>
                        <span class="badge warning">⚠ Inactive</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Email Status</div>
                <div class="info-value">
                    <?php if ($member->isEmailVerified()): ?>
                        <span class="badge success">✓ Verified</span>
                    <?php else: ?>
                        <span class="badge warning">⚠ Not Verified</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Member Since</div>
                <div class="info-value">
                    <?= $member->created_at ? $member->created_at->format('F j, Y') : 'N/A' ?>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Last Login</div>
                <div class="info-value">
                    <?= $member->last_login_at ? $member->last_login_at->format('F j, Y g:i A') : 'Never' ?>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Member ID</div>
                <div class="info-value">
                    <code style="background: var(--bg-light); padding: 0.25rem 0.5rem; border-radius: 0.25rem;">
                        #<?= $member->id ?>
                    </code>
                </div>
            </div>

            <?php if (!$member->roles->isEmpty()): ?>
                <div class="info-row">
                    <div class="info-label">Roles</div>
                    <div class="info-value">
                        <div class="role-list">
                            <?php foreach ($member->roles as $role): ?>
                                <span class="badge info"><?= htmlspecialchars($role->name) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <span class="card-icon">⚡</span>
                Quick Actions
            </h2>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <a href="/<?= $site->slug ?>/member/settings" class="btn btn-secondary">
                🔑 Change Password
            </a>

            <?php if (!$member->isEmailVerified()): ?>
                <a href="/<?= $site->slug ?>/member/resend-verification" class="btn btn-secondary">
                    📧 Resend Verification Email
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add this section to the settings page -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <span class="card-icon">🔒</span>
                Privacy Settings
            </h2>
        </div>

        <form method="POST" action="/member/settings/privacy">
            @csrf
            <div class="form-group">
                <div class="checkbox-group">
                    <input
                            type="checkbox"
                            id="show_activity"
                            name="show_activity"
                            value="1"
                            <?= $member->show_activity ? 'checked' : '' ?>
                    >
                    <label for="show_activity">
                        Show my activity publicly
                    </label>
                </div>
                <span class="form-hint">Allow others to see your reading activity and engagement</span>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input
                            type="checkbox"
                            id="show_badges"
                            name="show_badges"
                            value="1"
                            <?= $member->show_badges ? 'checked' : '' ?>
                    >
                    <label for="show_badges">
                        Show my badges publicly
                    </label>
                </div>
                <span class="form-hint">Display your earned badges on your profile</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    💾 Save Privacy Settings
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>