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

        /* ── Verification banner ── */
        .verification-banner {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
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
            transition: all .3s ease;
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
            font-size: 1rem;
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 2px;
        }

        /* ── Dashboard cards ── */
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
            transition: all .3s ease;
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
            transition: opacity .3s ease;
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
            transition: transform .2s ease;
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
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

        /* ── Limited access (unverified) ── */
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

            .verification-banner {
                padding: 1.5rem;
            }

            .verification-actions {
                flex-direction: column;
            }

            .btn-resend {
                width: 100%;
                justify-content: center;
            }
        }

        .products-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .product-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }

        .product-image-container {
            position: relative;
            width: 100%;
            padding-top: 75%; /* 4:3 aspect ratio */
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
            top: 0.75rem;
            right: 0.75rem;
            background: #ef4444;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
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
            margin-bottom: 0.5rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-description {
            font-size: 0.875rem;
            color: #6b7280;
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
            border-top: 1px solid #e5e7eb;
        }

        .product-price {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .price-current {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }

        .price-original {
            font-size: 0.875rem;
            color: #9ca3af;
            text-decoration: line-through;
        }

        .product-cta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .product-cta:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
        }

        .subscriptions-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .subscriptions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .subscriptions-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .subscription-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            font-weight: 600;
            cursor: pointer;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s ease;
        }

        .tab-button:hover {
            color: #667eea;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .subscription-grid {
            display: grid;
            gap: 1.5rem;
        }

        .subscription-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .subscription-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.1);
        }

        .subscription-card.expired {
            opacity: 0.7;
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
            gap: 0.75rem;
        }

        .subscription-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .icon-print {
            background: linear-gradient(135deg, #f59e0b20 0%, #d9770620 100%);
        }

        .icon-digital {
            background: linear-gradient(135deg, #3b82f620 0%, #2563eb20 100%);
        }

        .subscription-name h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .subscription-type {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .subscription-status {
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
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
            border-radius: 0.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .detail-value {
            font-size: 0.875rem;
            color: #1f2937;
            font-weight: 500;
        }

        .subscription-newsletters {
            margin-bottom: 1rem;
        }

        .newsletters-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .newsletter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .newsletter-tag {
            padding: 0.375rem 0.75rem;
            background: #e0e7ff;
            color: #3730a3;
            border-radius: 0.375rem;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .subscription-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #4b5563;
        }

        .auto-renew-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            background: #d1fae5;
            color: #065f46;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .subscriptions-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .subscription-tabs {
                width: 100%;
                overflow-x: auto;
            }

            .subscription-header {
                flex-direction: column;
                gap: 1rem;
            }

            .subscription-details {
                grid-template-columns: 1fr;
            }

            .subscription-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        .newsletter-prefs-section {
            background: white;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 12px;
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
            transition: all 0.3s ease;
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
            transition: all 0.3s ease;
        }

        .btn-toggle.subscribe {
            background: #667eea;
            color: white;
        }

        .btn-toggle.subscribe:hover:not(:disabled) {
            background: #5568d3;
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
            opacity: 0.5;
            cursor: not-allowed;
        }

        .locked-message {
            font-size: 13px;
            color: #92400e;
            font-style: italic;
            max-width: 300px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .success-message {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: none;
            animation: slideIn 0.3s ease;
        }

        .error-message {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: none;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
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
        }

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
            transition: all 0.2s ease;
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
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }

        .gift-card:hover {
            background: #e5e7eb;
            transform: translateX(4px);
        }

        .gift-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .gift-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9375rem;
            flex: 1;
        }

        .gift-status {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
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
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }

        .gift-message {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-style: italic;
            margin-bottom: 0.75rem;
            padding-left: 1rem;
            border-left: 3px solid var(--border-color);
        }

        .gift-actions {
            display: flex;
            gap: 0.75rem;
        }

        .gift-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
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
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-arrow:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .content-card,
        .conversation-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .content-card:hover,
        .conversation-card:hover {
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
            padding: 0.375rem 0.875rem;
            border-radius: 0.25rem;
            font-size: 0.8125rem;
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

        .conversation-text {
            padding: 1.25rem;
            padding-bottom: 0.75rem;
            font-size: 0.9375rem;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .conversation-stats {
            display: flex;
            gap: 1rem;
            padding: 0 1.25rem 1.25rem;
        }

        .stat-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 0.9375rem;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .stat-btn:hover {
            color: var(--primary-color);
        }

        .stat-btn span:first-child {
            font-size: 1.125rem;
        }

        .back-to-top {
            padding: 0.875rem 2.5rem;
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
            border-radius: 2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .back-to-top:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .main-content {
                padding: 1rem;
            }

            .promo-banner {
                min-height: 220px;
            }

            .banner-content {
                max-width: 100%;
                padding: 1.5rem;
            }

            .banner-content h2 {
                font-size: 1.5rem;
            }

            .banner-content p {
                font-size: 1rem;
                margin-bottom: 1.25rem;
            }

            .banner-image {
                opacity: 0.2;
                width: 100%;
            }

            .overview-grid {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }

        .rewards-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            border: 2px solid #e5e7eb;
        }

        .rewards-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
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
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .rewards-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .rewards-count {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .rewards-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .reward-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border: 2px solid rgba(102, 126, 234, 0.15);
            border-radius: 0.75rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .reward-card:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.15);
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
            color: #1f2937;
            margin: 0 0 0.25rem 0;
        }

        .reward-type {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .reward-description {
            color: #4b5563;
            margin-bottom: 1rem;
            line-height: 1.5;
            font-size: 0.9375rem;
        }

        .reward-value-box {
            background: white;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .reward-value-label {
            font-size: 0.8125rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .reward-value {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .reward-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-claim {
            flex: 1;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9375rem;
        }

        .btn-claim:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }

        .btn-view-all {
            padding: 0.75rem 1.5rem;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-view-all:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        @media (max-width: 768px) {
            .rewards-section {
                padding: 1.5rem;
            }

            .rewards-title {
                font-size: 1.5rem;
            }

            .reward-card {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>
@include('member._header')

<div class="container" id="dashboard-root">
    @include('member/components/dashboard-banner')
    <div id="dashboard-loading" style="text-align:center;padding:4rem;">
        <p style="color:var(--text-secondary);">Loading your dashboard…</p>
    </div>
    <div id="dashboard-content" style="display:none;"></div>

    <div id="section-recommended"></div>
    <div id="section-trending"></div>
    <div id="section-gifted"></div>
    <div id="section-newsletters"></div>
    <div id="section-products"></div>

    <div id="section-rewards"></div>

    <div id="section-subscriptions"></div>
    @include('member/components/back-to-top')
</div>

<script>
    /* ── Bootstrap ───────────────────────────────────────────── */

    async function loadDashboard() {
        try {
            const token = getMemberApiToken();
            const headers = token ? {Authorization: `Bearer ${token}`} : {};

            const [overviewRes, activityRes, discoveryRes, newslettersRes, rewardsRes, subscriptionsRes, statsRes] = await Promise.all([
                fetch(`/api/${SITE_SLUG}/member/dashboard/overview`, {headers}),
                fetch(`/api/${SITE_SLUG}/member/dashboard/activity`, {headers}),
                fetch(`/api/${SITE_SLUG}/member/dashboard/discovery`, {headers}),
                fetch(`/api/${SITE_SLUG}/member/dashboard/newsletters`, {headers}),
                fetch(`/api/${SITE_SLUG}/member/dashboard/rewards`, {headers}),
                fetch(`/api/${SITE_SLUG}/member/dashboard/subscriptions`, {headers}),
                fetch(`/api/${SITE_SLUG}/member/stats`, {headers}),
            ]);

            if ([overviewRes, activityRes, discoveryRes, newslettersRes, rewardsRes, subscriptionsRes, statsRes]
                .some(r => r.status === 401)) {
                clearMemberApiToken();
                window.location.href = `/${SITE_SLUG}/member/login`;
                return;
            }

            const [overview, activity, discovery, newsletters, rewards, subscriptions, statsData] = await Promise.all([
                overviewRes.json(),
                activityRes.json(),
                discoveryRes.json(),
                newslettersRes.json(),
                rewardsRes.json(),
                subscriptionsRes.json(),
                statsRes.json(),
            ]);

            if (!overview.success || !activity.success || !discovery.success) {
                throw new Error('Failed to load core dashboard data');
            }

            // Core dashboard (verified/unverified split)
            renderDashboard({
                ...overview.data, ...activity.data, ...discovery.data,
                stats: statsData.data?.stats ?? {}
            });

            // Satellite sections — only render when verified (discovery data is empty for unverified)
            const isVerified = overview.data.member?.email_verified_at !== null;
            if (isVerified) {
                if (discovery.success) {
                    console.log('discovery', discovery)
                    renderRecommended(discovery.data.recommended_pages ?? []);
                    renderTrending(discovery.data.trending_conversations ?? []);
                    renderGiftedArticles(discovery.data.gifted_articles ?? {});
                    renderRecommendedProducts(discovery.data.recommended_products ?? []);
                }
                if (newsletters.success) renderNewsletters(newsletters.data.newsletters ?? []);
                if (rewards.success) renderRewards(rewards.data.unclaimed_rewards ?? []);
                if (subscriptions.success) renderSubscriptions(subscriptions.data.grouped_subscriptions ?? {});
            }

        } catch (e) {
            document.getElementById('dashboard-loading').innerHTML =
                `<p style="color:var(--danger-color);">Failed to load dashboard. Please refresh.</p>`;
        }
    }

    /* ── Recommended For You ─────────────────────────────────── */

    function renderRecommended(pages) {
        const el = document.getElementById('section-recommended');
        if (!pages.length) return;

        const cards = pages.map(p => `
        <article class="content-card">
            <div class="content-image"
                 onclick="window.location.href='/${SITE_SLUG}/${escHtml(p.slug)}'">
                ${p.categories?.[0] ? `<span class="content-badge">${escHtml(p.categories[0].name)}</span>` : ''}
                <img src="${escHtml(p.metadata?.featured_image || 'https://via.placeholder.com/300x200')}"
                     alt="${escHtml(p.title)}">
            </div>
            <h3 class="content-title"
                onclick="window.location.href='/${SITE_SLUG}/${escHtml(p.slug)}'">${escHtml(p.title)}</h3>
            <button onclick="openGiftModal('${escHtml(p.slug)}', '${escHtml(p.title)}')"
                    class="btn btn-secondary" style="margin-top:10px;">
                🎁 Gift This Article
            </button>
        </article>`).join('');

        el.innerHTML = `
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">Recommended For You</h2>
                <a href="/${SITE_SLUG}/pages" class="nav-arrow" style="text-decoration:none;">→</a>
            </div>
            <div class="content-grid">${cards}</div>
        </div>`;
    }

    /* ── Trending Conversations ───────────────────────────────── */

    function renderTrending(conversations) {
        const el = document.getElementById('section-trending');
        if (!conversations.length) return;

        const cards = conversations.map(p => `
        <article class="conversation-card"
                 onclick="window.location.href='/${SITE_SLUG}/${escHtml(p.slug)}'">
            <div class="content-image">
                ${p.categories?.[0] ? `<span class="content-badge">${escHtml(p.categories[0].name)}</span>` : ''}
                <img src="${escHtml(p.metadata?.featured_image || 'https://via.placeholder.com/300x200')}"
                     alt="${escHtml(p.title)}">
            </div>
            <p class="conversation-text">
                ${escHtml(p.title.length > 150 ? p.title.substring(0, 150) + '...' : p.title)}
            </p>
            <div class="conversation-stats">
                <button class="stat-btn" onclick="event.stopPropagation()">
                    <span>👍</span><span>${(p.like_count_24h ?? 0).toLocaleString()}</span>
                </button>
                <button class="stat-btn" onclick="event.stopPropagation()">
                    <span>💬</span><span>${(p.comment_count_24h ?? 0).toLocaleString()}</span>
                </button>
            </div>
            <button onclick="event.stopPropagation(); openGiftModal('${escHtml(p.slug)}', '${escHtml(p.title)}')"
                    class="btn btn-secondary" style="margin-top:10px;">
                🎁 Gift This Article
            </button>
        </article>`).join('');

        el.innerHTML = `
        <div class="content-section">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Trending Conversations</h2>
                    <p style="color:var(--text-secondary);font-size:.9375rem;">Join the most popular discussions</p>
                </div>
                <a href="/${SITE_SLUG}/pages" class="nav-arrow" style="text-decoration:none;">→</a>
            </div>
            <div class="content-grid">${cards}</div>
        </div>`;
    }

    /* ── Gifted Articles ──────────────────────────────────────── */

    function renderGiftedArticles(gifted) {
        alert('here2')
        const el = document.getElementById('section-gifted');
        if (!gifted.received_count && !gifted.given_count) return;

        alert('here')

        const received = (gifted.received ?? []).map(g => `
        <div class="gift-card">
            <div class="gift-header">
                <div class="gift-title">${escHtml(g.page?.title ?? '')}</div>
                <span class="gift-status ${escHtml(g.status?.toLowerCase())}">${escHtml(g.status)}</span>
            </div>
            <div class="gift-meta">
               <span>
                    👤 From: ${
            escHtml(
                (
                    g.giftedBy?.first_name
                        ? `${g.giftedBy.first_name} ${g.giftedBy.last_name ?? ''}`
                        : g.giftedBy?.email ?? ''
                )
            )
        }
                    </span>
                <span>📅 ${formatDate(g.gifted_at)}</span>
            </div>
            ${g.personal_message ? `<div class="gift-message">"${escHtml(g.personal_message)}"</div>` : ''}
            <div class="gift-actions">
                ${g.status === 'pending'
            ? `<a href="/${SITE_SLUG}/gift/${escHtml(g.gift_token)}" class="gift-btn gift-btn-primary">Claim & Read</a>`
            : `<a href="/${SITE_SLUG}/${escHtml(g.page?.slug)}" class="gift-btn gift-btn-primary">Read Article</a>`
        }
            </div>
        </div>`).join('');

        const given = (gifted.given ?? []).map(g => `
        <div class="gift-card">
            <div class="gift-header">
                <div class="gift-title">${escHtml(g.page?.title ?? '')}</div>
                <span class="gift-status ${escHtml(g.status?.toLowerCase())}">${escHtml(g.status)}</span>
            </div>
            <div class="gift-meta">
                <span>📧 To: ${escHtml(g.recipient_email)}</span>
                <span>📅 ${formatDate(g.gifted_at)}</span>
            </div>
            ${g.status === 'claimed' && g.claimed_at
            ? `<div style="font-size:.875rem;color:var(--success-color);margin-bottom:.75rem;">✓ Claimed on ${formatDate(g.claimed_at)}</div>`
            : ''
        }
        </div>`).join('');

        el.innerHTML = `
        <div class="gifted-section">
            <h2 class="section-title">🎁 Gifted Articles</h2>
            <div class="gifted-tabs">
                <button class="gifted-tab active" onclick="switchGiftTab('received', this)">
                    Received (${gifted.received_count ?? 0})
                </button>
                <button class="gifted-tab" onclick="switchGiftTab('given', this)">
                    Given (${gifted.given_count ?? 0})
                </button>
            </div>
            <div id="receivedGifts" class="gifted-tab-content active">
                ${received || '<div class="empty-gifts"><p>You haven\'t received any gifted articles yet.</p></div>'}
                ${(gifted.received_count ?? 0) > 5
            ? `<div class="view-all-link"><a href="/${SITE_SLUG}/member/gifted-articles">View all ${gifted.received_count} received gifts →</a></div>`
            : ''}
            </div>
            <div id="givenGifts" class="gifted-tab-content" style="display:none;">
                ${given || '<div class="empty-gifts"><p>You haven\'t gifted any articles yet.</p></div>'}
                ${(gifted.given_count ?? 0) > 5
            ? `<div class="view-all-link"><a href="/${SITE_SLUG}/member/gifted-articles">View all ${gifted.given_count} given gifts →</a></div>`
            : ''}
            </div>
        </div>`;
    }

    function switchGiftTab(tab, btn) {
        btn.closest('.gifted-section').querySelectorAll('.gifted-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('receivedGifts').style.display = tab === 'received' ? 'block' : 'none';
        document.getElementById('givenGifts').style.display = tab === 'given' ? 'block' : 'none';
    }

    /* ── Newsletter Preferences ───────────────────────────────── */

    function renderNewsletters(newsletters) {
        const el = document.getElementById('section-newsletters');
        if (!newsletters.length) return;

        const items = newsletters.map(n => {
            const statusClass = n.is_active ? 'active' : (n.can_toggle ? 'inactive' : 'locked');
            const statusText = n.is_active ? '✓ Subscribed' : (n.can_toggle ? '✗ Unsubscribed' : '🔒 Locked');

            const action = !n.can_toggle && !n.is_active
                ? `<div class="locked-message">${escHtml(n.lock_reason)}.
                <a href="/${SITE_SLUG}/subscriptions" style="color:#667eea;font-weight:600;">Upgrade plan</a></div>`
                : `<button class="btn-toggle ${n.is_active ? 'unsubscribe' : 'subscribe'}"
                       onclick="toggleNewsletter(${n.subscription_id}, ${n.newsletter_id}, ${!n.is_active}, this)"
                       ${!n.can_toggle ? 'disabled' : ''}>
                   ${n.is_active ? 'Unsubscribe' : 'Subscribe'}
               </button>`;

            return `
            <div class="newsletter-item">
                <div class="newsletter-info">
                    <div class="newsletter-name">${escHtml(n.title)}</div>
                    <div class="newsletter-meta">
                        <span class="newsletter-status ${statusClass}">${statusText}</span>
                        <span>${escHtml(n.interval.charAt(0).toUpperCase() + n.interval.slice(1))} newsletter</span>
                    </div>
                </div>
                <div class="newsletter-actions">${action}</div>
            </div>`;
        }).join('');

        el.innerHTML = `
        <div class="newsletter-prefs-section">
            <div class="section-header">
                <h2 class="section-title">
                    📧 Newsletter Preferences
                    <span class="newsletter-count">${newsletters.length}</span>
                </h2>
            </div>
            <div id="newsletterMessages"></div>
            <div class="newsletter-list">${items}</div>
        </div>`;
    }

    async function toggleNewsletter(subscriptionId, newsletterId, subscribe, button) {
        const originalText = button.textContent.trim();
        button.disabled = true;
        button.textContent = subscribe ? 'Subscribing...' : 'Unsubscribing...';
        const msgEl = document.getElementById('newsletterMessages');

        try {
            const res = await fetch(`/${SITE_SLUG}/member/newsletters/toggle`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({subscription_id: subscriptionId, newsletter_id: newsletterId, subscribe}),
            });
            const result = await res.json();

            if (!result.success) throw new Error(result.message || 'Failed to update');

            msgEl.innerHTML = `<div class="success-message" style="display:block;">✓ ${escHtml(result.message)}</div>`;
            button.textContent = subscribe ? 'Unsubscribe' : 'Subscribe';
            button.classList.toggle('subscribe');
            button.classList.toggle('unsubscribe');

            const badge = button.closest('.newsletter-item').querySelector('.newsletter-status');
            badge.className = `newsletter-status ${subscribe ? 'active' : 'inactive'}`;
            badge.textContent = subscribe ? '✓ Subscribed' : '✗ Unsubscribed';

            setTimeout(() => msgEl.innerHTML = '', 3000);
        } catch (err) {
            msgEl.innerHTML = `<div class="error-message" style="display:block;">⚠ ${escHtml(err.message)}</div>`;
            button.textContent = originalText;
            button.disabled = false;
            setTimeout(() => msgEl.innerHTML = '', 5000);
        }

        button.disabled = false;
    }

    /* ── Rewards ──────────────────────────────────────────────── */

    function renderRewards(rewards) {
        const el = document.getElementById('section-rewards');
        if (!rewards.length) return;

        const cards = rewards.map(r => {
            let valueHtml = '';
            if (r.reward_data) {
                if (r.reward_data.voucher_code) {
                    valueHtml = `<div class="reward-value-label">Voucher Value</div>
                    <div class="reward-value">${escHtml(r.reward_data.currency)} ${parseFloat(r.reward_data.value).toFixed(2)}</div>`;
                } else if (r.reward_data.discount_value) {
                    const prefix = r.reward_data.discount_type === 'percentage' ? '' : '$';
                    const suffix = r.reward_data.discount_type === 'percentage' ? '%' : '';
                    valueHtml = `<div class="reward-value-label">Discount</div>
                    <div class="reward-value">${prefix}${escHtml(String(r.reward_data.discount_value))}${suffix} OFF</div>`;
                } else if (r.reward_data.points) {
                    valueHtml = `<div class="reward-value-label">Points</div>
                    <div class="reward-value">${escHtml(String(r.reward_data.points))} points</div>`;
                }
            }

            return `
            <div class="reward-card">
                <div class="reward-card-header">
                    <div>
                        <h3 class="reward-name">${escHtml(r.name)}</h3>
                        <div class="reward-type">${escHtml(r.type.charAt(0).toUpperCase() + r.type.slice(1))}</div>
                    </div>
                </div>
                <p class="reward-description">${escHtml(r.description)}</p>
                ${valueHtml ? `<div class="reward-value-box">${valueHtml}</div>` : ''}
                <div class="reward-actions">
                    <button class="btn-claim" onclick="claimReward(${r.id})">Claim Reward</button>
                </div>
            </div>`;
        }).join('');

        el.innerHTML = `
        <div class="rewards-section">
            <div class="rewards-content">
                <div class="rewards-header">
                    <span class="rewards-icon">🎁</span>
                    <div>
                        <h2 class="rewards-title">Unclaimed Rewards</h2>
                        <span class="rewards-count">${rewards.length} reward${rewards.length > 1 ? 's' : ''} available</span>
                    </div>
                </div>
                <div class="rewards-grid">${cards}</div>
                <a href="/${SITE_SLUG}/member/rewards" class="btn-view-all">View All Rewards</a>
            </div>
        </div>`;
    }

    /* ── Recommended Products ─────────────────────────────────── */

    function renderRecommendedProducts(products) {
        const el = document.getElementById('section-products');
        if (!products.length) return;

        const cards = products.map(p => `
        <a href="/${SITE_SLUG}/shop/details/${escHtml(p.slug)}" class="product-card">
            <div class="product-image-container">
                ${p.image
            ? `<img src="${escHtml(p.image)}" alt="${escHtml(p.name)}" class="product-image" loading="lazy">`
            : `<div class="product-image" style="display:flex;align-items:center;justify-content:center;background:#e5e7eb;color:#9ca3af;">📦</div>`
        }
                ${p.has_discount ? `<div class="product-badge">-${escHtml(String(p.discount_percentage))}%</div>` : ''}
            </div>
            <div class="product-content">
                <h3 class="product-title">${escHtml(p.name)}</h3>
                ${p.description ? `<p class="product-description">${escHtml(p.description)}</p>` : ''}
                <div class="product-footer">
                    <div class="product-price">
                        <span class="price-current">$${parseFloat(p.has_discount ? p.sale_price : p.price).toFixed(2)}</span>
                        ${p.has_discount ? `<span class="price-original">$${parseFloat(p.price).toFixed(2)}</span>` : ''}
                    </div>
                    <button class="product-cta"
                            onclick="event.preventDefault(); window.location.href='/${SITE_SLUG}/shop/details/${escHtml(p.slug)}'">
                        Buy Now →
                    </button>
                </div>
            </div>
        </a>`).join('');

        el.innerHTML = `
        <div class="products-section">
            <h2 class="section-title">Recommended for You</h2>
            <p style="color:#6b7280;margin-bottom:1rem;">Curated products based on your interests</p>
            <div class="products-grid">${cards}</div>
        </div>`;
    }

    /* ── Subscription Listing ─────────────────────────────────── */

    function renderSubscriptions(grouped) {
        const el = document.getElementById('section-subscriptions');
        const allActive = [...(grouped.active?.print ?? []), ...(grouped.active?.digital ?? [])];
        const allExpired = [...(grouped.expired?.print ?? []), ...(grouped.expired?.digital ?? [])];

        if (!allActive.length && !allExpired.length) return;

        const subCard = (s, isExpired = false) => `
        <div class="subscription-card ${isExpired ? 'expired' : ''}">
            <div class="subscription-header">
                <div class="subscription-title">
                    <div class="subscription-icon ${s.type === 'print' ? 'icon-print' : 'icon-digital'}">
                        ${s.type === 'print' ? '📦' : '💻'}
                    </div>
                    <div class="subscription-name">
                        <h3>${escHtml(s.plan_name)}</h3>
                        <div class="subscription-type">${escHtml(s.type.charAt(0).toUpperCase() + s.type.slice(1))} Subscription</div>
                    </div>
                </div>
                <span class="subscription-status status-${escHtml(s.status)}">${escHtml(s.status.charAt(0).toUpperCase() + s.status.slice(1))}</span>
            </div>
            <div class="subscription-details">
                <div class="detail-item">
                    <span class="detail-label">Start Date</span>
                    <span class="detail-value">${formatDate(s.start_date)}</span>
                </div>
                ${s.end_date ? `<div class="detail-item">
                    <span class="detail-label">${isExpired ? 'Ended' : 'End Date'}</span>
                    <span class="detail-value">${formatDate(s.end_date)}</span>
                </div>` : ''}
                ${!isExpired && s.next_billing_date ? `<div class="detail-item">
                    <span class="detail-label">Next Billing</span>
                    <span class="detail-value">${formatDate(s.next_billing_date)}</span>
                </div>` : ''}
            </div>
            ${!isExpired && s.auto_renew ? `<div class="auto-renew-badge"><span>🔄</span> Auto-Renew Enabled</div>` : ''}
            ${s.newsletters?.length ? `
                <div class="subscription-newsletters">
                    <div class="newsletters-label">Included Newsletters:</div>
                    <div class="newsletter-tags">
                        ${s.newsletters.map(n => `<span class="newsletter-tag">${escHtml(n.title)}</span>`).join('')}
                    </div>
                </div>` : ''}
            <div class="subscription-actions">
                ${!isExpired && s.archive_url ? `<a href="${escHtml(s.archive_url)}" class="btn btn-secondary">📚 View Archive</a>` : ''}
                ${(!isExpired && s.should_show_renew) || (isExpired && s.can_renew)
            ? `<a href="/${SITE_SLUG}/member/subscriptions/${s.id}/renew" class="btn btn-primary">🔄 ${isExpired ? 'Renew Subscription' : 'Renew Now'}</a>`
            : ''}
            </div>
        </div>`;

        const activeHtml = allActive.length
            ? allActive.map(s => subCard(s, false)).join('')
            : `<div class="empty-state"><div class="empty-state-icon">💤</div><h3>No Active Subscriptions</h3><p>Your subscriptions have expired or been cancelled.</p></div>`;

        const expiredHtml = allExpired.length
            ? allExpired.map(s => subCard(s, true)).join('')
            : '';

        el.innerHTML = `
        <div class="subscriptions-section">
            <div class="subscriptions-header">
                <h2>📰 My Subscriptions</h2>
                <a href="/${SITE_SLUG}/member/subscriptions" class="btn btn-secondary">View All Subscriptions →</a>
            </div>
            <div class="subscription-tabs">
                <button class="tab-button active" onclick="switchSubscriptionTab('active')" id="activeTab">
                    Active Subscriptions
                </button>
                ${allExpired.length ? `<button class="tab-button" onclick="switchSubscriptionTab('expired')" id="expiredTab">Expired Subscriptions</button>` : ''}
            </div>
            <div id="activeSubscriptions" class="subscription-grid">${activeHtml}</div>
            ${allExpired.length ? `<div id="expiredSubscriptions" class="subscription-grid" style="display:none;">${expiredHtml}</div>` : ''}
        </div>`;
    }

    function switchSubscriptionTab(tab) {
        document.getElementById('activeSubscriptions').style.display = tab === 'active' ? 'grid' : 'none';
        const expired = document.getElementById('expiredSubscriptions');
        if (expired) expired.style.display = tab === 'expired' ? 'grid' : 'none';
        document.getElementById('activeTab').classList.toggle('active', tab === 'active');
        const expiredTab = document.getElementById('expiredTab');
        if (expiredTab) expiredTab.classList.toggle('active', tab === 'expired');
    }

    function renderDashboard(data) {
        const root = document.getElementById('dashboard-content');
        const loading = document.getElementById('dashboard-loading');
        const verified = data.member.email_verified_at !== null;

        root.innerHTML = verified
            ? renderVerifiedDashboard(data)
            : renderUnverifiedDashboard(data);

        loading.style.display = 'none';
        root.style.display = 'block';

        // Re-attach gift modal outside-click listener after innerHTML swap
        document.getElementById('giftModal')?.addEventListener('click', e => {
            if (e.target.id === 'giftModal') closeGiftModal();
        });
    }

    /* ── Verified dashboard ──────────────────────────────────── */

    function renderVerifiedDashboard(data) {
        const {member, stats, recent_orders, all_subscriptions} = data;

        const navCards = [
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
                title: 'My Favorites',
                desc: 'View your saved favorite items.'
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
        ].map(c => `
            <a href="/${SITE_SLUG}/member/${c.href}" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon ${c.cls}">${c.icon}</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>${c.title}</h3>
                    <p>${c.desc}</p>
                </div>
            </a>`).join('');

        const statsHtml = `
            <div class="stats-grid">
                ${[
            ['orders', 'Total Orders'],
            ['newsletters', 'Newsletters'],
            ['subscriptions', 'Active Subscriptions'],
            ['comments', 'Comments Posted'],
            ['pages_read', 'Pages Read'],
            ['likes', 'Pages Liked'],
        ].map(([key, label]) => `
                    <div class="stat-card">
                        <div class="stat-number">${stats[key] ?? 0}</div>
                        <div class="stat-label">${label}</div>
                    </div>`).join('')}
            </div>`;

        const activitySection = buildActivityTables(recent_orders, all_subscriptions);

        return `
            <div class="welcome-section">
                <h1>Welcome back, ${escHtml(member.first_name ?? 'Member')}!</h1>
                <p>Manage your account, track your orders, and explore exclusive content.</p>
            </div>

            <h2 class="section-title">Quick Access</h2>
            <div class="dashboard-grid">${navCards}</div>

            <h2 class="section-title">Recent Activity</h2>
            <div class="dashboard-grid">${activitySection}</div>

            <h2 class="section-title">Your Activity</h2>
            ${statsHtml}

            ${giftModalHtml()}`;
    }

    /* ── Unverified dashboard ────────────────────────────────── */
    /*
     * Mirrors the old dashboard-old.php unverified branch exactly:
     *  1. Verification banner with resend button
     *  2. "Your Account Overview" section — profile info-card, and
     *     conditional order / subscription info-cards when counts > 0
     *  3. "Available After Verification" grid of disabled feature cards
     */
    function renderUnverifiedDashboard({member, stats}) {
        /* Info cards — only rendered when counts are non-zero */
        const orderInfoCard = (stats?.orders > 0) ? `
            <div class="info-card">
                <h3><span>🛍️</span> Your Orders</h3>
                <p>You have <strong>${stats.orders}</strong> order${stats.orders !== 1 ? 's' : ''}.
                   Verify your email to view order details and tracking information.</p>
            </div>` : '';

        const subInfoCard = (stats?.subscriptions > 0) ? `
            <div class="info-card">
                <h3><span>⭐</span> Your Subscriptions</h3>
                <p>You have <strong>${stats.subscriptions}</strong> active
                   subscription${stats.subscriptions !== 1 ? 's' : ''}.
                   Verify your email to manage your subscriptions.</p>
            </div>` : '';

        const disabledCards = [
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
        ].map(c => `
            <div class="dashboard-card disabled">
                <div class="card-header">
                    <div class="card-icon ${c.cls}">${c.icon}</div>
                    <div class="card-arrow">→</div>
                </div>
                <div class="card-content">
                    <h3>${c.title}</h3>
                    <p>${c.desc}</p>
                </div>
            </div>`).join('');

        return `
            <div class="verification-banner">
                <h2><span>⚠️</span> Email Verification Required</h2>
                <p>Welcome! Please verify your email address to unlock your full account and access all
                   features. We've sent a verification link to
                   <strong>${escHtml(member.email)}</strong>.</p>
                <div class="verification-actions">
                    <button class="btn-resend" id="resendBtn" onclick="resendVerification()">
                        <span>📧</span> Resend Verification Email
                    </button>
                </div>
            </div>

            <div class="limited-access-section">
                <h2>Your Account Overview</h2>
                <div class="limited-access-grid">
                    <div class="info-card">
                        <h3><span>👤</span> Profile Information</h3>
                        <p>
                            <strong>Name:</strong> ${escHtml((member.first_name ?? '') + ' ' + (member.last_name ?? ''))}<br>
                            <strong>Email:</strong> ${escHtml(member.email)}<br>
                            <strong>Member Since:</strong> ${formatDate(member.created_at)}
                        </p>
                    </div>
                    ${orderInfoCard}
                    ${subInfoCard}
                </div>
            </div>

            <h2 class="section-title">Available After Verification</h2>
            <div class="dashboard-grid">${disabledCards}</div>`;
    }

    /* ── Activity tables (orders + subscriptions tabs) ───────── */

    function buildActivityTables(recentOrders, allSubscriptions) {
        if (!recentOrders?.length && !allSubscriptions?.length) return '';

        const orderRows = (recentOrders ?? []).map(o => `
            <tr style="border-bottom:1px solid var(--border-color);">
                <td style="padding:.75rem;">${formatDate(o.created_at)}</td>
                <td style="padding:.75rem;font-weight:600;">#${escHtml(o.order_number)}</td>
                <td style="padding:.75rem;">${o.one_time_subscription_id ? '📋 Subscription' : '🛍️ Order'}</td>
                <td style="padding:.75rem;font-weight:600;">${escHtml(o.currency)} ${parseFloat(o.total).toFixed(2)}</td>
                <td style="padding:.75rem;">${statusBadge(o.status)}</td>
                <td style="padding:.75rem;">
                    <a href="/${SITE_SLUG}/member/orders/${o.id}"
                       style="color:var(--primary-color);text-decoration:none;font-weight:600;">View →</a>
                </td>
            </tr>`).join('');

        const subsRows = (allSubscriptions ?? []).map(s => `
            <tr style="border-bottom:1px solid var(--border-color);">
                <td style="padding:.75rem;">${formatDate(s.created_at)}</td>
                <td style="padding:.75rem;font-weight:600;">${escHtml(s.plan_name ?? '')}</td>
                <td style="padding:.75rem;">${statusBadge(s.status)}</td>
                <td style="padding:.75rem;">
                    <a href="/${SITE_SLUG}/member/subscriptions"
                       style="color:var(--primary-color);text-decoration:none;font-weight:600;">Manage →</a>
                </td>
            </tr>`).join('');

        const ordersTable = orderRows
            ? `<table style="width:100%;border-collapse:collapse;">
                <thead><tr style="background:var(--bg-light);">
                    ${['Date', 'Order #', 'Type', 'Total', 'Status', 'Action'].map(h =>
                `<th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">${h}</th>`
            ).join('')}
                </tr></thead>
                <tbody>${orderRows}</tbody>
               </table>
               <div style="margin-top:1rem;text-align:center;">
                   <a href="/${SITE_SLUG}/member/orders"
                      style="color:var(--primary-color);text-decoration:none;font-weight:600;">
                      View All Orders →</a>
               </div>`
            : '<div style="text-align:center;padding:2rem;color:var(--text-secondary);">No orders yet</div>';

        const subsTable = subsRows
            ? `<table style="width:100%;border-collapse:collapse;">
                <thead><tr style="background:var(--bg-light);">
                    ${['Date', 'Plan', 'Status', 'Action'].map(h =>
                `<th style="padding:.75rem;text-align:left;font-size:.875rem;font-weight:600;">${h}</th>`
            ).join('')}
                </tr></thead>
                <tbody>${subsRows}</tbody>
               </table>
               <div style="margin-top:1rem;text-align:center;">
                   <a href="/${SITE_SLUG}/member/subscriptions"
                      style="color:var(--primary-color);text-decoration:none;font-weight:600;">
                      View All Subscriptions →</a>
               </div>`
            : '<div style="text-align:center;padding:2rem;color:var(--text-secondary);">No subscriptions yet</div>';

        return `
            <div style="background:white;border-radius:1rem;padding:2rem;box-shadow:var(--shadow);margin-bottom:2rem;width:100%;grid-column:1/-1;">
                <div style="display:flex;gap:1rem;margin-bottom:1.5rem;border-bottom:2px solid var(--border-color);">
                    <button onclick="switchTab('orders')" id="ordersTab"
                        style="padding:1rem;background:none;border:none;font-weight:600;cursor:pointer;
                               border-bottom:3px solid var(--primary-color);margin-bottom:-2px;color:var(--text-primary);">
                        Orders (${recentOrders?.length ?? 0})
                    </button>
                    <button onclick="switchTab('subscriptions')" id="subscriptionsTab"
                        style="padding:1rem;background:none;border:none;font-weight:600;cursor:pointer;
                               color:var(--text-secondary);">
                        Subscriptions (${allSubscriptions?.length ?? 0})
                    </button>
                </div>
                <div id="ordersContent"       style="overflow-x:auto;">${ordersTable}</div>
                <div id="subscriptionsContent" style="overflow-x:auto;display:none;">${subsTable}</div>
            </div>`;
    }

    /* ── Gift modal HTML ─────────────────────────────────────── */

    function giftModalHtml() {
        return `
            <div id="giftModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
                background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center;">
                <div style="background:white;border-radius:12px;max-width:600px;width:90%;
                    max-height:90vh;overflow-y:auto;position:relative;">
                    <div style="padding:30px;">
                        <button onclick="closeGiftModal()"
                            style="position:absolute;top:15px;right:15px;background:none;border:none;
                                   font-size:28px;cursor:pointer;color:#666;">&times;</button>
                        <h2 style="margin-bottom:20px;color:#2c3e50;">🎁 Gift This Article</h2>
                        <div id="giftModalContent"></div>
                    </div>
                </div>
            </div>`;
    }

    /* ── Tab switching ───────────────────────────────────────── */

    function switchTab(tab) {
        document.getElementById('ordersContent').style.display = tab === 'orders' ? 'block' : 'none';
        document.getElementById('subscriptionsContent').style.display = tab === 'subscriptions' ? 'block' : 'none';
        ['orders', 'subscriptions'].forEach(t => {
            const el = document.getElementById(t + 'Tab');
            if (!el) return;
            el.style.borderBottom = t === tab ? '3px solid var(--primary-color)' : 'none';
            el.style.color = t === tab ? 'var(--text-primary)' : 'var(--text-secondary)';
        });
    }

    /* ── Shared helpers ──────────────────────────────────────── */

    function statusBadge(status) {
        const map = {
            completed: ['#d1fae5', '#065f46'],
            active: ['#d1fae5', '#065f46'],
            pending: ['#fef3c7', '#92400e'],
            expired: ['#fee2e2', '#991b1b'],
        };
        const [bg, fg] = map[status] ?? ['#f3f4f6', '#4b5563'];
        return `<span style="padding:.375rem .75rem;border-radius:.5rem;font-size:.875rem;
                             font-weight:600;background:${bg};color:${fg};">${escHtml(status)}</span>`;
    }

    function formatDate(str) {
        if (!str) return '';
        return new Date(str).toLocaleDateString('en-GB', {year: 'numeric', month: 'short', day: 'numeric'});
    }

    function escHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ── Actions ─────────────────────────────────────────────── */

    async function resendVerification() {
        const btn = document.getElementById('resendBtn');
        if (!btn) return;
        btn.disabled = true;
        btn.innerHTML = '<span>⏳</span> Sending...';
        try {
            const res = await fetch(`/${SITE_SLUG}/member/resend-verification`, {
                method: 'POST', headers: {'Content-Type': 'application/json'}
            });
            const result = await res.json();
            if (result.success) {
                btn.innerHTML = '<span>✓</span> Email Sent!';
                btn.style.background = 'linear-gradient(135deg,var(--success-color),#059669)';
                setTimeout(() => {
                    btn.innerHTML = '<span>📧</span> Resend Verification Email';
                    btn.style.background = '';
                    btn.disabled = false;
                }, 3000);
            } else {
                alert(result.message || 'Failed to send email. Please try again.');
                btn.innerHTML = '<span>📧</span> Resend Verification Email';
                btn.disabled = false;
            }
        } catch {
            alert('An error occurred.');
            btn.innerHTML = '<span>📧</span> Resend Verification Email';
            btn.disabled = false;
        }
    }

    async function claimReward(rewardId) {
        try {
            const res = await fetch(`/${SITE_SLUG}/member/rewards/${rewardId}/claim`, {
                method: 'POST', headers: {'Content-Type': 'application/json'}
            });
            const result = await res.json();
            if (result.success) {
                // Remove the claimed reward card from the DOM
                const btn = document.querySelector(`button[onclick="claimReward(${rewardId})"]`);
                const card = btn?.closest('.reward-card');
                card?.remove();

                // If no rewards left, remove the whole section
                if (!document.querySelectorAll('.reward-card').length) {
                    document.getElementById('section-rewards').innerHTML = '';
                }
            } else {
                alert(result.message);
            }
        } catch {
            alert('An error occurred. Please try again.');
        }
    }

    /* ── Gift modal ──────────────────────────────────────────── */

    let currentGiftPage = null;

    async function openGiftModal(pageSlug, pageTitle) {
        const modal = document.getElementById('giftModal');
        const content = document.getElementById('giftModalContent');
        if (!modal || !content) return;
        content.innerHTML = '<div style="text-align:center;padding:40px;"><p>Loading...</p></div>';
        modal.style.display = 'flex';
        try {
            const res = await fetch(`/${SITE_SLUG}/member/gift-modal/${pageSlug}`);
            const data = await res.json();
            if (data.success) {
                currentGiftPage = data.data.page;
                renderGiftModalContent(data.data, pageTitle, content);
            } else {
                content.innerHTML = `
                    <div style="color:#721c24;">${escHtml(data.message ?? 'Failed to load gift form')}</div>
                    <button onclick="closeGiftModal()" style="margin-top:1rem;width:100%;">Close</button>`;
            }
        } catch {
            content.innerHTML = `
                <div style="color:#721c24;">An error occurred.</div>
                <button onclick="closeGiftModal()" style="margin-top:1rem;width:100%;">Close</button>`;
        }
    }

    function renderGiftModalContent({allowance}, pageTitle, container) {
        const limitHtml = !allowance.can_gift
            ? `<div style="background:#f8d7da;border-left:4px solid #dc3545;padding:15px;border-radius:4px;margin-bottom:20px;color:#721c24;">
                <strong>Gift limit reached!</strong> You've used all ${allowance.annual_limit} of your annual article gifts.</div>`
            : allowance.remaining_gifts <= 2
                ? `<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:15px;border-radius:4px;margin-bottom:20px;color:#856404;">
                <strong>Almost there!</strong> You have ${allowance.remaining_gifts} gift${allowance.remaining_gifts !== 1 ? 's' : ''} remaining this year.</div>`
                : `<div style="background:#e8f5e9;padding:15px;border-radius:4px;margin-bottom:20px;">
                You have <strong>${allowance.remaining_gifts}</strong> gifts remaining out of ${allowance.annual_limit} this year.</div>`;

        container.innerHTML = `
            <div style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px;">
                <div style="font-weight:600;color:#2c3e50;margin-bottom:5px;">${escHtml(pageTitle)}</div>
                <div style="font-size:14px;color:#666;">Share this article with someone special</div>
            </div>
            ${limitHtml}
            <div id="giftModalMessages"></div>
            ${allowance.can_gift ? `
            <form id="modalGiftForm">
                <div style="margin-bottom:20px;">
                    <label style="display:block;margin-bottom:5px;font-weight:500;color:#555;">Recipient's Email Address *</label>
                    <input type="email" id="modal_recipient_email" required
                        style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box;"
                        placeholder="friend@example.com">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block;margin-bottom:5px;font-weight:500;color:#555;">Personal Message (Optional)</label>
                    <textarea id="modal_personal_message" maxlength="500"
                        style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:14px;
                               box-sizing:border-box;resize:vertical;min-height:100px;font-family:inherit;"
                        placeholder="Add a personal note..."></textarea>
                    <div style="font-size:12px;color:#666;margin-top:5px;"><span id="modal_charCount">0</span>/500</div>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" style="flex:1;padding:.75rem;background:var(--primary-color);color:white;border:none;border-radius:.5rem;font-weight:600;cursor:pointer;">Send Gift</button>
                    <button type="button" onclick="closeGiftModal()" style="flex:1;padding:.75rem;background:white;color:var(--text-primary);border:2px solid var(--border-color);border-radius:.5rem;font-weight:600;cursor:pointer;">Cancel</button>
                </div>
            </form>` : `<button onclick="closeGiftModal()" style="width:100%;padding:.75rem;background:white;color:var(--text-primary);border:2px solid var(--border-color);border-radius:.5rem;font-weight:600;cursor:pointer;">Close</button>`}`;

        if (allowance.can_gift) {
            const textarea = document.getElementById('modal_personal_message');
            const charCount = document.getElementById('modal_charCount');
            textarea.addEventListener('input', () => {
                charCount.textContent = textarea.value.length;
            });
            document.getElementById('modalGiftForm').addEventListener('submit', handleModalGiftSubmit);
        }
    }

    async function handleModalGiftSubmit(e) {
        e.preventDefault();
        const email = document.getElementById('modal_recipient_email').value.trim();
        const message = document.getElementById('modal_personal_message').value.trim();
        const submitBtn = e.target.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        try {
            const res = await fetch(`/${SITE_SLUG}/gift-article/${currentGiftPage.slug}`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({recipient_email: email, personal_message: message})
            });
            const data = await res.json();
            if (data.data?.success) {
                document.getElementById('giftModalMessages').innerHTML =
                    `<div style="background:#d4edda;color:#155724;padding:15px;border-radius:4px;margin-bottom:20px;">✓ ${escHtml(data.data.message)}</div>`;
                e.target.style.display = 'none';
                setTimeout(() => closeGiftModal(), 3000);
            } else {
                throw new Error(data.data?.message || 'Failed to send gift');
            }
        } catch (err) {
            document.getElementById('giftModalMessages').innerHTML =
                `<div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:4px;margin-bottom:20px;">⚠ ${escHtml(err.message)}</div>`;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Gift';
        }
    }

    function closeGiftModal() {
        const modal = document.getElementById('giftModal');
        if (modal) modal.style.display = 'none';
        currentGiftPage = null;
    }

    /* ── Init ─────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', loadDashboard);
</script>
</body>
</html>
