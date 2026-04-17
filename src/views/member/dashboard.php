<?php ?>
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
            --shadow: 0 1px 3px rgba(0, 0, 0, .1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, .1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ── Loader ── */
        .page-loader {
            text-align: center;
            padding: 4rem;
            color: var(--text-secondary);
        }

        /* ── Verification banner ── */
        .verification-banner {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid var(--warning-color);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .verification-banner h2 {
            color: #92400e;
            font-size: 1.5rem;
            margin-bottom: .5rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .verification-banner p {
            color: #78350f;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .verification-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .btn-resend {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: white;
            padding: .875rem 1.5rem;
            border: none;
            border-radius: .5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .3s;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .btn-resend:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(245, 158, 11, .3);
        }

        .btn-resend:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
        }

        /* ── Welcome ── */
        .welcome-section {
            background: white;
            border-radius: 1rem;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .welcome-section h1 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: .5rem;
        }

        .welcome-section p {
            color: var(--text-secondary);
        }

        /* ── Flash messages ── */
        .message {
            padding: 1rem 1.25rem;
            border-radius: .5rem;
            margin-bottom: 2rem;
            font-size: .9375rem;
            display: flex;
            align-items: center;
            gap: .75rem;
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

        /* ── Section title ── */
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .section-title::before {
            content: '';
            width: 4px;
            height: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        /* ── Dashboard nav cards ── */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        .dashboard-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: var(--shadow);
            transition: all .3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 2px solid transparent;
            position: relative;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .dashboard-card.disabled {
            opacity: .5;
            cursor: not-allowed;
            pointer-events: none;
        }
        .dashboard-card.disabled::after {
            content: '🔒 Verify Email to Unlock';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, .8);
            color: white;
            padding: .5rem 1rem;
            border-radius: .5rem;
            font-size: .875rem;
            font-weight: 600;
            white-space: nowrap;
            opacity: 0;
            transition: opacity .3s;
        }

        .dashboard-card.disabled:hover::after {
            opacity: 1;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
        }

        .card-icon {
            width: 3rem;
            height: 3rem;
            border-radius: .75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .card-icon.orders {
            background: linear-gradient(135deg, #667eea20, #764ba220);
        }

        .card-icon.newsletters {
            background: linear-gradient(135deg, #10b98120, #059f6920);
        }

        .card-icon.subscriptions {
            background: linear-gradient(135deg, #f59e0b20, #d9770620);
        }

        .card-icon.addresses {
            background: linear-gradient(135deg, #3b82f620, #2563eb20);
        }

        .card-icon.comments {
            background: linear-gradient(135deg, #8b5cf620, #7c3aed20);
        }

        .card-icon.settings {
            background: linear-gradient(135deg, #6b728020, #4b556320);
        }

        .card-content h3 {
            font-size: .875rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .5rem;
        }

        .card-content p {
            font-size: .875rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-top: .5rem;
        }

        .card-arrow {
            color: var(--primary-color);
            font-size: 1.25rem;
            transition: transform .2s;
        }

        .dashboard-card:hover .card-arrow {
            transform: translateX(4px);
        }

        /* ── Stats ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: .5rem;
        }

        .stat-label {
            font-size: .875rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        /* ── Limited access ── */
        .limited-access-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .limited-access-section h2 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .limited-access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .info-card {
            background: var(--bg-light);
            border-radius: .75rem;
            padding: 1.5rem;
            border: 2px solid var(--border-color);
        }

        .info-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: .5rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .info-card p {
            font-size: .875rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* ── Products ── */
        .products-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .product-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: .75rem;
            overflow: hidden;
            transition: all .3s;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .product-image-container {
            position: relative;
            width: 100%;
            padding-top: 75%;
            background: #f8f9fa;
            overflow: hidden;
        }

        .product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-badge {
            position: absolute;
            top: .75rem;
            right: .75rem;
            background: var(--danger-color);
            color: white;
            padding: .25rem .75rem;
            border-radius: .5rem;
            font-size: .75rem;
            font-weight: 700;
        }

        .product-content {
            padding: 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: .5rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-description {
            font-size: .875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            line-height: 1.5;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .price-current {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .price-original {
            font-size: .875rem;
            color: #9ca3af;
            text-decoration: line-through;
        }

        .product-cta {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: .75rem 1.5rem;
            border-radius: .5rem;
            font-weight: 600;
            font-size: .875rem;
            border: none;
            cursor: pointer;
            transition: all .3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .product-cta:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, .4);
        }

        /* ── Subscriptions ── */
        .subscriptions-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .subscriptions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .subscriptions-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .subscription-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .tab-button {
            padding: .75rem 1.5rem;
            background: none;
            border: none;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-secondary);
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all .3s;
        }

        .tab-button:hover {
            color: var(--primary-color);
        }

        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .subscription-grid {
            display: grid;
            gap: 1.5rem;
        }

        .subscription-card {
            background: #f9fafb;
            border: 2px solid var(--border-color);
            border-radius: .75rem;
            padding: 1.5rem;
            transition: all .3s;
        }

        .subscription-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 6px rgba(102, 126, 234, .1);
        }

        .subscription-card.expired {
            opacity: .7;
        }

        .subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .subscription-title {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .subscription-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: .5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .icon-print {
            background: linear-gradient(135deg, #f59e0b20, #d9770620);
        }

        .icon-digital {
            background: linear-gradient(135deg, #3b82f620, #2563eb20);
        }

        .subscription-name h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: .25rem;
        }

        .subscription-type {
            font-size: .75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .subscription-status {
            padding: .375rem .75rem;
            border-radius: .5rem;
            font-size: .875rem;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-cancelled {
            background: #fef3c7;
            color: #92400e;
        }

        .subscription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: .5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: .25rem;
        }

        .detail-label {
            font-size: .75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .05em;
            font-weight: 600;
        }

        .detail-value {
            font-size: .875rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .newsletter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .newsletter-tag {
            padding: .375rem .75rem;
            background: #e0e7ff;
            color: #3730a3;
            border-radius: .375rem;
            font-size: .8125rem;
            font-weight: 500;
        }

        .subscription-actions {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .auto-renew-badge {
            display: inline-flex;
            align-items: center;
            gap: .375rem;
            padding: .25rem .625rem;
            background: #d1fae5;
            color: #065f46;
            border-radius: .375rem;
            font-size: .75rem;
            font-weight: 600;
            margin-top: .5rem;
        }

        /* ── Newsletters ── */
        .newsletter-prefs-section {
            background: white;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .newsletter-count {
            background: #e2e8f0;
            color: #4a5568;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
        }

        .newsletter-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .newsletter-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all .3s;
        }

        .newsletter-item:hover {
            background: #f1f3f5;
        }

        .newsletter-info {
            flex: 1;
        }

        .newsletter-name {
            font-size: 16px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 4px;
        }

        .newsletter-meta {
            font-size: 14px;
            color: #718096;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .newsletter-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .newsletter-status.active {
            background: #d1fae5;
            color: #065f46;
        }

        .newsletter-status.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .newsletter-status.locked {
            background: #fef3c7;
            color: #92400e;
        }

        .newsletter-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-toggle {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: all .3s;
        }

        .btn-toggle.subscribe {
            background: var(--primary-color);
            color: white;
        }

        .btn-toggle.subscribe:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-toggle.unsubscribe {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-toggle.unsubscribe:hover:not(:disabled) {
            background: #cbd5e0;
        }

        .btn-toggle:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .locked-message {
            font-size: 13px;
            color: #92400e;
            font-style: italic;
            max-width: 300px;
        }

        /* ── Gifted Articles ── */
        .gifted-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .gifted-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .gifted-tab {
            padding: 1rem;
            background: none;
            border: none;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-secondary);
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all .2s;
        }

        .gifted-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .gifted-tab-content {
            display: none;
        }

        .gifted-tab-content.active {
            display: block;
        }

        .gift-card {
            padding: 1.25rem;
            background: var(--bg-light);
            border-radius: .75rem;
            margin-bottom: 1rem;
            transition: all .2s;
        }

        .gift-card:hover {
            background: #e5e7eb;
            transform: translateX(4px);
        }

        .gift-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: .75rem;
        }

        .gift-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: .9375rem;
            flex: 1;
        }

        .gift-status {
            padding: .25rem .75rem;
            border-radius: 9999px;
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .gift-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .gift-status.claimed {
            background: #d1fae5;
            color: #065f46;
        }

        .gift-status.expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .gift-meta {
            display: flex;
            gap: 1rem;
            font-size: .8125rem;
            color: var(--text-secondary);
            margin-bottom: .75rem;
        }

        .gift-message {
            font-size: .875rem;
            color: var(--text-secondary);
            font-style: italic;
            margin-bottom: .75rem;
            padding-left: 1rem;
            border-left: 3px solid var(--border-color);
        }

        .gift-actions {
            display: flex;
            gap: .75rem;
        }

        .gift-btn {
            padding: .5rem 1rem;
            border-radius: .5rem;
            font-size: .875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s;
        }

        .gift-btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .gift-btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .gift-btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .gift-btn-secondary:hover {
            background: var(--primary-color);
            color: white;
        }

        .empty-gifts {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .view-all-link {
            text-align: center;
            margin-top: 1rem;
        }

        .view-all-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .view-all-link a:hover {
            text-decoration: underline;
        }

        /* ── Rewards ── */
        .rewards-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            border: 2px solid var(--border-color);
        }

        .rewards-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, rgba(102, 126, 234, .05), rgba(118, 75, 162, .05));
            border-radius: 50%;
            z-index: 0;
        }

        .rewards-content {
            position: relative;
            z-index: 1;
        }

        .rewards-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .rewards-icon {
            font-size: 2.5rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0)
            }
            50% {
                transform: translateY(-10px)
            }
        }

        .rewards-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .rewards-count {
            background: linear-gradient(135deg, rgba(102, 126, 234, .1), rgba(118, 75, 162, .1));
            padding: .25rem .75rem;
            border-radius: 9999px;
            font-size: .875rem;
            font-weight: 600;
            color: var(--primary-color);
            border: 1px solid rgba(102, 126, 234, .2);
        }

        .rewards-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .reward-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, .05), rgba(118, 75, 162, .05));
            border: 2px solid rgba(102, 126, 234, .15);
            border-radius: .75rem;
            padding: 1.5rem;
            transition: all .3s;
        }

        .reward-card:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, .1), rgba(118, 75, 162, .1));
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, .15);
        }

        .reward-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .reward-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 .25rem;
        }

        .reward-type {
            font-size: .75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .reward-description {
            color: #4b5563;
            margin-bottom: 1rem;
            line-height: 1.5;
            font-size: .9375rem;
        }

        .reward-value-box {
            background: white;
            border: 2px solid rgba(102, 126, 234, .2);
            border-radius: .5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .reward-value-label {
            font-size: .8125rem;
            color: var(--text-secondary);
            margin-bottom: .25rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .reward-value {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .reward-actions {
            display: flex;
            gap: .75rem;
        }

        .btn-claim {
            flex: 1;
            padding: .75rem 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: .5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            font-size: .9375rem;
        }

        .btn-claim:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, .3);
        }

        .btn-view-all {
            padding: .75rem 1.5rem;
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            border-radius: .5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-view-all:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        /* ── Content sections (Recommended + Trending) ── */
        .content-section {
            margin-bottom: 3rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .nav-arrow {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: white;
            color: var(--text-primary);
            font-size: 1.25rem;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .nav-arrow:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        /* ── Recommended pages grid ── */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .content-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all .3s;
            cursor: pointer;
        }

        .content-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .content-image {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: var(--bg-light);
        }

        .content-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .content-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--primary-color);
            color: white;
            padding: .375rem .875rem;
            border-radius: .25rem;
            font-size: .8125rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .content-title {
            padding: 1.25rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.5;
        }

        /* ── Trending conversations (list layout) ── */
        .trending-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .conversation-card {
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            transition: all .3s;
            cursor: pointer;
            display: flex;
            align-items: stretch;
            overflow: hidden;
            border: 2px solid transparent;
        }

        .conversation-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .conversation-rank {
            width: 3rem;
            flex-shrink: 0;
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            font-weight: 800;
            color: white;
        }

        .conversation-body {
            flex: 1;
            padding: 1rem 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: .5rem;
        }

        .conversation-title {
            font-size: .9375rem;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.5;
        }

        .conversation-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .conversation-stat {
            display: flex;
            align-items: center;
            gap: .375rem;
            font-size: .8125rem;
            color: var(--text-secondary);
        }

        .conversation-stat span:first-child {
            font-size: 1rem;
        }

        .trending-score-pill {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, .12), rgba(118, 75, 162, .12));
            color: var(--primary-color);
            padding: .2rem .6rem;
            border-radius: 9999px;
            font-size: .75rem;
            font-weight: 700;
            border: 1px solid rgba(102, 126, 234, .2);
        }

        .conversation-actions {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            padding-right: 1rem;
        }

        /* ── Trending pages (card grid) ── */
        .trending-pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .trending-page-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all .3s;
            cursor: pointer;
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
        }

        .trending-page-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .trending-page-image {
            position: relative;
            width: 100%;
            height: 180px;
            overflow: hidden;
            background: var(--bg-light);
        }

        .trending-page-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .trending-page-image-placeholder {
            width: 100%;
            height: 180px;
            background: linear-gradient(135deg, #667eea15, #764ba215);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }

        .trending-page-body {
            padding: 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }

        .trending-page-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.45;
        }

        .trending-page-stats {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .trending-page-stat {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .8125rem;
            color: var(--text-secondary);
        }

        .trending-page-footer {
            padding: .875rem 1.25rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .trending-fire-badge {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            font-size: .75rem;
            font-weight: 700;
            color: #e85d04;
            background: #fff3e0;
            padding: .25rem .6rem;
            border-radius: 9999px;
        }

        /* ── Shared btn ── */
        .btn {
            padding: .625rem 1.25rem;
            border-radius: .5rem;
            font-weight: 600;
            font-size: .875rem;
            cursor: pointer;
            transition: all .3s;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, .4);
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background: white;
            color: var(--danger-color);
            border: 2px solid var(--danger-color);
        }

        .btn-danger:hover {
            background: var(--danger-color);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: .5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: .5rem;
            color: #4b5563;
        }

        /* ── Toast ── */
        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: .75rem;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: 1rem 1.25rem;
            border-radius: .75rem;
            font-size: .9375rem;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            pointer-events: all;
            animation: slideIn .3s ease;
            max-width: 360px;
        }

        .toast.success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .toast.error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .toast.info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .toast-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: .6;
            font-size: 1.1rem;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%)
            }
            to {
                opacity: 1;
                transform: translateX(0)
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0)
            }
            to {
                opacity: 0;
                transform: translateX(100%)
            }
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-section h1 {
                font-size: 1.5rem;
            }

            .verification-actions {
                flex-direction: column;
            }

            .btn-resend {
                width: 100%;
                justify-content: center;
            }

            .subscription-details {
                grid-template-columns: 1fr;
            }

            .subscription-actions {
                flex-direction: column;
            }

            .newsletter-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .newsletter-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn-toggle {
                width: 100%;
            }

            .subscriptions-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .trending-pages-grid {
                grid-template-columns: 1fr;
            }

            .conversation-rank {
                width: 2.5rem;
            }
        }
    </style>
</head>
<body>
@include('member._header')

<div class="container" id="dashboard-root">
    @include('member/components/dashboard-banner')
    <div id="dashboard-loading" class="page-loader">Loading your dashboard…</div>
    <div id="dashboard-content" style="display:none;"></div>

    <div id="section-recommended"></div>
    <div id="section-trending-conversations"></div>
    <div id="section-trending-pages"></div>
    <div id="section-gifted"></div>
    <div id="section-newsletters"></div>
    <div id="section-products"></div>
    <div id="section-rewards"></div>
    <div id="section-subscriptions"></div>
    @include('member/components/back-to-top')
</div>

<script>
    /* ─────────────────────────────────────────────────────────────
       Component: RecommendedCard
    ───────────────────────────────────────────────────────────── */
    class RecommendedCard {
        constructor(page, siteSlug) {
            this.page = page;
            this.siteSlug = siteSlug;
        }

        render() {
            const p = this.page;
            const img = p.metadata?.featured_image;

            return UI.el('article', {className: 'content-card'}, [
                UI.el('div', {
                    className: 'content-image',
                    onclick: () => window.location.href = `/${this.siteSlug}/${p.slug}`,
                }, [
                    p.categories?.[0] ? UI.el('span', {className: 'content-badge'}, [p.categories[0].name]) : null,
                    img
                        ? UI.el('img', {src: img, alt: p.title, loading: 'lazy'})
                        : UI.el('div', {
                            style: {
                                width: '100%',
                                height: '100%',
                                background: '#e5e7eb',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                color: '#9ca3af'
                            }
                        }, ['📄']),
                ]),
                UI.el('h3', {
                    className: 'content-title',
                    onclick: () => window.location.href = `/${this.siteSlug}/${p.slug}`,
                }, [p.title]),
                UI.el('button', {
                    className: 'btn btn-secondary',
                    style: {margin: '0 1.25rem 1.25rem'},
                    onclick: () => this._giftModal.open(p.slug, p.title),
                }, ['🎁 Gift This Article']),
            ]);
        }

        setGiftModal(modal) {
            this._giftModal = modal;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: TrendingConversationCard
    ───────────────────────────────────────────────────────────── */
    class TrendingConversationCard {
        constructor(page, rank, siteSlug, giftModal) {
            this.page = page;
            this.rank = rank;
            this.siteSlug = siteSlug;
            this.giftModal = giftModal;
        }

        render() {
            const p = this.page;
            const shortTitle = p.title.length > 120 ? p.title.substring(0, 120) + '…' : p.title;

            return UI.el('article', {
                className: 'conversation-card',
                onclick: () => window.location.href = `/${this.siteSlug}/${p.slug}`,
            }, [
                UI.el('div', {className: 'conversation-rank'}, [String(this.rank)]),
                UI.el('div', {className: 'conversation-body'}, [
                    UI.el('div', {className: 'conversation-title'}, [shortTitle]),
                    UI.el('div', {className: 'conversation-meta'}, [
                        UI.el('span', {className: 'conversation-stat'}, ['💬', ` ${(p.comment_count_24h ?? 0).toLocaleString()}`]),
                        UI.el('span', {className: 'conversation-stat'}, ['👍', ` ${(p.like_count_24h ?? 0).toLocaleString()}`]),
                        UI.el('span', {className: 'conversation-stat'}, ['👁', ` ${(p.view_count_24h ?? 0).toLocaleString()}`]),
                        UI.el('span', {className: 'trending-score-pill'}, ['🔥', ` ${p.trending_score ?? 0}`]),
                    ]),
                ]),
                UI.el('div', {
                    className: 'conversation-actions',
                    onclick: e => e.stopPropagation(),
                }, [
                    UI.el('button', {
                        className: 'btn btn-secondary',
                        style: {fontSize: '.8125rem', padding: '.4rem .875rem'},
                        onclick: () => this.giftModal.open(p.slug, p.title),
                    }, ['🎁']),
                ]),
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: TrendingPageCard
    ───────────────────────────────────────────────────────────── */
    class TrendingPageCard {
        constructor(page, siteSlug, giftModal) {
            this.page = page;
            this.siteSlug = siteSlug;
            this.giftModal = giftModal;
        }

        render() {
            const p = this.page;
            const img = p.metadata?.featured_image || p.resolved_images?.featured;

            return UI.el('article', {
                className: 'trending-page-card',
                onclick: () => window.location.href = `/${this.siteSlug}/${p.slug}`,
            }, [
                img
                    ? UI.el('div', {className: 'trending-page-image'}, [
                        UI.el('img', {src: img, alt: p.title, loading: 'lazy'}),
                    ])
                    : UI.el('div', {className: 'trending-page-image-placeholder'}, ['📰']),

                UI.el('div', {className: 'trending-page-body'}, [
                    UI.el('div', {className: 'trending-page-title'}, [p.title]),
                    UI.el('div', {className: 'trending-page-stats'}, [
                        UI.el('span', {className: 'trending-page-stat'}, ['👁', ` ${(p.view_count_24h ?? 0).toLocaleString()} views`]),
                        UI.el('span', {className: 'trending-page-stat'}, ['💬', ` ${(p.comment_count_24h ?? 0).toLocaleString()}`]),
                        UI.el('span', {className: 'trending-page-stat'}, ['👍', ` ${(p.like_count_24h ?? 0).toLocaleString()}`]),
                    ]),
                ]),

                UI.el('div', {className: 'trending-page-footer'}, [
                    UI.el('span', {className: 'trending-fire-badge'}, ['🔥', ` Score ${p.trending_score ?? 0}`]),
                    UI.el('button', {
                        className: 'btn btn-secondary',
                        style: {fontSize: '.8125rem', padding: '.4rem .875rem'},
                        onclick: e => {
                            e.stopPropagation();
                            this.giftModal.open(p.slug, p.title);
                        },
                    }, ['🎁 Gift']),
                ]),
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: GiftCard (received / given)
    ───────────────────────────────────────────────────────────── */
    class GiftCard {
        constructor(gift, direction, siteSlug) {
            this.gift = gift;
            this.direction = direction;
            this.siteSlug = siteSlug;
        }

        render() {
            const g = this.gift;
            const status = (g.status ?? '').toLowerCase();

            const metaLine = this.direction === 'received'
                ? UI.el('div', {className: 'gift-meta'}, [
                    UI.el('span', {}, [
                        '👤 From: ',
                        g.giftedBy?.first_name
                            ? `${g.giftedBy.first_name} ${g.giftedBy.last_name ?? ''}`.trim()
                            : (g.giftedBy?.email ?? ''),
                    ]),
                    UI.el('span', {}, ['📅 ', UI.formatDate(g.gifted_at)]),
                ])
                : UI.el('div', {className: 'gift-meta'}, [
                    UI.el('span', {}, ['📧 To: ', g.recipient_email ?? '']),
                    UI.el('span', {}, ['📅 ', UI.formatDate(g.gifted_at)]),
                ]);

            console.log('g', g)

            const actionBtn = this.direction === 'received'
                ? (status === 'pending'
                    ? UI.el('a', {
                        className: 'gift-btn gift-btn-primary',
                        href: `/${this.siteSlug}/gift/${g.gift_token}`
                    }, ['Claim & Read'])
                    : UI.el('a', {
                        className: 'gift-btn gift-btn-primary',
                        href: `/${this.siteSlug}/${g.page?.slug}`
                    }, ['Read Article']))
                : null;

            return UI.el('div', {className: 'gift-card'}, [
                UI.el('div', {className: 'gift-header'}, [
                    UI.el('div', {className: 'gift-title'}, [g.page?.title ?? '']),
                    UI.el('span', {className: `gift-status ${status}`}, [g.status ?? '']),
                ]),
                metaLine,
                g.personal_message
                    ? UI.el('div', {className: 'gift-message'}, [`"${g.personal_message}"`])
                    : null,
                actionBtn
                    ? UI.el('div', {className: 'gift-actions'}, [actionBtn])
                    : null,
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: GiftModal (singleton IIFE)
    ───────────────────────────────────────────────────────────── */
    const GiftModal = (() => {
        let currentPage = null;
        let modalEl = null;
        let siteSlug = null;

        function init(slug) {
            siteSlug = slug;
        }

        function getOrCreate() {
            if (modalEl) return modalEl;

            const overlay = UI.el('div', {
                id: 'giftModal',
                style: {
                    display: 'none', position: 'fixed', top: '0', left: '0', width: '100%', height: '100%',
                    background: 'rgba(0,0,0,.5)', zIndex: '10000', alignItems: 'center', justifyContent: 'center'
                },
            });
            overlay.addEventListener('click', e => {
                if (e.target === overlay) close();
            });

            const inner = UI.el('div', {
                style: {
                    background: 'white', borderRadius: '12px', maxWidth: '600px', width: '90%',
                    maxHeight: '90vh', overflowY: 'auto', position: 'relative'
                },
            });
            const wrapper = UI.el('div', {style: {padding: '30px'}});
            const closeBtn = UI.el('button', {
                style: {
                    position: 'absolute', top: '15px', right: '15px', background: 'none', border: 'none',
                    fontSize: '28px', cursor: 'pointer', color: '#666'
                },
                onclick: close,
            }, ['×']);
            const heading = UI.el('h2', {style: {marginBottom: '20px', color: '#2c3e50'}}, ['🎁 Gift This Article']);
            const content = UI.el('div', {id: 'giftModalContent'});

            wrapper.appendChild(closeBtn);
            wrapper.appendChild(heading);
            wrapper.appendChild(content);
            inner.appendChild(wrapper);
            overlay.appendChild(inner);
            document.body.appendChild(overlay);
            modalEl = overlay;
            return overlay;
        }

        async function open(slug, title) {
            const overlay = getOrCreate();
            const content = overlay.querySelector('#giftModalContent');
            UI.render(content, [UI.el('div', {style: {textAlign: 'center', padding: '40px'}}, ['Loading…'])]);
            overlay.style.display = 'flex';

            try {
                const data = await api(`/${siteSlug}/member/gift-modal/${slug}`);
                currentPage = data.data.page;
                renderContent(data.data, title, content);
            } catch (err) {
                UI.render(content, [
                    UI.el('div', {style: {color: '#721c24'}}, [UI.esc(err.message ?? 'Failed to load')]),
                    UI.el('button', {style: {marginTop: '1rem', width: '100%'}, onclick: close}, ['Close']),
                ]);
            }
        }

        function renderContent({allowance}, pageTitle, container) {
            let allowanceEl;
            if (!allowance.can_gift) {
                allowanceEl = UI.el('div', {
                        style: {
                            background: '#f8d7da',
                            borderLeft: '4px solid #dc3545',
                            padding: '15px',
                            borderRadius: '4px',
                            marginBottom: '20px',
                            color: '#721c24'
                        }
                    },
                    [`Gift limit reached! You've used all ${allowance.annual_limit} of your annual article gifts.`]);
            } else if (allowance.remaining_gifts <= 2) {
                allowanceEl = UI.el('div', {
                        style: {
                            background: '#fff3cd',
                            borderLeft: '4px solid #ffc107',
                            padding: '15px',
                            borderRadius: '4px',
                            marginBottom: '20px',
                            color: '#856404'
                        }
                    },
                    [`Almost there! You have ${allowance.remaining_gifts} gift${allowance.remaining_gifts !== 1 ? 's' : ''} remaining this year.`]);
            } else {
                allowanceEl = UI.el('div', {
                        style: {
                            background: '#e8f5e9',
                            padding: '15px',
                            borderRadius: '4px',
                            marginBottom: '20px'
                        }
                    },
                    [`You have ${allowance.remaining_gifts} gifts remaining out of ${allowance.annual_limit} this year.`]);
            }

            const articleInfo = UI.el('div', {
                style: {
                    background: '#f8f9fa',
                    padding: '15px',
                    borderRadius: '8px',
                    marginBottom: '20px'
                }
            }, [
                UI.el('div', {style: {fontWeight: '600', color: '#2c3e50', marginBottom: '5px'}}, [pageTitle]),
                UI.el('div', {style: {fontSize: '14px', color: '#666'}}, ['Share this article with someone special']),
            ]);

            const msgEl = UI.el('div', {id: 'giftModalMessages'});
            UI.render(container, [articleInfo, allowanceEl, msgEl]);

            container.appendChild(allowance.can_gift ? buildGiftForm(msgEl) : UI.el('button', {
                style: {
                    width: '100%',
                    padding: '.75rem',
                    background: 'white',
                    border: '2px solid var(--border-color)',
                    borderRadius: '.5rem',
                    fontWeight: '600',
                    cursor: 'pointer'
                },
                onclick: close,
            }, ['Close']));
        }

        function buildGiftForm(msgEl) {
            const emailInput = UI.el('input', {
                type: 'email', id: 'modal_recipient_email', required: 'true',
                style: {
                    width: '100%',
                    padding: '10px',
                    border: '1px solid #ddd',
                    borderRadius: '4px',
                    fontSize: '14px',
                    boxSizing: 'border-box'
                },
                placeholder: 'friend@example.com',
            });
            const charCountEl = UI.el('span', {id: 'modal_charCount'}, ['0']);
            const textarea = UI.el('textarea', {
                id: 'modal_personal_message', maxlength: '500',
                style: {
                    width: '100%',
                    padding: '10px',
                    border: '1px solid #ddd',
                    borderRadius: '4px',
                    fontSize: '14px',
                    boxSizing: 'border-box',
                    resize: 'vertical',
                    minHeight: '100px',
                    fontFamily: 'inherit'
                },
                placeholder: 'Add a personal note…',
            });
            textarea.addEventListener('input', () => UI.text(charCountEl, String(textarea.value.length)));

            const submitBtn = UI.el('button', {
                type: 'submit',
                style: {
                    flex: '1',
                    padding: '.75rem',
                    background: 'var(--primary-color)',
                    color: 'white',
                    border: 'none',
                    borderRadius: '.5rem',
                    fontWeight: '600',
                    cursor: 'pointer'
                },
            }, ['Send Gift']);
            const cancelBtn = UI.el('button', {
                type: 'button',
                style: {
                    flex: '1',
                    padding: '.75rem',
                    background: 'white',
                    border: '2px solid var(--border-color)',
                    borderRadius: '.5rem',
                    fontWeight: '600',
                    cursor: 'pointer'
                },
                onclick: close,
            }, ['Cancel']);

            submitBtn.addEventListener('click', async () => {
                const email = emailInput.value.trim();
                const message = textarea.value.trim();
                submitBtn.disabled = true;
                submitBtn.textContent = 'Sending…';

                try {
                    const data = await api(`/${siteSlug}/gift-article/${currentPage.slug}`, {
                        method: 'POST',
                        body: JSON.stringify({recipient_email: email, personal_message: message}),
                    });
                    UI.render(msgEl, [UI.el('div', {
                            style: {
                                background: '#d4edda',
                                color: '#155724',
                                padding: '15px',
                                borderRadius: '4px',
                                marginBottom: '20px'
                            }
                        },
                        [`✓ ${data.data?.message ?? data.message ?? 'Gift sent!'}`])]);
                    form.style.display = 'none';
                    setTimeout(close, 3000);
                } catch (err) {
                    UI.render(msgEl, [UI.el('div', {
                            style: {
                                background: '#f8d7da',
                                color: '#721c24',
                                padding: '15px',
                                borderRadius: '4px',
                                marginBottom: '20px'
                            }
                        },
                        [`⚠ ${err.message}`])]);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Send Gift';
                }
            });

            const form = UI.el('div', {}, [
                UI.el('div', {style: {marginBottom: '20px'}}, [
                    UI.el('label', {
                        style: {
                            display: 'block',
                            marginBottom: '5px',
                            fontWeight: '500',
                            color: '#555'
                        }
                    }, ["Recipient's Email Address *"]),
                    emailInput,
                ]),
                UI.el('div', {style: {marginBottom: '20px'}}, [
                    UI.el('label', {
                        style: {
                            display: 'block',
                            marginBottom: '5px',
                            fontWeight: '500',
                            color: '#555'
                        }
                    }, ['Personal Message (Optional)']),
                    textarea,
                    UI.el('div', {style: {fontSize: '12px', color: '#666', marginTop: '5px'}}, [charCountEl, '/500']),
                ]),
                UI.el('div', {style: {display: 'flex', gap: '10px'}}, [submitBtn, cancelBtn]),
            ]);

            return form;
        }

        function close() {
            if (modalEl) modalEl.style.display = 'none';
            currentPage = null;
        }

        return {init, open, close};
    })();

    /* ─────────────────────────────────────────────────────────────
       DashboardBuilder — builds every visual section
    ───────────────────────────────────────────────────────────── */
    class DashboardBuilder {
        constructor(siteSlug, giftModal) {
            this.siteSlug = siteSlug;
            this.giftModal = giftModal;
        }

        /* ── Top-level layout ── */

        buildVerifiedDashboard(data) {
            const {member, stats, recent_orders, all_subscriptions} = data;

            const navItems = [
                {
                    href: 'orders',
                    icon: '🛍️',
                    cls: 'orders',
                    title: 'My Orders',
                    desc: 'View and track your order history and current shipments.'
                },
                {
                    href: 'newsletters',
                    icon: '📧',
                    cls: 'newsletters',
                    title: 'Newsletters',
                    desc: 'Manage your newsletter subscriptions and preferences.'
                },
                {
                    href: 'subscriptions',
                    icon: '⭐',
                    cls: 'subscriptions',
                    title: 'Subscriptions',
                    desc: 'View and manage your active subscriptions and membership plans.'
                },
                {
                    href: 'addresses',
                    icon: '📍',
                    cls: 'addresses',
                    title: 'Addresses',
                    desc: 'Manage your shipping and billing addresses.'
                },
                {
                    href: 'comments',
                    icon: '💬',
                    cls: 'comments',
                    title: 'Comments',
                    desc: 'View and manage your comments across the site.'
                },
                {
                    href: 'account-details',
                    icon: '👤',
                    cls: '',
                    title: 'Account Details',
                    desc: 'View and update your personal information and account status.'
                },
                {
                    href: 'settings',
                    icon: '⚙️',
                    cls: 'settings',
                    title: 'Security Settings',
                    desc: 'Update your password and security preferences.'
                },
                {
                    href: 'reading-history',
                    icon: '📚',
                    cls: '',
                    title: 'Reading History',
                    desc: "View pages you've read and track your reading progress."
                },
                {
                    href: 'liked-pages',
                    icon: '❤️',
                    cls: '',
                    title: 'Liked Pages',
                    desc: 'Access your collection of liked pages and content.'
                },
                {
                    href: 'wishlist',
                    icon: '🛍️',
                    cls: 'orders',
                    title: 'My Favourites',
                    desc: 'View your saved favourite items.'
                },
                {
                    href: 'consent',
                    icon: '🔒',
                    cls: 'orders',
                    title: 'Privacy & Consent',
                    desc: 'Control how your personal data is used.'
                },
                {
                    href: 'activity',
                    icon: '🏆',
                    cls: 'orders',
                    title: 'Activity & Achievements',
                    desc: 'Track your engagement and earn badges.'
                },
            ];

            const navCards = navItems.map(c =>
                UI.el('a', {href: `/${this.siteSlug}/member/${c.href}`, className: 'dashboard-card'}, [
                    UI.el('div', {className: 'card-header'}, [
                        UI.el('div', {className: `card-icon ${c.cls}`}, [c.icon]),
                        UI.el('div', {className: 'card-arrow'}, ['→']),
                    ]),
                    UI.el('div', {className: 'card-content'}, [
                        UI.el('h3', {}, [c.title]),
                        UI.el('p', {}, [c.desc]),
                    ]),
                ])
            );

            const statDefs = [
                ['orders', 'Total Orders'], ['newsletters', 'Newsletters'],
                ['subscriptions', 'Active Subscriptions'], ['comments', 'Comments Posted'],
                ['pages_read', 'Pages Read'], ['likes', 'Pages Liked'],
            ];
            const statCards = statDefs.map(([key, label]) =>
                UI.el('div', {className: 'stat-card'}, [
                    UI.el('div', {className: 'stat-number'}, [String(stats[key] ?? 0)]),
                    UI.el('div', {className: 'stat-label'}, [label]),
                ])
            );

            return [
                UI.el('div', {className: 'welcome-section'}, [
                    UI.el('h1', {}, [`Welcome back, ${member.first_name ?? 'Member'}!`]),
                    UI.el('p', {}, ['Manage your account, track your orders, and explore exclusive content.']),
                ]),
                UI.el('h2', {className: 'section-title'}, ['Quick Access']),
                UI.el('div', {className: 'dashboard-grid'}, navCards),
                UI.el('h2', {className: 'section-title'}, ['Recent Activity']),
                UI.el('div', {className: 'dashboard-grid'}, [this.buildActivityTables(recent_orders, all_subscriptions)]),
                UI.el('h2', {className: 'section-title'}, ['Your Activity']),
                UI.el('div', {className: 'stats-grid'}, statCards),
            ];
        }

        buildUnverifiedDashboard({member, stats}) {
            const orderInfoCard = (stats?.orders > 0) ? UI.el('div', {className: 'info-card'}, [
                UI.el('h3', {}, ['🛍️ Your Orders']),
                UI.el('p', {}, [`You have ${stats.orders} order${stats.orders !== 1 ? 's' : ''}. Verify your email to view order details and tracking information.`]),
            ]) : null;

            const subInfoCard = (stats?.subscriptions > 0) ? UI.el('div', {className: 'info-card'}, [
                UI.el('h3', {}, ['⭐ Your Subscriptions']),
                UI.el('p', {}, [`You have ${stats.subscriptions} active subscription${stats.subscriptions !== 1 ? 's' : ''}. Verify your email to manage your subscriptions.`]),
            ]) : null;

            const profileCard = UI.el('div', {className: 'info-card'}, [
                UI.el('h3', {}, ['👤 Profile Information']),
                UI.el('div', {
                    innerHTML:
                        `<p><strong>Name:</strong> ${UI.esc(`${member.first_name ?? ''} ${member.last_name ?? ''}`.trim())}</p>` +
                        `<p><strong>Email:</strong> ${UI.esc(member.email)}</p>` +
                        `<p><strong>Member Since:</strong> ${UI.formatDate(member.created_at)}</p>`
                }),
            ]);

            const disabledItems = [
                {
                    icon: '🛍️',
                    cls: 'orders',
                    title: 'My Orders',
                    desc: 'View and track your order history and current shipments.'
                },
                {
                    icon: '📧',
                    cls: 'newsletters',
                    title: 'Newsletters',
                    desc: 'Manage your newsletter subscriptions and preferences.'
                },
                {
                    icon: '⭐',
                    cls: 'subscriptions',
                    title: 'Subscriptions',
                    desc: 'View and manage your active subscriptions and membership plans.'
                },
                {icon: '💬', cls: 'comments', title: 'Comments', desc: 'View and manage your comments across the site.'},
            ];
            const disabledCards = disabledItems.map(c =>
                UI.el('div', {className: 'dashboard-card disabled'}, [
                    UI.el('div', {className: 'card-header'}, [
                        UI.el('div', {className: `card-icon ${c.cls}`}, [c.icon]),
                        UI.el('div', {className: 'card-arrow'}, ['→']),
                    ]),
                    UI.el('div', {className: 'card-content'}, [
                        UI.el('h3', {}, [c.title]),
                        UI.el('p', {}, [c.desc]),
                    ]),
                ])
            );

            const resendBtn = UI.el('button', {
                className: 'btn-resend',
                id: 'resendBtn'
            }, ['📧 Resend Verification Email']);
            resendBtn.addEventListener('click', () => window.dashboardApp.resendVerification());

            return [
                UI.el('div', {className: 'verification-banner'}, [
                    UI.el('h2', {}, ['⚠️ Email Verification Required']),
                    UI.el('p', {innerHTML: `Welcome! Please verify your email address to unlock your full account. We've sent a verification link to <strong>${UI.esc(member.email)}</strong>.`}),
                    UI.el('div', {className: 'verification-actions'}, [resendBtn]),
                ]),
                UI.el('div', {className: 'limited-access-section'}, [
                    UI.el('h2', {}, ['Your Account Overview']),
                    UI.el('div', {className: 'limited-access-grid'}, [profileCard, orderInfoCard, subInfoCard].filter(Boolean)),
                ]),
                UI.el('h2', {className: 'section-title'}, ['Available After Verification']),
                UI.el('div', {className: 'dashboard-grid'}, disabledCards),
            ];
        }

        /* ── Activity tables ── */

        buildActivityTables(recentOrders, allSubscriptions) {
            if (!recentOrders?.length && !allSubscriptions?.length) return UI.el('div', {}, []);

            const ordersTab = UI.el('button', {
                    id: 'ordersTab',
                    style: {
                        padding: '1rem',
                        background: 'none',
                        border: 'none',
                        fontWeight: '600',
                        cursor: 'pointer',
                        borderBottom: '3px solid var(--primary-color)',
                        marginBottom: '-2px',
                        color: 'var(--text-primary)'
                    }
                },
                [`Orders (${recentOrders?.length ?? 0})`]);
            const subsTab = UI.el('button', {
                    id: 'subscriptionsTab',
                    style: {
                        padding: '1rem',
                        background: 'none',
                        border: 'none',
                        fontWeight: '600',
                        cursor: 'pointer',
                        color: 'var(--text-secondary)'
                    }
                },
                [`Subscriptions (${allSubscriptions?.length ?? 0})`]);

            ordersTab.addEventListener('click', () => window.dashboardApp.switchActivityTab('orders'));
            subsTab.addEventListener('click', () => window.dashboardApp.switchActivityTab('subscriptions'));

            const orderRows = (recentOrders ?? []).map(o =>
                UI.el('tr', {style: {borderBottom: '1px solid var(--border-color)'}}, [
                    UI.el('td', {style: {padding: '.75rem'}}, [UI.formatDate(o.created_at)]),
                    UI.el('td', {style: {padding: '.75rem', fontWeight: '600'}}, [`#${o.order_number}`]),
                    UI.el('td', {style: {padding: '.75rem'}}, [o.one_time_subscription_id ? '📋 Subscription' : '🛍️ Order']),
                    UI.el('td', {
                        style: {
                            padding: '.75rem',
                            fontWeight: '600'
                        }
                    }, [`${o.currency} ${parseFloat(o.total).toFixed(2)}`]),
                    UI.el('td', {style: {padding: '.75rem'}}, [UI.statusBadge(o.status)]),
                    UI.el('td', {style: {padding: '.75rem'}}, [
                        UI.el('a', {
                            href: `/${this.siteSlug}/member/orders/${o.id}`,
                            style: {color: 'var(--primary-color)', textDecoration: 'none', fontWeight: '600'}
                        }, ['View →']),
                    ]),
                ])
            );

            const subsRows = (allSubscriptions ?? []).map(s =>
                UI.el('tr', {style: {borderBottom: '1px solid var(--border-color)'}}, [
                    UI.el('td', {style: {padding: '.75rem'}}, [UI.formatDate(s.created_at)]),
                    UI.el('td', {style: {padding: '.75rem', fontWeight: '600'}}, [s.plan_name ?? '']),
                    UI.el('td', {style: {padding: '.75rem'}}, [UI.statusBadge(s.status)]),
                    UI.el('td', {style: {padding: '.75rem'}}, [
                        UI.el('a', {
                            href: `/${this.siteSlug}/member/subscriptions`,
                            style: {color: 'var(--primary-color)', textDecoration: 'none', fontWeight: '600'}
                        }, ['Manage →']),
                    ]),
                ])
            );

            const ordersTable = orderRows.length
                ? UI.el('div', {style: {overflowX: 'auto'}}, [
                    UI.el('table', {style: {width: '100%', borderCollapse: 'collapse'}}, [
                        UI.el('thead', {}, [UI.el('tr', {style: {background: 'var(--bg-light)'}},
                            ['Date', 'Order #', 'Type', 'Total', 'Status', 'Action'].map(h =>
                                UI.el('th', {
                                    style: {
                                        padding: '.75rem',
                                        textAlign: 'left',
                                        fontSize: '.875rem',
                                        fontWeight: '600'
                                    }
                                }, [h])))]),
                        UI.el('tbody', {}, orderRows),
                    ]),
                    UI.el('div', {style: {marginTop: '1rem', textAlign: 'center'}}, [
                        UI.el('a', {
                            href: `/${this.siteSlug}/member/orders`,
                            style: {color: 'var(--primary-color)', textDecoration: 'none', fontWeight: '600'}
                        }, ['View All Orders →']),
                    ]),
                ])
                : UI.el('div', {
                    style: {
                        textAlign: 'center',
                        padding: '2rem',
                        color: 'var(--text-secondary)'
                    }
                }, ['No orders yet']);

            const subsTable = subsRows.length
                ? UI.el('div', {style: {overflowX: 'auto'}}, [
                    UI.el('table', {style: {width: '100%', borderCollapse: 'collapse'}}, [
                        UI.el('thead', {}, [UI.el('tr', {style: {background: 'var(--bg-light)'}},
                            ['Date', 'Plan', 'Status', 'Action'].map(h =>
                                UI.el('th', {
                                    style: {
                                        padding: '.75rem',
                                        textAlign: 'left',
                                        fontSize: '.875rem',
                                        fontWeight: '600'
                                    }
                                }, [h])))]),
                        UI.el('tbody', {}, subsRows),
                    ]),
                    UI.el('div', {style: {marginTop: '1rem', textAlign: 'center'}}, [
                        UI.el('a', {
                            href: `/${this.siteSlug}/member/subscriptions`,
                            style: {color: 'var(--primary-color)', textDecoration: 'none', fontWeight: '600'}
                        }, ['View All Subscriptions →']),
                    ]),
                ])
                : UI.el('div', {
                    style: {
                        textAlign: 'center',
                        padding: '2rem',
                        color: 'var(--text-secondary)'
                    }
                }, ['No subscriptions yet']);

            const ordersContent = UI.el('div', {id: 'ordersContent'});
            const subsContent = UI.el('div', {id: 'subscriptionsContent', style: {display: 'none'}});
            UI.render(ordersContent, [ordersTable]);
            UI.render(subsContent, [subsTable]);

            return UI.el('div', {
                style: {
                    background: 'white',
                    borderRadius: '1rem',
                    padding: '2rem',
                    boxShadow: 'var(--shadow)',
                    marginBottom: '2rem',
                    width: '100%',
                    gridColumn: '1/-1'
                }
            }, [
                UI.el('div', {
                    style: {
                        display: 'flex',
                        gap: '1rem',
                        marginBottom: '1.5rem',
                        borderBottom: '2px solid var(--border-color)'
                    }
                }, [ordersTab, subsTab]),
                ordersContent,
                subsContent,
            ]);
        }

        /* ── Section renderers ── */

        renderRecommended(pages) {
            const el = document.getElementById('section-recommended');
            if (!pages.length) return;

            const grid = UI.el('div', {className: 'content-grid'});
            pages.forEach(p => grid.appendChild(new RecommendedCard(p, this.siteSlug, this.giftModal).render()));

            UI.render(el, [
                UI.el('div', {className: 'content-section'}, [
                    UI.el('div', {className: 'section-header'}, [
                        UI.el('h2', {className: 'section-title'}, ['Recommended For You']),
                        UI.el('a', {
                            className: 'nav-arrow',
                            href: `/${this.siteSlug}/pages`,
                            style: {textDecoration: 'none'}
                        }, ['→']),
                    ]),
                    grid,
                ]),
            ]);
        }

        renderTrendingConversations(conversations) {
            const el = document.getElementById('section-trending-conversations');
            if (!conversations.length) return;

            const list = UI.el('div', {className: 'trending-list'});
            conversations.forEach((p, i) => list.appendChild(new TrendingConversationCard(p, i + 1, this.siteSlug, this.giftModal).render()));

            UI.render(el, [
                UI.el('div', {className: 'content-section'}, [
                    UI.el('div', {className: 'section-header'}, [
                        UI.el('div', {}, [
                            UI.el('h2', {className: 'section-title'}, ['Trending Conversations']),
                            UI.el('p', {
                                style: {
                                    color: 'var(--text-secondary)',
                                    fontSize: '.9375rem',
                                    marginTop: '.25rem'
                                }
                            }, ['Join the most popular discussions']),
                        ]),
                        UI.el('a', {
                            className: 'nav-arrow',
                            href: `/${this.siteSlug}/pages`,
                            style: {textDecoration: 'none'}
                        }, ['→']),
                    ]),
                    list,
                ]),
            ]);
        }

        renderTrendingPages(pages) {
            const el = document.getElementById('section-trending-pages');
            if (!pages.length) return;

            const grid = UI.el('div', {className: 'trending-pages-grid'});
            pages.forEach(p => grid.appendChild(new TrendingPageCard(p, this.siteSlug, this.giftModal).render()));

            UI.render(el, [
                UI.el('div', {className: 'content-section'}, [
                    UI.el('div', {className: 'section-header'}, [
                        UI.el('div', {}, [
                            UI.el('h2', {className: 'section-title'}, ['Trending Pages']),
                            UI.el('p', {
                                style: {
                                    color: 'var(--text-secondary)',
                                    fontSize: '.9375rem',
                                    marginTop: '.25rem'
                                }
                            }, ['The most-read content right now']),
                        ]),
                        UI.el('a', {
                            className: 'nav-arrow',
                            href: `/${this.siteSlug}/pages`,
                            style: {textDecoration: 'none'}
                        }, ['→']),
                    ]),
                    grid,
                ]),
            ]);
        }

        renderGiftedArticles(gifted) {
            const el = document.getElementById('section-gifted');
            if (!gifted.received_count && !gifted.given_count) return;

            const receivedCards = (gifted.received ?? []).map(g => new GiftCard(g, 'received', this.siteSlug).render());
            const givenCards = (gifted.given ?? []).map(g => new GiftCard(g, 'given', this.siteSlug).render());

            const receivedTab = UI.el('div', {id: 'receivedGifts', className: 'gifted-tab-content active'}, [
                ...receivedCards,
                (gifted.received_count ?? 0) > 5
                    ? UI.el('div', {className: 'view-all-link'}, [UI.el('a', {href: `/${this.siteSlug}/member/gifted-articles`}, [`View all ${gifted.received_count} received gifts →`])])
                    : null,
            ]);
            const givenTab = UI.el('div', {
                id: 'givenGifts',
                className: 'gifted-tab-content',
                style: {display: 'none'}
            }, [
                ...givenCards,
                (gifted.given_count ?? 0) > 5
                    ? UI.el('div', {className: 'view-all-link'}, [UI.el('a', {href: `/${this.siteSlug}/member/gifted-articles`}, [`View all ${gifted.given_count} given gifts →`])])
                    : null,
            ]);

            if (!receivedCards.length) UI.render(receivedTab, [UI.el('div', {className: 'empty-gifts'}, ["You haven't received any gifted articles yet."])]);
            if (!givenCards.length) UI.render(givenTab, [UI.el('div', {className: 'empty-gifts'}, ["You haven't gifted any articles yet."])]);

            const tabReceived = UI.el('button', {className: 'gifted-tab active'}, [`Received (${gifted.received_count ?? 0})`]);
            const tabGiven = UI.el('button', {className: 'gifted-tab'}, [`Given (${gifted.given_count ?? 0})`]);

            tabReceived.addEventListener('click', () => {
                tabReceived.classList.add('active');
                tabGiven.classList.remove('active');
                receivedTab.style.display = 'block';
                givenTab.style.display = 'none';
            });
            tabGiven.addEventListener('click', () => {
                tabGiven.classList.add('active');
                tabReceived.classList.remove('active');
                givenTab.style.display = 'block';
                receivedTab.style.display = 'none';
            });

            UI.render(el, [
                UI.el('div', {className: 'gifted-section'}, [
                    UI.el('h2', {className: 'section-title'}, ['🎁 Gifted Articles']),
                    UI.el('div', {className: 'gifted-tabs'}, [tabReceived, tabGiven]),
                    receivedTab,
                    givenTab,
                ]),
            ]);
        }

        renderNewsletters(newsletters) {
            const el = document.getElementById('section-newsletters');
            if (!newsletters.length) return;

            const items = newsletters.map(n => {
                const statusClass = n.is_active ? 'active' : (n.can_toggle ? 'inactive' : 'locked');
                const statusText = n.is_active ? '✓ Subscribed' : (n.can_toggle ? '✗ Unsubscribed' : '🔒 Locked');

                let actionEl;
                if (!n.can_toggle && !n.is_active) {
                    actionEl = UI.el('div', {className: 'locked-message'}, [
                        `${n.lock_reason}. `,
                        UI.el('a', {
                            href: `/${this.siteSlug}/subscriptions`,
                            style: {color: '#667eea', fontWeight: '600'}
                        }, ['Upgrade plan']),
                    ]);
                } else {
                    const btn = UI.el('button', {
                        className: `btn-toggle ${n.is_active ? 'unsubscribe' : 'subscribe'}`,
                        disabled: !n.can_toggle ? 'true' : null,
                    }, [n.is_active ? 'Unsubscribe' : 'Subscribe']);
                    btn.addEventListener('click', () => window.dashboardApp.toggleNewsletter(n.subscription_id, n.newsletter_id, !n.is_active, btn));
                    actionEl = btn;
                }

                return UI.el('div', {className: 'newsletter-item'}, [
                    UI.el('div', {className: 'newsletter-info'}, [
                        UI.el('div', {className: 'newsletter-name'}, [n.title]),
                        UI.el('div', {className: 'newsletter-meta'}, [
                            UI.el('span', {className: `newsletter-status ${statusClass}`}, [statusText]),
                            UI.el('span', {}, [`${n.interval.charAt(0).toUpperCase() + n.interval.slice(1)} newsletter`]),
                        ]),
                    ]),
                    UI.el('div', {className: 'newsletter-actions'}, [actionEl]),
                ]);
            });

            const msgEl = UI.el('div', {id: 'newsletterMessages'});

            UI.render(el, [
                UI.el('div', {className: 'newsletter-prefs-section'}, [
                    UI.el('div', {className: 'section-header'}, [
                        UI.el('h2', {className: 'section-title'}, [
                            '📧 Newsletter Preferences ',
                            UI.el('span', {className: 'newsletter-count'}, [String(newsletters.length)]),
                        ]),
                    ]),
                    msgEl,
                    UI.el('div', {className: 'newsletter-list'}, items),
                ]),
            ]);
        }

        renderRewards(rewards) {
            const el = document.getElementById('section-rewards');
            if (!rewards.length) return;

            const cards = rewards.map(r => {
                let valueEl = null;
                if (r.reward_data) {
                    if (r.reward_data.voucher_code) {
                        valueEl = UI.el('div', {className: 'reward-value-box'}, [
                            UI.el('div', {className: 'reward-value-label'}, ['Voucher Value']),
                            UI.el('div', {className: 'reward-value'}, [`${r.reward_data.currency} ${parseFloat(r.reward_data.value).toFixed(2)}`]),
                        ]);
                    } else if (r.reward_data.discount_value) {
                        const prefix = r.reward_data.discount_type === 'percentage' ? '' : '$';
                        const suffix = r.reward_data.discount_type === 'percentage' ? '%' : '';
                        valueEl = UI.el('div', {className: 'reward-value-box'}, [
                            UI.el('div', {className: 'reward-value-label'}, ['Discount']),
                            UI.el('div', {className: 'reward-value'}, [`${prefix}${r.reward_data.discount_value}${suffix} OFF`]),
                        ]);
                    } else if (r.reward_data.points) {
                        valueEl = UI.el('div', {className: 'reward-value-box'}, [
                            UI.el('div', {className: 'reward-value-label'}, ['Points']),
                            UI.el('div', {className: 'reward-value'}, [`${r.reward_data.points} points`]),
                        ]);
                    }
                }

                const claimBtn = UI.el('button', {className: 'btn-claim'}, ['Claim Reward']);
                claimBtn.addEventListener('click', () => window.dashboardApp.claimReward(r.id, claimBtn));

                return UI.el('div', {className: 'reward-card'}, [
                    UI.el('div', {className: 'reward-card-header'}, [
                        UI.el('div', {}, [
                            UI.el('h3', {className: 'reward-name'}, [r.name]),
                            UI.el('div', {className: 'reward-type'}, [r.type.charAt(0).toUpperCase() + r.type.slice(1)]),
                        ]),
                    ]),
                    UI.el('p', {className: 'reward-description'}, [r.description]),
                    valueEl,
                    UI.el('div', {className: 'reward-actions'}, [claimBtn]),
                ]);
            });

            UI.render(el, [
                UI.el('div', {className: 'rewards-section'}, [
                    UI.el('div', {className: 'rewards-content'}, [
                        UI.el('div', {className: 'rewards-header'}, [
                            UI.el('span', {className: 'rewards-icon'}, ['🎁']),
                            UI.el('div', {}, [
                                UI.el('h2', {className: 'rewards-title'}, ['Unclaimed Rewards']),
                                UI.el('span', {className: 'rewards-count'}, [`${rewards.length} reward${rewards.length > 1 ? 's' : ''} available`]),
                            ]),
                        ]),
                        UI.el('div', {className: 'rewards-grid'}, cards),
                        UI.el('a', {
                            href: `/${this.siteSlug}/member/rewards`,
                            className: 'btn-view-all'
                        }, ['View All Rewards']),
                    ]),
                ]),
            ]);
        }

        renderRecommendedProducts(products) {
            const el = document.getElementById('section-products');
            if (!products.length) return;

            const cards = products.map(p => {
                const imgEl = p.image
                    ? UI.el('img', {src: p.image, alt: p.name, className: 'product-image', loading: 'lazy'})
                    : UI.el('div', {
                        className: 'product-image',
                        style: {
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            background: '#e5e7eb',
                            color: '#9ca3af'
                        }
                    }, ['📦']);

                return UI.el('a', {href: `/${this.siteSlug}/shop/details/${p.slug}`, className: 'product-card'}, [
                    UI.el('div', {className: 'product-image-container'}, [
                        imgEl,
                        p.has_discount ? UI.el('div', {className: 'product-badge'}, [`-${p.discount_percentage}%`]) : null,
                    ]),
                    UI.el('div', {className: 'product-content'}, [
                        UI.el('h3', {className: 'product-title'}, [p.name]),
                        p.description ? UI.el('p', {className: 'product-description'}, [p.description]) : null,
                        UI.el('div', {className: 'product-footer'}, [
                            UI.el('div', {className: 'product-price'}, [
                                UI.el('span', {className: 'price-current'}, [`$${parseFloat(p.has_discount ? p.sale_price : p.price).toFixed(2)}`]),
                                p.has_discount ? UI.el('span', {className: 'price-original'}, [`$${parseFloat(p.price).toFixed(2)}`]) : null,
                            ]),
                            UI.el('button', {
                                className: 'product-cta',
                                onclick: e => {
                                    e.preventDefault();
                                    window.location.href = `/${this.siteSlug}/shop/details/${p.slug}`;
                                },
                            }, ['Buy Now →']),
                        ]),
                    ]),
                ]);
            });

            UI.render(el, [
                UI.el('div', {className: 'products-section'}, [
                    UI.el('h2', {className: 'section-title'}, ['Recommended for You']),
                    UI.el('p', {
                        style: {
                            color: '#6b7280',
                            marginBottom: '1rem'
                        }
                    }, ['Curated products based on your interests']),
                    UI.el('div', {className: 'products-grid'}, cards),
                ]),
            ]);
        }

        renderSubscriptions(grouped) {
            const el = document.getElementById('section-subscriptions');
            const allActive = [...(grouped.active?.print ?? []), ...(grouped.active?.digital ?? [])];
            const allExpired = [...(grouped.expired?.print ?? []), ...(grouped.expired?.digital ?? [])];
            if (!allActive.length && !allExpired.length) return;

            const activeGrid = UI.el('div', {id: 'activeSubscriptions', className: 'subscription-grid'});
            const expiredGrid = allExpired.length ? UI.el('div', {
                id: 'expiredSubscriptions',
                className: 'subscription-grid',
                style: {display: 'none'}
            }) : null;

            if (allActive.length) {
                allActive.forEach(s => activeGrid.appendChild(this._buildSubscriptionCard(s, false)));
            } else {
                UI.render(activeGrid, [UI.emptyState({
                    icon: '💤',
                    title: 'No Active Subscriptions',
                    body: 'Your subscriptions have expired or been cancelled.'
                })]);
            }
            if (expiredGrid) allExpired.forEach(s => expiredGrid.appendChild(this._buildSubscriptionCard(s, true)));

            const tabActiveBtn = UI.el('button', {
                className: 'tab-button active',
                id: 'activeTab'
            }, ['Active Subscriptions']);
            tabActiveBtn.addEventListener('click', () => window.dashboardApp.switchSubscriptionTab('active'));

            const tabNodes = [tabActiveBtn];
            if (allExpired.length) {
                const tabExpiredBtn = UI.el('button', {
                    className: 'tab-button',
                    id: 'expiredTab'
                }, ['Expired Subscriptions']);
                tabExpiredBtn.addEventListener('click', () => window.dashboardApp.switchSubscriptionTab('expired'));
                tabNodes.push(tabExpiredBtn);
            }

            UI.render(el, [
                UI.el('div', {className: 'subscriptions-section'}, [
                    UI.el('div', {className: 'subscriptions-header'}, [
                        UI.el('h2', {}, ['📰 My Subscriptions']),
                        UI.el('a', {
                            href: `/${this.siteSlug}/member/subscriptions`,
                            className: 'btn btn-secondary'
                        }, ['View All Subscriptions →']),
                    ]),
                    UI.el('div', {className: 'subscription-tabs'}, tabNodes),
                    activeGrid,
                    expiredGrid,
                ]),
            ]);
        }

        _buildSubscriptionCard(s, isExpired) {
            const detailItems = [
                UI.el('div', {className: 'detail-item'}, [
                    UI.el('span', {className: 'detail-label'}, ['Start Date']),
                    UI.el('span', {className: 'detail-value'}, [UI.formatDate(s.start_date)]),
                ]),
                s.end_date ? UI.el('div', {className: 'detail-item'}, [
                    UI.el('span', {className: 'detail-label'}, [isExpired ? 'Ended' : 'End Date']),
                    UI.el('span', {className: 'detail-value'}, [UI.formatDate(s.end_date)]),
                ]) : null,
                !isExpired && s.next_billing_date ? UI.el('div', {className: 'detail-item'}, [
                    UI.el('span', {className: 'detail-label'}, ['Next Billing']),
                    UI.el('span', {className: 'detail-value'}, [UI.formatDate(s.next_billing_date)]),
                ]) : null,
            ].filter(Boolean);

            const actions = [
                !isExpired && s.archive_url
                    ? UI.el('a', {href: s.archive_url, className: 'btn btn-secondary'}, ['📚 View Archive'])
                    : null,
                (!isExpired && s.should_show_renew) || (isExpired && s.can_renew)
                    ? UI.el('a', {
                            href: `/${this.siteSlug}/member/subscriptions/${s.id}/renew`,
                            className: 'btn btn-primary'
                        },
                        [`🔄 ${isExpired ? 'Renew Subscription' : 'Renew Now'}`])
                    : null,
            ].filter(Boolean);

            return UI.el('div', {className: `subscription-card${isExpired ? ' expired' : ''}`}, [
                UI.el('div', {className: 'subscription-header'}, [
                    UI.el('div', {className: 'subscription-title'}, [
                        UI.el('div', {className: `subscription-icon ${s.type === 'print' ? 'icon-print' : 'icon-digital'}`}, [s.type === 'print' ? '📦' : '💻']),
                        UI.el('div', {className: 'subscription-name'}, [
                            UI.el('h3', {}, [s.plan_name]),
                            UI.el('div', {className: 'subscription-type'}, [`${s.type.charAt(0).toUpperCase() + s.type.slice(1)} Subscription`]),
                        ]),
                    ]),
                    UI.el('span', {className: `subscription-status status-${s.status}`}, [s.status.charAt(0).toUpperCase() + s.status.slice(1)]),
                ]),
                UI.el('div', {className: 'subscription-details'}, detailItems),
                !isExpired && s.auto_renew ? UI.el('div', {className: 'auto-renew-badge'}, ['🔄 Auto-Renew Enabled']) : null,
                s.newsletters?.length ? UI.el('div', {className: 'subscription-newsletters'}, [
                    UI.el('div', {className: 'newsletters-label'}, ['Included Newsletters:']),
                    UI.el('div', {className: 'newsletter-tags'}, s.newsletters.map(n => UI.el('span', {className: 'newsletter-tag'}, [n.title]))),
                ]) : null,
                actions.length ? UI.el('div', {className: 'subscription-actions'}, actions) : null,
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
       DashboardManager — orchestrates loading, state, and actions
    ───────────────────────────────────────────────────────────── */
    class DashboardManager {
        constructor({siteSlug}) {
            this.siteSlug = siteSlug;
            this.giftModal = GiftModal;
            this.builder = new DashboardBuilder(siteSlug, GiftModal);

            GiftModal.init(siteSlug);
            document.addEventListener('DOMContentLoaded', () => this._load());
        }

        /* ── Bootstrap ── */

        async _load() {
            try {
                const token = getMemberApiToken?.() ?? null;
                const headers = token ? {Authorization: `Bearer ${token}`} : {};

                const [overviewRes, activityRes, discoveryRes, newslettersRes, rewardsRes, subscriptionsRes, statsRes] = await Promise.all([
                    fetch(`/api/${this.siteSlug}/member/dashboard/overview`, {headers}),
                    fetch(`/api/${this.siteSlug}/member/dashboard/activity`, {headers}),
                    fetch(`/api/${this.siteSlug}/member/dashboard/discovery`, {headers}),
                    fetch(`/api/${this.siteSlug}/member/dashboard/newsletters`, {headers}),
                    fetch(`/api/${this.siteSlug}/member/dashboard/rewards`, {headers}),
                    fetch(`/api/${this.siteSlug}/member/dashboard/subscriptions`, {headers}),
                    fetch(`/api/${this.siteSlug}/member/dashboard/stats`, {headers}),
                ]);

                if ([overviewRes, activityRes, discoveryRes, newslettersRes, rewardsRes, subscriptionsRes, statsRes].some(r => r.status === 401)) {
                    clearMemberApiToken?.();
                    window.location.href = `/${this.siteSlug}/member/login`;
                    return;
                }

                const [overview, activity, discovery, newsletters, rewards, subscriptions, statsData] = await Promise.all([
                    overviewRes.json(), activityRes.json(), discoveryRes.json(),
                    newslettersRes.json(), rewardsRes.json(), subscriptionsRes.json(), statsRes.json(),
                ]);

                if (!overview.success || !activity.success || !discovery.success) {
                    throw new Error('Failed to load core dashboard data');
                }

                this._render({
                    ...overview.data, ...activity.data, ...discovery.data,
                    stats: statsData.data?.stats ?? {}
                });

                const isVerified = overview.data.member?.email_verified_at !== null;

                if (isVerified && discovery.success) {
                    this.builder.renderRecommended(discovery.data.recommended_pages ?? []);
                    this.builder.renderTrendingConversations(discovery.data.trending_conversations ?? []);
                    this.builder.renderTrendingPages(discovery.data.trending_pages ?? []);
                    this.builder.renderGiftedArticles(discovery.data.gifted_articles ?? {});
                    this.builder.renderRecommendedProducts(discovery.data.recommended_products ?? []);
                }
                if (isVerified) {
                    if (newsletters.success) this.builder.renderNewsletters(newsletters.data.newsletters ?? []);
                    if (rewards.success) this.builder.renderRewards(rewards.data.unclaimed_rewards ?? []);
                    if (subscriptions.success) this.builder.renderSubscriptions(subscriptions.data.grouped_subscriptions ?? {});
                }
            } catch {
                document.getElementById('dashboard-loading').style.display = 'none';
                UI.toast('Failed to load dashboard. Please refresh the page.', 'error');
            }
        }

        _render(data) {
            const root = document.getElementById('dashboard-content');
            const loading = document.getElementById('dashboard-loading');
            const verified = data.member.email_verified_at !== null;

            UI.render(root, verified
                ? this.builder.buildVerifiedDashboard(data)
                : this.builder.buildUnverifiedDashboard(data)
            );
            loading.style.display = 'none';
            root.style.display = 'block';
        }

        /* ── Tab handlers ── */

        switchActivityTab(tab) {
            const ordersEl = document.getElementById('ordersContent');
            const subsEl = document.getElementById('subscriptionsContent');
            if (ordersEl) ordersEl.style.display = tab === 'orders' ? 'block' : 'none';
            if (subsEl) subsEl.style.display = tab === 'subscriptions' ? 'block' : 'none';
            ['orders', 'subscriptions'].forEach(t => {
                const btn = document.getElementById(t + 'Tab');
                if (!btn) return;
                btn.style.borderBottom = t === tab ? '3px solid var(--primary-color)' : 'none';
                btn.style.color = t === tab ? 'var(--text-primary)' : 'var(--text-secondary)';
            });
        }

        switchSubscriptionTab(tab) {
            document.getElementById('activeSubscriptions').style.display = tab === 'active' ? 'grid' : 'none';
            const expired = document.getElementById('expiredSubscriptions');
            if (expired) expired.style.display = tab === 'expired' ? 'grid' : 'none';
            document.getElementById('activeTab')?.classList.toggle('active', tab === 'active');
            document.getElementById('expiredTab')?.classList.toggle('active', tab === 'expired');
        }

        /* ── Actions ── */

        async resendVerification() {
            const btn = document.getElementById('resendBtn');
            if (!btn) return;
            btn.disabled = true;
            UI.text(btn, '⏳ Sending…');

            try {
                const result = await api(`/${this.siteSlug}/member/resend-verification`, {method: 'POST'});
                if (result.success) {
                    UI.text(btn, '✓ Email Sent!');
                    btn.style.background = 'linear-gradient(135deg,var(--success-color),#059669)';
                    setTimeout(() => {
                        UI.text(btn, '📧 Resend Verification Email');
                        btn.style.background = '';
                        btn.disabled = false;
                    }, 3000);
                } else {
                    UI.toast(result.message || 'Failed to send email. Please try again.', 'error');
                    UI.text(btn, '📧 Resend Verification Email');
                    btn.disabled = false;
                }
            } catch {
                UI.toast('An error occurred. Please try again.', 'error');
                UI.text(btn, '📧 Resend Verification Email');
                btn.disabled = false;
            }
        }

        async toggleNewsletter(subscriptionId, newsletterId, subscribe, button) {
            const originalText = button.textContent.trim();
            button.disabled = true;
            UI.text(button, subscribe ? 'Subscribing…' : 'Unsubscribing…');

            try {
                const result = await api(`/${this.siteSlug}/member/newsletters/toggle`, {
                    method: 'POST',
                    body: JSON.stringify({subscription_id: subscriptionId, newsletter_id: newsletterId, subscribe}),
                });

                UI.toast(result.message, 'success');
                UI.text(button, subscribe ? 'Unsubscribe' : 'Subscribe');
                button.classList.toggle('subscribe');
                button.classList.toggle('unsubscribe');

                const badge = button.closest('.newsletter-item')?.querySelector('.newsletter-status');
                if (badge) {
                    badge.className = `newsletter-status ${subscribe ? 'active' : 'inactive'}`;
                    UI.text(badge, subscribe ? '✓ Subscribed' : '✗ Unsubscribed');
                }
            } catch (err) {
                UI.toast(err.message, 'error');
                UI.text(button, originalText);
            }

            button.disabled = false;
        }

        async claimReward(rewardId, btn) {
            try {
                const result = await api(`/api/${this.siteSlug}/member/rewards/${rewardId}/claim`, {method: 'POST'});
                if (result.success) {
                    btn?.closest('.reward-card')?.remove();
                    if (!document.querySelectorAll('.reward-card').length) {
                        document.getElementById('section-rewards').innerHTML = '';
                    }
                } else {
                    UI.toast(result.message, 'error');
                }
            } catch (err) {
                UI.toast(err.message ?? 'An error occurred. Please try again.', 'error');
            }
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Init
    ───────────────────────────────────────────────────────────── */
    window.dashboardApp = new DashboardManager({
        siteSlug: '<?= \App\Framework\Support\SiteContext::slug() ?>',
    });
</script>
</body>
</html>