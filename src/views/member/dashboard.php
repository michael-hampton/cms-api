<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - <?= htmlspecialchars($site->name ?? 'Site') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        .header {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0 20px;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-name {
            font-weight: 500;
            color: #333;
        }

        .btn-logout {
            padding: 8px 20px;
            background: #f0f0f0;
            color: #666;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .btn-logout:hover {
            background: #e0e0e0;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome-section {
            background: white;
            border-radius: 10px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .welcome-section h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
        }

        .welcome-section p {
            color: #666;
            font-size: 16px;
        }

        .message {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 15px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .dashboard-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .dashboard-card h3 {
            font-size: 20px;
            color: #333;
            margin-bottom: 10px;
        }

        .dashboard-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .card-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }

        .card-link:hover {
            text-decoration: underline;
        }

        .member-info {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .member-info h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            flex: 0 0 150px;
            color: #666;
            font-weight: 500;
        }

        .info-value {
            color: #333;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
        }

        .badge.basic {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge.premium {
            background: #fff3e0;
            color: #f57c00;
        }

        .badge.vip {
            background: #f3e5f5;
            color: #7b1fa2;
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-content">
        <div class="logo"><?= htmlspecialchars($site->name ?? 'Site') ?></div>
        <div class="user-menu">
            <div class="user-info">
                <div class="avatar">
                    <?= strtoupper(substr($member->firstName ?? 'M', 0, 1)) ?>
                </div>
                <span class="user-name"><?= htmlspecialchars($member->getDisplayName()) ?></span>
            </div>
            <form method="POST" action="/member/logout" style="display: inline;">
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </div>
</header>

<div class="container">
    <?php if ($msg = message()): ?>
        <div class="message success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="welcome-section">
        <h1>Welcome back, <?= htmlspecialchars($member->firstName ?? 'Member') ?>!</h1>
        <p>Good to see you again. Here's what's happening with your account.</p>
    </div>

    <div class="member-info">
        <h2>Your Account Information</h2>
        <div class="info-row">
            <div class="info-label">Full Name:</div>
            <div class="info-value"><?= htmlspecialchars($member->getFullName()) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div class="info-value"><?= htmlspecialchars($member->email) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Member Since:</div>
            <div class="info-value">
                <?php
                // This would come from member creation date if available
                echo date('F j, Y');
                ?>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Membership Roles:</div>
            <div class="info-value">
                <?php if (!empty($member->roles)): ?>
                    <?php foreach ($member->roles as $role): ?>
                        <span class="badge <?= strtolower($role) ?>"><?= htmlspecialchars(ucfirst($role)) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="badge basic">Basic Member</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-icon">📚</div>
            <h3>Browse Content</h3>
            <p>Explore exclusive member-only articles, resources, and content created just for you.</p>
            <a href="/" class="card-link">View Content →</a>
        </div>

        <div class="dashboard-card">
            <div class="card-icon">⚙️</div>
            <h3>Account Settings</h3>
            <p>Update your profile information, change your password, and manage your preferences.</p>
            <a href="/member/settings" class="card-link">Manage Settings →</a>
        </div>

        <div class="dashboard-card">
            <div class="card-icon">🔔</div>
            <h3>Notifications</h3>
            <p>Stay updated with the latest news, announcements, and personalized recommendations.</p>
            <a href="/member/notifications" class="card-link">View Notifications →</a>
        </div>
    </div>
</div>
</body>
</html>