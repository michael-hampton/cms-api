<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 */


// At the top of _header.php, add:
use App\Repositories\Members\GiftedArticleRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\MemberInsights\NotificationService;

$notificationService = new NotificationService(
        new RewardsRepository(
                new \App\Repositories\Rewards\RewardDefinitionRepository(new \App\Repositories\Rewards\RewardAuditLogRepository()),
                new \App\Repositories\Rewards\RewardAuditLogRepository()
        ),
        new GiftedArticleRepository()
);

$notifications = $notificationService->getNotifications($member, $site->id);
$notificationCount = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @js('app-core.js')
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --bg-gradient: linear-gradient(135deg, #f5f7fa 0%, #e3e9f0 100%);
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Main Header */
        .main-header {
            background: white;
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border-color);
        }

        .header-content {
            /*max-width: 1400px;*/
            /*margin: 0 auto;*/
            padding: 0 2rem;
        }

        /* Top Bar with Logo and User Info */
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .site-logo {
            font-size: 1.75rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            letter-spacing: -0.5px;
            transition: transform 0.3s ease;
            white-space: nowrap;
        }

        .site-logo:hover {
            transform: scale(1.05);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.625rem 1.25rem;
            background: var(--bg-light);
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: #e5e7eb;
        }

        .user-avatar {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.125rem;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9375rem;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1;
        }

        .btn-logout {
            padding: 0.625rem 1.25rem;
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .btn-logout:hover {
            border-color: var(--danger-color);
            color: var(--danger-color);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            color: var(--text-primary);
        }

        .mobile-menu-toggle svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        /* Navigation Bar - Separate Line */
        .nav-container {
            border-top: 1px solid var(--border-color);
            background: linear-gradient(to bottom, #fafbfc, #ffffff);
        }

        .main-nav {
            padding: 0;
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) transparent;
        }

        .main-nav::-webkit-scrollbar {
            height: 3px;
        }

        .main-nav::-webkit-scrollbar-track {
            background: transparent;
        }

        .main-nav::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 10px;
        }

        .nav-list {
            display: flex;
            list-style: none;
            gap: 0;
            margin: 0;
            padding: 0;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            position: relative;
        }

        .nav-link:hover {
            color: var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
        }

        .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
            font-weight: 600;
        }

        .nav-icon {
            width: 1.125rem;
            height: 1.125rem;
            flex-shrink: 0;
        }

        /* Badge for notifications */
        .nav-badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--danger-color);
            color: white;
            font-size: 0.625rem;
            font-weight: 700;
            padding: 0.125rem 0.375rem;
            border-radius: 10px;
            min-width: 1.125rem;
            text-align: center;
        }

        /* Page Container */
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .header-content {
                padding: 0 1.5rem;
            }

            .page-container {
                padding: 1.5rem;
            }

            .nav-link {
                padding: 1rem 1.25rem;
                font-size: 0.875rem;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 0 1rem;
            }

            .header-top {
                padding: 1rem 0;
            }

            .header-left {
                gap: 1rem;
            }

            .site-logo {
                font-size: 1.5rem;
            }

            .user-details {
                display: none;
            }

            .user-avatar {
                width: 2.25rem;
                height: 2.25rem;
                font-size: 1rem;
            }

            .user-profile {
                padding: 0.5rem;
            }

            .btn-logout span {
                display: none;
            }

            .btn-logout {
                padding: 0.625rem;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .nav-container {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                box-shadow: var(--shadow-lg);
                border-top: 1px solid var(--border-color);
                border-bottom: 1px solid var(--border-color);
            }

            .nav-container.active {
                display: block;
            }

            .main-nav {
                overflow-x: visible;
            }

            .nav-list {
                flex-direction: column;
                padding: 0.5rem 0;
            }

            .nav-link {
                border-bottom: none;
                border-left: 3px solid transparent;
                padding: 0.875rem 1rem;
            }

            .nav-link.active {
                border-left-color: var(--primary-color);
                border-bottom-color: transparent;
            }

            .page-container {
                padding: 1rem;
            }

            .header-actions {
                gap: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .site-logo {
                font-size: 1.25rem;
            }

            .user-avatar {
                width: 2rem;
                height: 2rem;
                font-size: 0.875rem;
            }

            .header-actions {
                gap: 0.5rem;
            }
        }

        .notification-bell {
            position: relative;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            width: 2.75rem;
            height: 2.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-bell:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .notification-bell svg {
            width: 1.25rem;
            height: 1.25rem;
            stroke: var(--text-secondary);
        }

        .notification-bell:hover svg {
            stroke: var(--primary-color);
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--danger-color);
            color: white;
            font-size: 0.625rem;
            font-weight: 700;
            padding: 0.125rem 0.375rem;
            border-radius: 10px;
            min-width: 1.125rem;
            text-align: center;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        .notification-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: white;
            border-radius: 0.75rem;
            box-shadow: var(--shadow-xl);
            width: 380px;
            max-height: 500px;
            overflow-y: auto;
            z-index: 1001;
            display: none;
            border: 1px solid var(--border-color);
        }

        .notification-dropdown.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-header {
            padding: 1rem 1.25rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .notification-clear {
            font-size: 0.8125rem;
            color: var(--primary-color);
            cursor: pointer;
            background: none;
            border: none;
            font-weight: 600;
        }

        .notification-clear:hover {
            text-decoration: underline;
        }

        .notification-list {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .notification-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s ease;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: var(--bg-light);
        }

        .notification-item-header {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .notification-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-item-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .notification-item-message {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .notification-priority {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 0.5rem;
        }

        .notification-priority.high {
            background: #fee2e2;
            color: #991b1b;
        }

        .notification-priority.medium {
            background: #fef3c7;
            color: #92400e;
        }

        .notification-priority.low {
            background: #e5e7eb;
            color: #374151;
        }

        .notification-empty {
            padding: 3rem 1.25rem;
            text-align: center;
            color: var(--text-secondary);
        }

        .notification-empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .notification-dropdown {
                width: calc(100vw - 2rem);
                right: -1rem;
            }
        }
    </style>
</head>
<body>
<header class="main-header">
    <div class="header-content">
        <!-- Top Bar: Logo, User Profile, Logout -->
        <div class="header-top">
            <div class="header-left">
                <a href="/<?= $site->slug ?>" class="site-logo">
                    <?= htmlspecialchars($site->name ?? 'Member Portal') ?>
                </a>
            </div>

            <div class="header-actions">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr($member->first_name ?? $member->email ?? 'M', 0, 1)) ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($member->displayName ?? 'Member') ?></span>
                        <span class="user-role">Member</span>
                    </div>
                </div>

                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <div style="position: relative;">
                    <button class="notification-bell" onclick="toggleNotifications()" aria-label="Notifications">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-badge"><?= $notificationCount ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <span class="notification-title">Notifications</span>
                            <?php if ($notificationCount > 0): ?>
                                <button class="notification-clear" onclick="clearAllNotifications()">Clear all</button>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($notifications)): ?>
                            <div class="notification-empty">
                                <div class="notification-empty-icon">🔔</div>
                                <p>No new notifications</p>
                            </div>
                        <?php else: ?>
                            <ul class="notification-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <a href="<?= htmlspecialchars($notification['url']) ?>"
                                       class="notification-item"
                                       onclick="notificationClicked('<?= htmlspecialchars($notification['type']) ?>')">
                                        <div class="notification-item-header">
                                            <span class="notification-icon"><?= $notification['icon'] ?></span>
                                            <div class="notification-content">
                                                <div class="notification-item-title">
                                                    <?= htmlspecialchars($notification['title']) ?>
                                                </div>
                                                <div class="notification-item-message">
                                                    <?= htmlspecialchars($notification['message']) ?>
                                                </div>
                                                <?php if (isset($notification['priority'])): ?>
                                                    <span class="notification-priority <?= $notification['priority'] ?>">
                                        <?= $notification['priority'] ?>
                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" action="/member/logout" style="display: inline;">
                    <button type="submit" class="btn-logout">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Navigation Menu - Separate Line -->
        <div class="nav-container" id="navContainer">
            <nav class="main-nav">
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/dashboard"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/dashboard') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/orders"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/orders') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            Orders
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/subscriptions"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/subscriptions') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                            Subscriptions
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/addresses"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/addresses') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            Addresses
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/wishlist"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/wishlist') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                            Wishlist
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/reading-history"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/reading-history') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                            Reading
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/activity"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/activity') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                            </svg>
                            Activity
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/comments"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/comments') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            Comments
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/consent"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/consent') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Privacy
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/account-details"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/account-details') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Account
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/subscription-payments"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/subscription-payments') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect>
                                <line x1="8" y1="6" x2="16" y2="6"></line>
                                <line x1="8" y1="10" x2="16" y2="10"></line>
                                <line x1="8" y1="14" x2="12" y2="14"></line>
                            </svg>
                            Payments
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/payment-methods"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/payment-methods') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="2" y1="10" x2="22" y2="10"></line>
                                <line x1="6" y1="15" x2="10" y2="15"></line>
                            </svg>
                            Payment Methods
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/settings"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/settings') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M12 1v6m0 6v6m-9-9h6m6 0h6M3.93 3.93l4.24 4.24m5.66 5.66l4.24 4.24M3.93 20.07l4.24-4.24m5.66-5.66l4.24-4.24"></path>
                            </svg>
                            Settings
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/<?= $site->slug ?>/member/support"
                           class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/support') !== false ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                            Help
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</header>

<script>
    const originalFetch = window.fetch;
    const SITE_SLUG = '<?= \App\Framework\Support\SiteContext::slug() ?>';

    window.fetch = (url, options = {}) => {
        const token = getMemberApiToken();

        const fullUrl = typeof url === 'string' ? url : url.url;

        // only apply to member API
        if (token && fullUrl.includes('/api/' + SITE_SLUG + '/member')) {
            options.headers = {
                ...(options.headers || {}),
                Authorization: `Bearer ${token}`
            };
        }

        return originalFetch(url, options);
    };

    function toggleMobileMenu() {
        const navContainer = document.getElementById('navContainer');
        navContainer.classList.toggle('active');
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function (event) {
        const navContainer = document.getElementById('navContainer');
        const toggle = document.querySelector('.mobile-menu-toggle');

        if (navContainer.classList.contains('active') &&
            !navContainer.contains(event.target) &&
            !toggle.contains(event.target)) {
            navContainer.classList.remove('active');
        }
    });

    // Close mobile menu when window is resized to desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            document.getElementById('navContainer').classList.remove('active');
        }
    });

    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.toggle('active');
    }

    function notificationClicked(type) {
        // Mark as seen
        fetch('/<?= $site->slug ?>/member/notifications/mark-seen', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({type: type})
        });
    }

    function clearAllNotifications() {
        if (confirm('Clear all notifications?')) {
            fetch('/<?= $site->slug ?>/member/notifications/clear-all', {
                method: 'POST'
            }).then(() => {
                window.location.reload();
            });
        }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function (event) {
        const dropdown = document.getElementById('notificationDropdown');
        const bell = event.target.closest('.notification-bell');

        if (!bell && !dropdown.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });

    function getMemberApiToken() {
        return localStorage.getItem(`member_api_token:${SITE_SLUG}`) || '';
    }

    function clearMemberApiToken() {
        localStorage.removeItem(`member_api_token:${SITE_SLUG}`);
    }
</script>