<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'YourStore') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: var(--bg-light);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .site-header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo a {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .main-nav {
            display: flex;
            gap: 2rem;
        }

        .main-nav a {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .main-nav a:hover, .main-nav a.active {
            color: var(--primary-color);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .icon-btn {
            position: relative;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s;
        }

        .icon-btn:hover {
            background-color: var(--bg-light);
        }

        .badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger-color);
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.375rem;
            border-radius: 1rem;
            font-weight: 600;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .breadcrumb {
            display: flex;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        /* Spinner */
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow-lg);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        .toast.show {
            display: flex;
        }

        .toast.success {
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            border-left: 4px solid var(--danger-color);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Footer */
        .site-footer {
            background: white;
            padding: 2rem 0;
            text-align: center;
            color: var(--text-secondary);
            margin-top: 4rem;
            box-shadow: var(--shadow);
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        /* Buttons */
        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            margin-bottom: 1rem;
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--bg-light);
        }

        .btn-primary.processing::after {
            content: '';
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin-left: 0.5rem;
            vertical-align: middle;
        }

        /* Forms */
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
        }

        .form-label .required {
            color: var(--danger-color);
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-error {
            color: var(--danger-color);
            font-size: 0.875rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
        }

        /* Security Badge */
        .security-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 0.5rem;
            margin-top: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .loading-content {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            text-align: center;
        }

        @media (max-width: 640px) {
            .main-nav {
                display: none;
            }

            .page-title {
                font-size: 1.5rem;
            }
        }

        <?= $additionalStyles ?? '' ?>
    </style>
    <?php if (!empty($headAssets)): ?>
        <?= $headAssets ?>
    <?php endif; ?>
</head>
<body>

@include('components/layout/site-header', [
'activeNav' => $activeNav ?? '',
'cartCount' => $count ?? 0,
'wishlistCount'=> $wishlistCount ?? 0,
])

<?php if (!empty($pageTitle) && !empty($breadcrumbs)): ?>
    @include('components/layout/page-header', [
    'title'       => $pageTitle,
    'breadcrumbs' => $breadcrumbs,
    ])
<?php endif; ?>

<main>
    <div class="container">
        @yield('content')
    </div>
</main>

<div id="toast" class="toast"></div>

@yield('scripts')

<script>
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function formatCurrency(amount, symbol = '$') {
        return symbol + parseFloat(amount).toFixed(2);
    }

    function showAlert(message, type = 'success') {
        const html = `<div class="alert alert-${type}">${message}</div>`;
        document.getElementById('alert-container').innerHTML = html;
        window.scrollTo({top: 0, behavior: 'smooth'});
        setTimeout(() => {
            document.getElementById('alert-container').innerHTML = '';
        }, 5000);
    }
</script>
</body>
</html>