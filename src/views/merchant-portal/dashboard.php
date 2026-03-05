<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@400;500&family=Instrument+Sans:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        :root {
            --ink: #0f0e0d;
            --ink-2: #3a3733;
            --ink-3: #6b6762;
            --ink-4: #a09c98;
            --paper: #f5f3ef;
            --paper-2: #ede9e3;
            --paper-3: #e2ddd5;
            --white: #ffffff;
            --accent: #c8492a;
            --accent-light: #f0d5ce;
            --accent-dark: #9e3620;
            --green: #2a7a4b;
            --green-light: #d0ead9;
            --amber: #b86e0a;
            --amber-light: #fde8c0;
            --blue: #1f4e8c;
            --blue-light: #d1dff5;
            --border: #d9d4cc;
            --shadow-sm: 0 1px 3px rgba(15, 14, 13, .08), 0 1px 2px rgba(15, 14, 13, .06);
            --shadow-md: 0 4px 12px rgba(15, 14, 13, .1), 0 2px 6px rgba(15, 14, 13, .07);
            --shadow-lg: 0 12px 32px rgba(15, 14, 13, .13), 0 4px 10px rgba(15, 14, 13, .08);
            --radius: 4px;
            --radius-md: 8px;
            --radius-lg: 14px;
            --sidebar-w: 228px;
            --header-h: 56px;
            --font-serif: 'DM Serif Display', Georgia, serif;
            --font-sans: 'Instrument Sans', system-ui, sans-serif;
            --font-mono: 'DM Mono', 'Courier New', monospace;
            --transition: 180ms cubic-bezier(.4, 0, .2, 1);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 15px;
            -webkit-font-smoothing: antialiased;
        }

        body {
            font-family: var(--font-sans);
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* ─── SIDEBAR ─────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--ink);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: relative;
            z-index: 100;
        }

        .sidebar::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 1px;
            background: linear-gradient(to bottom, transparent, rgba(255, 255, 255, .06) 30%, rgba(255, 255, 255, .06) 70%, transparent);
        }

        .sidebar-brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
        }

        .brand-mark {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: var(--accent);
            border-radius: 6px;
            display: grid;
            place-items: center;
            font-family: var(--font-serif);
            font-size: 16px;
            color: white;
            font-style: italic;
            letter-spacing: -1px;
            flex-shrink: 0;
        }

        .brand-name {
            font-family: var(--font-serif);
            font-size: 17px;
            color: rgba(255, 255, 255, .92);
            letter-spacing: -.3px;
            line-height: 1.1;
        }

        .brand-sub {
            font-size: 10px;
            color: rgba(255, 255, 255, .3);
            font-family: var(--font-mono);
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .merchant-tag {
            margin: 12px 20px;
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: var(--radius);
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .merchant-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #e06a3a);
            display: grid;
            place-items: center;
            font-size: 11px;
            font-weight: 600;
            color: white;
            flex-shrink: 0;
        }

        .merchant-info {
            flex: 1;
            min-width: 0;
        }

        .merchant-name {
            font-size: 12px;
            font-weight: 500;
            color: rgba(255, 255, 255, .8);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .merchant-status {
            font-size: 10px;
            color: #4ade80;
            font-family: var(--font-mono);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .merchant-status::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #4ade80;
        }

        .nav-section {
            padding: 8px 12px 4px;
        }

        .nav-label {
            font-size: 9px;
            font-family: var(--font-mono);
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .22);
            padding: 0 8px;
            margin-bottom: 2px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 10px;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 13px;
            font-weight: 450;
            color: rgba(255, 255, 255, .52);
            transition: background var(--transition), color var(--transition);
            position: relative;
            text-decoration: none;
            margin-bottom: 1px;
            user-select: none;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, .06);
            color: rgba(255, 255, 255, .82);
        }

        .nav-item.active {
            background: rgba(200, 73, 42, .18);
            color: rgba(255, 255, 255, .92);
        }

        .nav-item.active .nav-icon {
            color: var(--accent);
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: -4px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 16px;
            background: var(--accent);
            border-radius: 0 2px 2px 0;
        }

        .nav-icon {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            opacity: .7;
        }

        .nav-item.active .nav-icon {
            opacity: 1;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--accent);
            color: white;
            font-size: 9px;
            font-family: var(--font-mono);
            padding: 1px 5px;
            border-radius: 10px;
            min-width: 16px;
            text-align: center;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 12px;
            border-top: 1px solid rgba(255, 255, 255, .06);
        }

        .balance-card {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: var(--radius-md);
            padding: 12px 14px;
        }

        .balance-label {
            font-size: 10px;
            color: rgba(255, 255, 255, .3);
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 4px;
        }

        .balance-amount {
            font-family: var(--font-mono);
            font-size: 20px;
            color: rgba(255, 255, 255, .9);
            letter-spacing: -.5px;
        }

        .balance-sub {
            font-size: 10px;
            color: rgba(255, 255, 255, .25);
            margin-top: 2px;
        }

        /* ─── MAIN ────────────────────────────────── */
        .main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .topbar {
            height: var(--header-h);
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 28px;
            gap: 16px;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-title {
            font-family: var(--font-serif);
            font-size: 19px;
            color: var(--ink);
            letter-spacing: -.3px;
        }

        .topbar-breadcrumb {
            font-size: 12px;
            color: var(--ink-4);
            font-family: var(--font-mono);
        }

        .topbar-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: var(--radius);
            font-size: 12.5px;
            font-family: var(--font-sans);
            font-weight: 500;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all var(--transition);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .btn-primary:hover {
            background: var(--accent-dark);
            border-color: var(--accent-dark);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--ink-2);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background: var(--paper);
            border-color: var(--ink-4);
        }

        .btn-ghost {
            background: transparent;
            color: var(--ink-3);
            border-color: transparent;
        }

        .btn-ghost:hover {
            background: var(--paper-2);
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 11.5px;
        }

        .btn-danger {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca;
        }

        .btn-danger:hover {
            background: #fee2e2;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 28px;
        }

        /* ─── PANELS ──────────────────────────────── */
        .panel {
            display: none;
        }

        .panel.active {
            display: block;
            animation: fadeIn 200ms ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ─── CARDS / STATS ───────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: box-shadow var(--transition);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .stat-card.accent::before {
            background: var(--accent);
        }

        .stat-card.green::before {
            background: var(--green);
        }

        .stat-card.amber::before {
            background: var(--amber);
        }

        .stat-card.blue::before {
            background: var(--blue);
        }

        .stat-label {
            font-size: 10.5px;
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--ink-3);
            margin-bottom: 8px;
        }

        .stat-value {
            font-family: var(--font-serif);
            font-size: 28px;
            color: var(--ink);
            letter-spacing: -.5px;
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-delta {
            font-size: 11px;
            font-family: var(--font-mono);
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .stat-delta.up {
            color: var(--green);
        }

        .stat-delta.down {
            color: var(--accent);
        }

        .stat-icon {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: grid;
            place-items: center;
            font-size: 15px;
        }

        .stat-icon.accent-bg {
            background: var(--accent-light);
        }

        .stat-icon.green-bg {
            background: var(--green-light);
        }

        .stat-icon.amber-bg {
            background: var(--amber-light);
        }

        .stat-icon.blue-bg {
            background: var(--blue-light);
        }

        /* ─── CARD ────────────────────────────────── */
        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
        }

        .card-sub {
            font-size: 12px;
            color: var(--ink-4);
            font-family: var(--font-mono);
        }

        .card-actions {
            margin-left: auto;
            display: flex;
            gap: 6px;
        }

        .card-body {
            padding: 20px;
        }

        /* ─── TABLE ───────────────────────────────── */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            text-align: left;
            padding: 10px 16px;
            font-size: 10.5px;
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--ink-4);
            background: var(--paper);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--paper-2);
            transition: background var(--transition);
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover {
            background: var(--paper);
        }

        tbody td {
            padding: 12px 16px;
            color: var(--ink-2);
            vertical-align: middle;
        }

        /* ─── BADGE ───────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10.5px;
            font-family: var(--font-mono);
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-green {
            background: var(--green-light);
            color: var(--green);
        }

        .badge-amber {
            background: var(--amber-light);
            color: var(--amber);
        }

        .badge-red {
            background: var(--accent-light);
            color: var(--accent);
        }

        .badge-blue {
            background: var(--blue-light);
            color: var(--blue);
        }

        .badge-gray {
            background: var(--paper-3);
            color: var(--ink-3);
        }

        /* ─── FORM ────────────────────────────────── */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--ink-2);
            margin-bottom: 5px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 13px;
            font-family: var(--font-sans);
            color: var(--ink);
            background: var(--white);
            transition: border-color var(--transition), box-shadow var(--transition);
            outline: none;
        }

        .form-control:focus {
            border-color: var(--ink-3);
            box-shadow: 0 0 0 3px rgba(15, 14, 13, .06);
        }

        .form-control::placeholder {
            color: var(--ink-4);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 90px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-hint {
            font-size: 11px;
            color: var(--ink-4);
            margin-top: 4px;
            font-family: var(--font-mono);
        }

        /* ─── GRID LAYOUTS ────────────────────────── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .three-col {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        /* ─── TABS ────────────────────────────────── */
        .tab-bar {
            display: flex;
            gap: 2px;
            background: var(--paper-2);
            border-radius: var(--radius-md);
            padding: 3px;
            margin-bottom: 20px;
            width: fit-content;
        }

        .tab-btn {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--ink-3);
            transition: all var(--transition);
            font-family: var(--font-sans);
        }

        .tab-btn.active {
            background: var(--white);
            color: var(--ink);
            box-shadow: var(--shadow-sm);
        }

        /* ─── BOOST PANEL ─────────────────────────── */
        .boost-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .boost-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 18px;
            cursor: pointer;
            transition: all var(--transition);
            position: relative;
        }

        .boost-card:hover {
            border-color: var(--accent);
            box-shadow: var(--shadow-md);
        }

        .boost-card.selected {
            border-color: var(--accent);
            background: #fff8f7;
        }

        .boost-card-label {
            font-size: 11px;
            font-family: var(--font-mono);
            color: var(--ink-3);
            margin-bottom: 4px;
        }

        .boost-card-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .boost-card-desc {
            font-size: 12px;
            color: var(--ink-3);
            line-height: 1.5;
        }

        .boost-card-price {
            margin-top: 12px;
            font-family: var(--font-mono);
            font-size: 18px;
            color: var(--accent);
            font-weight: 500;
        }

        .boost-card-per {
            font-size: 10px;
            color: var(--ink-4);
        }

        .selected-check {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--accent);
            display: none;
            place-items: center;
        }

        .boost-card.selected .selected-check {
            display: grid;
        }

        /* Multiplier slider */
        .range-wrap {
            margin: 16px 0;
        }

        .range-labels {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            font-family: var(--font-mono);
            color: var(--ink-4);
            margin-top: 6px;
        }

        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            height: 4px;
            border-radius: 2px;
            background: linear-gradient(to right, var(--accent) 0%, var(--accent) 50%, var(--border) 50%, var(--border) 100%);
            outline: none;
            cursor: pointer;
        }

        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--white);
            border: 2px solid var(--accent);
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }

        /* ─── CHART PLACEHOLDER ───────────────────── */
        .chart-area {
            height: 160px;
            display: flex;
            align-items: flex-end;
            gap: 6px;
            padding: 0 4px;
        }

        .chart-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .chart-bar {
            width: 100%;
            border-radius: 3px 3px 0 0;
            background: var(--accent-light);
            transition: all .3s;
            cursor: pointer;
        }

        .chart-bar:hover {
            background: var(--accent);
        }

        .chart-bar.highlight {
            background: var(--accent);
        }

        .chart-label {
            font-size: 9px;
            font-family: var(--font-mono);
            color: var(--ink-4);
        }

        /* ─── PROGRESS ────────────────────────────── */
        .progress-wrap {
            margin: 6px 0;
        }

        .progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 4px;
        }

        .progress-bar-bg {
            height: 6px;
            background: var(--paper-2);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width .6s cubic-bezier(.4, 0, .2, 1);
        }

        .progress-bar-fill.green {
            background: var(--green);
        }

        .progress-bar-fill.accent {
            background: var(--accent);
        }

        .progress-bar-fill.blue {
            background: var(--blue);
        }

        /* ─── TOGGLE ──────────────────────────────── */
        .toggle {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 20px;
        }

        .toggle input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute;
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: var(--border);
            border-radius: 20px;
            cursor: pointer;
            transition: background var(--transition);
        }

        .toggle-slider::before {
            content: '';
            position: absolute;
            left: 2px;
            top: 2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--white);
            transition: transform var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .toggle input:checked + .toggle-slider {
            background: var(--green);
        }

        .toggle input:checked + .toggle-slider::before {
            transform: translateX(16px);
        }

        /* ─── ALERT ───────────────────────────────── */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            border-left: 3px solid;
            margin-bottom: 16px;
        }

        .alert-success {
            background: var(--green-light);
            border-color: var(--green);
            color: var(--green);
        }

        .alert-warning {
            background: var(--amber-light);
            border-color: var(--amber);
            color: var(--amber);
        }

        .alert-info {
            background: var(--blue-light);
            border-color: var(--blue);
            color: var(--blue);
        }

        /* ─── PRODUCT GRID ────────────────────────── */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }

        .product-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: box-shadow var(--transition);
        }

        .product-card:hover {
            box-shadow: var(--shadow-md);
        }

        .product-img {
            height: 140px;
            background: var(--paper-2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            position: relative;
        }

        .product-badges {
            position: absolute;
            top: 8px;
            left: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .product-info {
            padding: 12px;
        }

        .product-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--ink);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-cat {
            font-size: 10.5px;
            color: var(--ink-4);
            font-family: var(--font-mono);
            margin-bottom: 8px;
        }

        .product-price {
            font-family: var(--font-mono);
            font-size: 15px;
            font-weight: 500;
            color: var(--ink);
        }

        .product-sale {
            color: var(--accent);
        }

        .product-orig {
            font-size: 11px;
            color: var(--ink-4);
            text-decoration: line-through;
        }

        .product-actions {
            display: flex;
            gap: 6px;
            margin-top: 10px;
        }

        /* ─── REVIEW CARD ─────────────────────────── */
        .review-item {
            padding: 16px 0;
            border-bottom: 1px solid var(--paper-2);
        }

        .review-item:last-child {
            border-bottom: none;
        }

        .review-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .review-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--paper-3);
            display: grid;
            place-items: center;
            font-size: 11px;
            font-weight: 600;
            color: var(--ink-3);
        }

        .review-author {
            font-size: 13px;
            font-weight: 500;
        }

        .review-date {
            font-size: 11px;
            color: var(--ink-4);
            font-family: var(--font-mono);
            margin-left: auto;
        }

        .stars {
            color: #f59e0b;
            font-size: 13px;
            letter-spacing: 1px;
        }

        .review-title {
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 3px;
        }

        .review-body {
            font-size: 13px;
            color: var(--ink-3);
            line-height: 1.55;
        }

        .review-product {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: var(--ink-4);
            font-family: var(--font-mono);
            margin-top: 6px;
            background: var(--paper);
            padding: 2px 8px;
            border-radius: 20px;
            border: 1px solid var(--border);
        }

        /* ─── VOUCHER CARD ────────────────────────── */
        .voucher-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            display: flex;
            overflow: hidden;
            margin-bottom: 12px;
            transition: box-shadow var(--transition);
        }

        .voucher-card:hover {
            box-shadow: var(--shadow-sm);
        }

        .voucher-left {
            width: 80px;
            background: var(--ink);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px 8px;
            position: relative;
            flex-shrink: 0;
        }

        .voucher-left::after {
            content: '';
            position: absolute;
            right: -12px;
            top: 0;
            bottom: 0;
            width: 24px;
            background: radial-gradient(circle at left, var(--paper) 8px, transparent 8px);
            background-repeat: repeat-y;
            background-size: 24px 24px;
        }

        .voucher-pct {
            font-family: var(--font-mono);
            font-size: 22px;
            font-weight: 500;
            color: white;
        }

        .voucher-off {
            font-size: 9px;
            color: rgba(255, 255, 255, .4);
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .1em;
        }

        .voucher-right {
            flex: 1;
            padding: 14px 16px 14px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .voucher-code {
            font-family: var(--font-mono);
            font-size: 16px;
            font-weight: 500;
            letter-spacing: 2px;
            color: var(--ink);
        }

        .voucher-meta {
            font-size: 11px;
            color: var(--ink-4);
            margin-top: 2px;
        }

        .voucher-actions {
            margin-left: auto;
            display: flex;
            gap: 6px;
            align-items: center;
        }

        /* ─── COMMISSION ──────────────────────────── */
        .commission-summary {
            display: grid;
            grid-template-columns: 1fr 1px 1fr 1px 1fr;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .commission-block {
            padding: 20px;
        }

        .commission-divider {
            background: var(--border);
        }

        .commission-label {
            font-size: 10px;
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--ink-4);
            margin-bottom: 6px;
        }

        .commission-value {
            font-family: var(--font-serif);
            font-size: 24px;
            letter-spacing: -.3px;
        }

        .commission-note {
            font-size: 11px;
            color: var(--ink-4);
            margin-top: 4px;
        }

        /* ─── OFFER CARD ──────────────────────────── */
        .offer-list-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--paper-2);
        }

        .offer-list-item:last-child {
            border-bottom: none;
        }

        .offer-img {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: var(--paper-2);
            display: grid;
            place-items: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .offer-details {
            flex: 1;
            min-width: 0;
        }

        .offer-name {
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .offer-dates {
            font-size: 11px;
            color: var(--ink-4);
            font-family: var(--font-mono);
            margin-top: 2px;
        }

        .offer-price {
            text-align: right;
        }

        .offer-sale {
            font-family: var(--font-mono);
            font-size: 15px;
            color: var(--accent);
            font-weight: 500;
        }

        .offer-orig {
            font-size: 11px;
            color: var(--ink-4);
            text-decoration: line-through;
            font-family: var(--font-mono);
        }

        /* ─── SCROLLBAR ───────────────────────────── */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--ink-4);
        }

        /* ─── MODAL ───────────────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 14, 13, .5);
            backdrop-filter: blur(4px);
            z-index: 500;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal {
            background: var(--white);
            border-radius: var(--radius-lg);
            width: 520px;
            max-width: 95vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: slideUp 220ms cubic-bezier(.4, 0, .2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-title {
            font-family: var(--font-serif);
            font-size: 18px;
            color: var(--ink);
        }

        .modal-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink-4);
            font-size: 18px;
            line-height: 1;
            padding: 4px;
            border-radius: 4px;
            transition: all var(--transition);
        }

        .modal-close:hover {
            background: var(--paper-2);
            color: var(--ink);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        /* ─── NOTIFICATION ────────────────────────── */
        .notif {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--ink);
            color: rgba(255, 255, 255, .9);
            padding: 12px 18px;
            border-radius: var(--radius-md);
            font-size: 13px;
            box-shadow: var(--shadow-lg);
            transform: translateY(80px);
            opacity: 0;
            transition: all 280ms cubic-bezier(.4, 0, .2, 1);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notif.show {
            transform: translateY(0);
            opacity: 1;
        }

        .notif-icon {
            font-size: 15px;
        }

        /* ─── RESPONSIVE UTILITIES ────────────────── */
        .flex {
            display: flex;
        }

        .flex-col {
            flex-direction: column;
        }

        .items-center {
            align-items: center;
        }

        .gap-2 {
            gap: 8px;
        }

        .gap-3 {
            gap: 12px;
        }

        .ml-auto {
            margin-left: auto;
        }

        .mt-1 {
            margin-top: 4px;
        }

        .mt-2 {
            margin-top: 8px;
        }

        .mt-3 {
            margin-top: 12px;
        }

        .mb-3 {
            margin-bottom: 12px;
        }

        .mb-4 {
            margin-bottom: 16px;
        }

        .mb-6 {
            margin-bottom: 24px;
        }

        .text-sm {
            font-size: 12px;
        }

        .text-xs {
            font-size: 11px;
            color: var(--ink-4);
            font-family: var(--font-mono);
        }

        .font-mono {
            font-family: var(--font-mono);
        }

        .section-title {
            font-family: var(--font-serif);
            font-size: 20px;
            margin-bottom: 16px;
        }

        .inline-flex {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* ─── SEARCH BAR ──────────────────────────── */
        .search-bar {
            display: flex;
            align-items: center;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0 12px;
            gap: 8px;
            width: 240px;
            transition: border-color var(--transition), box-shadow var(--transition);
        }

        .search-bar:focus-within {
            border-color: var(--ink-3);
            box-shadow: 0 0 0 3px rgba(15, 14, 13, .06);
        }

        .search-bar svg {
            color: var(--ink-4);
            flex-shrink: 0;
        }

        .search-bar input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 13px;
            font-family: var(--font-sans);
            color: var(--ink);
            width: 100%;
            padding: 8px 0;
        }

        /* ─── EMPTY STATE ─────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--ink-4);
        }

        .empty-state-icon {
            font-size: 36px;
            margin-bottom: 12px;
        }

        .empty-state-text {
            font-size: 14px;
            color: var(--ink-3);
            margin-bottom: 4px;
        }

        .empty-state-sub {
            font-size: 12px;
            font-family: var(--font-mono);
        }
    </style>
</head>
<body>

<?php
$initials = collect(explode(' ', $merchant->name))
        ->map(fn($w) => strtoupper($w[0] ?? ''))
        ->take(2)
        ->implode('');
$activeBoostCount = $activeBoosts->count();
$pendingOffersCount = $offers->where('status', 'pending')->count();
$pendingReviewCount = ($reviewStats['pending_response'] ?? 0);
?>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">
            <div class="brand-icon">M</div>
            <div>
                <div class="brand-name">Merchant Hub</div>
                <div class="brand-sub">Dashboard v2.4</div>
            </div>
        </div>
    </div>

    <div class="merchant-tag">
        <div class="merchant-avatar">{{ $initials }}</div>
        <div class="merchant-info">
            <div class="merchant-name">{{ $merchant->name }}</div>
            <div class="merchant-status">Active</div>
        </div>
    </div>

    <div class="nav-section">
        <div class="nav-label">Overview</div>
        <a class="nav-item active" data-panel="overview">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="1" y="1" width="6" height="6" rx="1"/>
                <rect x="9" y="1" width="6" height="6" rx="1"/>
                <rect x="1" y="9" width="6" height="6" rx="1"/>
                <rect x="9" y="9" width="6" height="6" rx="1"/>
            </svg>
            Overview
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-label">Products</div>
        <a class="nav-item" data-panel="products">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M2 4l6-2 6 2v8l-6 2-6-2V4z"/>
            </svg>
            Products
            <span class="nav-badge">{{ $products->count() }}</span>
        </a>
        <a class="nav-item" data-panel="offers">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="8" cy="8" r="6"/>
                <path d="M5 8l2 2 4-4"/>
            </svg>
            Offers
            @if($pendingOffersCount > 0)
            <span class="nav-badge">{{ $pendingOffersCount }}</span>
            @endif
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-label">Marketing</div>
        <a class="nav-item" data-panel="boost">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M8 2L10.5 7H14L11 9.5l1 4L8 11l-4 2.5 1-4L2 7h3.5L8 2z"/>
            </svg>
            Boost
            @if($activeBoostCount > 0)
            <span class="nav-badge">{{ $activeBoostCount }}</span>
            @endif
        </a>
        <a class="nav-item" data-panel="vouchers">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="1" y="4" width="14" height="8" rx="1"/>
                <path d="M5 4v8M11 4v8"/>
            </svg>
            Vouchers
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-label">Finance</div>
        <a class="nav-item" data-panel="commission">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="8" cy="8" r="6"/>
                <path d="M8 5v6M6 7h3.5a1 1 0 010 2H6"/>
            </svg>
            Commission
        </a>
        <a class="nav-item" data-panel="invoices">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="1" width="10" height="14" rx="1"/>
                <path d="M6 5h4M6 8h4M6 11h2"/>
            </svg>
            Invoices
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-label">Community</div>
        <a class="nav-item" data-panel="reviews">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M2 2h12v9H9l-3 3V11H2z"/>
            </svg>
            Reviews
            @if($pendingReviewCount > 0)
            <span class="nav-badge" style="background:var(--amber)">{{ $pendingReviewCount }}</span>
            @endif
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="balance-card">
            <div class="balance-label">Available Balance</div>
            <div class="balance-amount">£{{ number_format($commissionSummary['net_earnings'] ?? 0, 0) }}</div>
            <div class="balance-sub">Net earnings this month</div>
        </div>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <header class="topbar">
        <div>
            <div class="topbar-title" id="page-title">Overview</div>
            <div class="topbar-breadcrumb text-xs">{{ $merchant->name }} → <span id="page-breadcrumb">Dashboard</span>
            </div>
        </div>
        <div class="topbar-actions">
            <div class="search-bar">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="7" cy="7" r="5"/>
                    <path d="M12 12l2 2"/>
                </svg>
                <input type="text" placeholder="Search…">
            </div>
            <button class="btn btn-secondary" onclick="openModal('boost-modal')">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 2L10.5 7H14L11 9.5l1 4L8 11l-4 2.5 1-4L2 7h3.5L8 2z"/>
                </svg>
                New Boost
            </button>
            <button class="btn btn-primary" id="primary-action" onclick="openModal('product-modal')">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M8 3v10M3 8h10"/>
                </svg>
                Add Product
            </button>
        </div>
    </header>

    <!-- CONTENT -->
    <div class="content-area">

        <!-- ═══════════════════════════════════════ OVERVIEW ═══ -->
        <div class="panel active" id="panel-overview">
            @if($activeBoostCount > 0)
            <div class="alert alert-info">
                <strong>Active Boosts:</strong> You have {{ $activeBoostCount }} active boost{{ $activeBoostCount !== 1
                ? 's' : '' }} running.
                <a href="#" style="color:inherit;text-decoration:underline" onclick="navigate('boost')">Manage →</a>
            </div>
            @endif

            <div class="stats-grid">
                <div class="stat-card accent">
                    <div class="stat-icon accent-bg">📦</div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">£{{ number_format($stats->totalRevenue, 0) }}</div>
                    <div class="stat-delta {{ $stats->revenueIsUp() ? 'up' : 'down' }}">
                        {{ $stats->revenueIsUp() ? '↑' : '↓' }} {{ abs($stats->revenueDelta) }}% vs last month
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon green-bg">🛒</div>
                    <div class="stat-label">Orders</div>
                    <div class="stat-value">{{ number_format($stats->totalOrders) }}</div>
                    <div class="stat-delta {{ $stats->ordersIsUp() ? 'up' : 'down' }}">
                        {{ $stats->ordersIsUp() ? '↑' : '↓' }} {{ abs($stats->ordersDelta) }}% vs last month
                    </div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-icon amber-bg">👁</div>
                    <div class="stat-label">Impressions</div>
                    <div class="stat-value">{{ number_format($stats->totalImpressions / 1000, 1) }}k</div>
                    <div class="stat-delta {{ $stats->impressionsIsUp() ? 'up' : 'down' }}">
                        {{ $stats->impressionsIsUp() ? '↑' : '↓' }} {{ abs($stats->impressionsDelta) }}% vs last month
                    </div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-icon blue-bg">⭐</div>
                    <div class="stat-label">Avg. Rating</div>
                    <div class="stat-value">{{ $stats->averageRating }}</div>
                    <div class="stat-delta {{ $stats->ratingIsUp() ? 'up' : 'down' }}">
                        {{ $stats->ratingIsUp() ? '↑' : '↓' }} {{ abs($stats->ratingDelta) }} this month
                    </div>
                </div>
            </div>

            <div class="two-col mb-6">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Revenue (30 days)</div>
                            <div class="card-sub">Daily breakdown</div>
                        </div>
                        <div class="card-actions">
                            <div class="tab-bar" style="margin:0">
                                <button class="tab-btn active">30d</button>
                                <button class="tab-btn">90d</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-area" id="revenue-chart"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Active Boosts</div>
                        <span class="nav-badge" style="background:var(--accent)">{{ $activeBoostCount }} live</span>
                        <div class="card-actions">
                            <button class="btn btn-sm btn-secondary" onclick="navigate('boost')">Manage</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if ($activeBoosts->count() > 0):

                            foreach ($activeBoosts as $boost): ?>
                                <div style="margin-bottom:14px">
                                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                        <span style="font-size:13px;font-weight:500">{{ $boost->product->name ?? 'Unknown product' }}</span>
                                        <span class="badge badge-{{ $boost->context === 'listing' ? 'green' : 'amber' }}">
                                    {{ ucfirst($boost->context) }}
                                </span>
                                    </div>
                                    <div class="progress-wrap">
                                        <?php
                                        $impressions = $boost->impressions ?? 0;
                                        $daysLeft = now_datetime()->diffInDays($boost->ends_at, false);
                                        $totalDays = $boost->starts_at->diff($boost->ends_at)->days;
                                        $pct = $totalDays > 0 ? min(100, round((1 - $daysLeft / $totalDays) * 100)) : 0;
                                        ?>
                                        <div class="progress-labels">
                                            <span class="text-xs">Impressions: {{ number_format($impressions) }}</span>
                                            <span class="text-xs">{{ $daysLeft }} days left</span>
                                        </div>
                                        <div class="progress-bar-bg">
                                            <div class="progress-bar-fill {{ $boost->context === 'listing' ? 'green' : 'accent' }}"
                                                 style="width:{{ $pct }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">⚡</div>
                                <div class="empty-state-text">No active boo
                                    sts
                                </div>
                                <div class="empty-state-sub">Create one to increase visibility</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="two-col">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Recent Orders</div>
                        <div class="card-actions">
                            <button class="btn btn-sm btn-ghost">View all</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Order</th>
                                <th>Product</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($recentOrders->count() > 0): ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td class="font-mono text-xs">#{{ $order->id }}</td>
                                        <td>{{ $order->items->first()?->product_name ?? '—' }}</td>
                                        <td class="font-mono">£{{ number_format($order->total, 2) }}</td>
                                        <td>
                                            <?php
                                            $statusMap = ['completed' => 'badge-green', 'processing' => 'badge-blue', 'pending' => 'badge-amber', 'cancelled' => 'badge-red'];
                                            $statusBadge = $statusMap[$order->status->value ?? $order->status] ?? 'badge-gray';
                                            ?>
                                            <span class="badge {{ $statusBadge }}">{{ ucfirst($order->status->value ?? $order->status) }}</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;color:var(--ink-4);padding:24px">No recent
                                        orders
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Top Products</div>
                        <div class="card-sub">By revenue this month</div>
                    </div>
                    <div class="card-body">
                        <?php
                        $maxRevenue = max($topProducts->pluck('revenue')->toArray() ?: [1]);
                        $barColors = ['accent', 'blue', 'green', 'green'];
                        ?>
                        <?php
                        if ($topProducts->count() > 0):
                            foreach ($topProducts as $i => $product): ?>
                                <div class="progress-wrap mt-2">
                                    <div class="progress-labels">
                                        <span style="font-size:12px;font-weight:500">{{ $product->name }}</span>
                                        <span class="text-xs">£{{ number_format($product->revenue ?? 0) }}</span>
                                    </div>
                                    <div class="progress-bar-bg">
                                        <div class="progress-bar-fill {{ $barColors[$i] ?? 'accent' }}"
                                             style="width:{{ round(($product->revenue / $maxRevenue) * 100) }}%"></div>
                                    </div>
                                </div>
                            <?php endforeach;
                        else: ?>
                            <div class="empty-state">
                                <div class="empty-state-text">No revenue data yet</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════ PRODUCTS ═══ -->
        <?php
        $activeProducts = $products->where('status', 'active');
        $draftProducts = $products->where('status', 'draft');
        $oosProducts = $products->where('status', 'out_of_stock');

        $statusBadgeMap = [
                'active' => 'badge-green',
                'draft' => 'badge-gray',
                'out_of_stock' => 'badge-red'
        ];

        $statusLabel = [
                'active' => 'Active',
                'draft' => 'Draft',
                'out_of_stock' => 'Out of stock'
        ];
        ?>

        <!-- ════════════════════════════ PRODUCTS ════ -->
        <div class="panel" id="panel-products">

            <div class="flex items-center gap-2 mb-6">
                <div class="tab-bar">
                    <button class="tab-btn active">
                        All (<?php echo $products->count(); ?>)
                    </button>
                    <button class="tab-btn">
                        Active (<?php echo $activeProducts->count(); ?>)
                    </button>
                    <button class="tab-btn">
                        Draft (<?php echo $draftProducts->count(); ?>)
                    </button>
                    <button class="tab-btn">
                        Out of Stock (<?php echo $oosProducts->count(); ?>)
                    </button>
                </div>

                <div class="ml-auto flex gap-2">
                    <div class="search-bar" style="width:200px">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                             stroke-width="1.8">
                            <circle cx="7" cy="7" r="5"/>
                            <path d="M12 12l2 2"/>
                        </svg>
                        <input type="text" placeholder="Search products…">
                    </div>

                    <button class="btn btn-primary" onclick="openModal('product-modal')">
                        + Add Product
                    </button>
                </div>
            </div>

            <div class="product-grid">

                <?php foreach ($products as $product): ?>

                    <?php
                    $productStatus = $product->is_active ? 'active' : 'draft';
                    $isBoosted = $activeBoosts->contains('product_id', $product->id);
                    ?>

                    <div class="product-card">
                        <div class="product-img">
                            📦
                            <div class="product-badges">

                        <span class="badge <?php echo $statusBadgeMap[$productStatus] ?? 'badge-gray'; ?>">
                            <?php echo $statusLabel[$productStatus] ?? ucfirst($productStatus); ?>
                        </span>

                                <?php if ($isBoosted): ?>
                                    <span class="badge badge-red" style="margin-top:3px">
                                Boosted
                            </span>
                                <?php endif; ?>

                            </div>
                        </div>

                        <div class="product-info">

                            <div class="product-name">
                                <?php echo htmlspecialchars($product->name); ?>
                            </div>

                            <div class="product-cat">
                                <?php echo htmlspecialchars($product->category->name ?? '—'); ?>
                            </div>

                            <div class="product-price">
                                <?php if ($product->sale_price && $product->sale_price < $product->price): ?>

                                    <span class="product-sale">
                                £<?php echo number_format($product->sale_price, 2); ?>
                            </span>

                                    <span class="product-orig">
                                £<?php echo number_format($product->price, 2); ?>
                            </span>

                                <?php else: ?>

                                    <span>
                                £<?php echo number_format($product->price, 2); ?>
                            </span>

                                <?php endif; ?>
                            </div>

                            <div class="product-actions">

                                <?php if ($productStatus === 'draft'): ?>
                                    <button class="btn btn-sm btn-primary">Publish</button>

                                <?php elseif ($productStatus === 'out_of_stock'): ?>
                                    <button class="btn btn-sm btn-secondary">Restock</button>
                                <?php endif; ?>

                                <button class="btn btn-sm btn-secondary"
                                        onclick="openModal('product-modal')">
                                    Edit
                                </button>

                                <?php if ($productStatus === 'active'): ?>
                                    <button class="btn btn-sm btn-ghost"
                                            onclick="navigate('boost')">
                                        Boost
                                    </button>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>

                <div class="product-card"
                     style="border:2px dashed var(--border);background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;min-height:240px"
                     onclick="openModal('product-modal')">

                    <div style="text-align:center;color:var(--ink-4)">
                        <div style="font-size:32px;margin-bottom:8px">+</div>
                        <div style="font-size:13px;font-weight:500">Add Product</div>
                    </div>

                </div>

            </div>
        </div>

        <?php
        $activeOffers = $offers->where('status', 'published');

        $offerStatusMap = [
                'published' => 'badge-green',
                'pending' => 'badge-amber',
                'expired' => 'badge-gray',
                'paused' => 'badge-red'
        ];
        ?>

        <!-- ════════════════════════════ OFFERS ════ -->
        <div class="panel" id="panel-offers">

            <div class="flex items-center mb-6">
                <div class="tab-bar">
                    <button class="tab-btn active">All Offers</button>
                    <button class="tab-btn">Active</button>
                    <button class="tab-btn">Pending</button>
                    <button class="tab-btn">Expired</button>
                </div>

                <div class="ml-auto">
                    <button class="btn btn-primary" onclick="openModal('offer-modal')">
                        + New Offer
                    </button>
                </div>
            </div>

            <div class="card mb-4">

                <div class="card-header">
                    <div class="card-title">Active Offers</div>
                    <span class="badge badge-green">
                <?php echo $activeOffers->count(); ?> live
            </span>
                </div>

                <div class="card-body">

                    <?php if (count($offers) > 0): ?>

                        <?php foreach ($offers as $offer): ?>

                            <?php
                            $offerStatus = $offer->status->value ?? $offer->status;

                            if ($offer->starts_at && $offer->ends_at) {
                                $dateRange = $offer->starts_at->format('d M') .
                                        ' – ' .
                                        $offer->ends_at->format('d M Y');
                            } elseif ($offer->starts_at) {
                                $dateRange = 'From ' . $offer->starts_at->format('d M Y');
                            } else {
                                $dateRange = 'Ongoing · No expiry set';
                            }
                            ?>

                            <div class="offer-list-item">

                                <div class="offer-img">🏷️</div>

                                <div class="offer-details">
                                    <div class="offer-name">
                                        <?php echo htmlspecialchars($offer->product->name ?? '—'); ?>
                                    </div>

                                    <div class="offer-dates">
                                        <?php echo $dateRange; ?>
                                    </div>
                                </div>

                                <div>
                            <span class="badge <?php echo $offerStatusMap[$offerStatus] ?? 'badge-gray'; ?>">
                                <?php echo ucfirst($offerStatus); ?>
                            </span>
                                </div>

                                <div class="offer-price">

                                    <div class="offer-sale">
                                        £<?php echo number_format($offer->sale_price, 2); ?>
                                    </div>

                                    <?php if (!empty($offer->original_price)): ?>
                                        <div class="offer-orig">
                                            £<?php echo number_format($offer->original_price, 2); ?>
                                        </div>
                                    <?php endif; ?>

                                </div>

                                <div class="flex gap-2">

                                    <button class="btn btn-sm btn-secondary"
                                            onclick="openModal('offer-modal')">
                                        Edit
                                    </button>

                                    <?php if ($offerStatus === 'published'): ?>
                                        <button class="btn btn-sm btn-danger"
                                                onclick="showNotif('Offer paused')">
                                            Pause
                                        </button>

                                    <?php elseif ($offerStatus === 'pending'): ?>
                                        <button class="btn btn-sm btn-primary">
                                            Approve
                                        </button>
                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty-state">
                            <div class="empty-state-icon">🏷️</div>
                            <div class="empty-state-text">No offers yet</div>
                            <div class="empty-state-sub">
                                Create an offer to promote a product
                            </div>
                        </div>

                    <?php endif; ?>

                </div>
            </div>

        </div>

        <?php
        $budgetUsed = $boostStats->budget_used ?? 0;
        $budgetTotal = $boostStats->budget_total ?? 200;
        $budgetPct = $budgetTotal > 0 ? round(($budgetUsed / $budgetTotal) * 100) : 0;
        $budgetRemaining = $budgetTotal - $budgetUsed;
        ?>

        <!-- ════════════════════════════ BOOST ════ -->
        <div class="panel" id="panel-boost">

            <div class="two-col mb-6">

                <div>

                    <?php if ($boostStats): ?>
                        <div class="alert alert-success">
                            <strong>Auto Boost enabled.</strong>
                            Budget: £<?php echo number_format($budgetTotal); ?>/month ·
                            Used: £<?php echo number_format($budgetUsed); ?> ·
                            <?php echo $activeBoostCount; ?> products auto-boosted this cycle.
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">

                        <div class="card-header">
                            <div class="card-title">Auto Boost Settings</div>

                            <label class="toggle ml-auto">
                                <input type="checkbox"
                                        <?php echo ($boostStats?->auto_boost_enabled ?? false) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="card-body">

                            <div class="form-row">

                                <div class="form-group">
                                    <label class="form-label">Monthly Budget</label>

                                    <input type="number"
                                           class="form-control"
                                           value="<?php echo $boostStats?->budget_total ?? 200; ?>">

                                    <div class="form-hint">
                                        Max spend per calendar month
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Goal</label>

                                    <select class="form-control">
                                        <option>Maximise Revenue</option>
                                        <option>Promote Deals</option>
                                        <option>Clear Inventory</option>
                                    </select>
                                </div>

                            </div>

                            <button class="btn btn-secondary"
                                    onclick="showNotif('Auto Boost preview generated')">
                                Preview Plan
                            </button>

                            <button class="btn btn-primary"
                                    style="margin-left:8px"
                                    onclick="showNotif('Settings saved successfully')">
                                Save Settings
                            </button>

                        </div>
                    </div>

                    <?php if ($boostStats): ?>

                        <div class="card">

                            <div class="card-header">
                                <div class="card-title">Budget Usage</div>
                                <span class="text-xs">
                            <?php echo now_datetime()->format('M Y'); ?>
                        </span>
                            </div>

                            <div class="card-body">

                                <div style="display:flex;justify-content:space-between;margin-bottom:8px">

                            <span style="font-family:var(--font-mono);font-size:22px;font-weight:500">
                                £<?php echo number_format($budgetUsed); ?>

                                <span style="font-size:13px;color:var(--ink-4)">
                                    / £<?php echo number_format($budgetTotal); ?>
                                </span>
                            </span>

                                    <span class="badge badge-green">
                                <?php echo $budgetPct; ?>% used
                            </span>

                                </div>

                                <div class="progress-bar-bg" style="height:8px">
                                    <div class="progress-bar-fill green"
                                         style="width:<?php echo $budgetPct; ?>%;height:100%">
                                    </div>
                                </div>

                                <div class="text-xs mt-2">
                                    £<?php echo number_format($budgetRemaining); ?> remaining ·
                                    Resets <?php echo now_datetime()->endOfMonth()->diffForHumans(); ?>
                                </div>

                            </div>
                        </div>

                    <?php endif; ?>

                </div>

                <div>

                    <div class="card mb-4">

                        <div class="card-header">
                            <div class="card-title">Active Boosts</div>

                            <div class="card-actions">
                                <button class="btn btn-sm btn-primary"
                                        onclick="openModal('boost-modal')">
                                    + New Boost
                                </button>
                            </div>
                        </div>

                        <div class="table-wrap">

                            <table>

                                <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Context</th>
                                    <th>Multiplier</th>
                                    <th>Impressions</th>
                                    <th>Clicks</th>
                                    <th>Status</th>
                                    <th>Ends</th>
                                    <th></th>
                                </tr>
                                </thead>

                                <tbody>

                                <?php if (count($activeBoosts) > 0): ?>

                                    <?php foreach ($activeBoosts as $boost): ?>

                                        <tr>
                                            <td style="font-weight:500">
                                                <?php echo htmlspecialchars($boost->product->name ?? '—'); ?>
                                            </td>

                                            <td>
                                        <span class="badge <?php echo $boost->context === 'listing'
                                                ? 'badge-blue'
                                                : 'badge-amber'; ?>">
                                            <?php echo ucfirst($boost->context); ?>
                                        </span>
                                            </td>

                                            <td class="font-mono">
                                                ×<?php echo number_format($boost->multiplier, 1); ?>
                                            </td>

                                            <td class="font-mono">
                                                <?php echo number_format($boost->impressions ?? 0); ?>
                                            </td>

                                            <td class="font-mono">
                                                <?php echo number_format($boost->clicks ?? 0); ?>
                                            </td>

                                            <td>
                                                <span class="badge badge-green">Active</span>
                                            </td>

                                            <td class="text-xs">
                                                <?php echo $boost->ends_at?->format('d M') ?? '—'; ?>
                                            </td>

                                            <td>
                                                <button class="btn btn-sm btn-danger"
                                                        onclick="showNotif('Boost paused')">
                                                    Pause
                                                </button>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="8"
                                            style="text-align:center;color:var(--ink-4);padding:24px">
                                            No active boosts
                                        </td>
                                    </tr>

                                <?php endif; ?>

                                </tbody>

                            </table>

                        </div>
                    </div>

                </div>

            </div>
        </div>

        <?php
        $activeVouchers = $vouchers->filter(function ($v) {
            return ($v->status->value ?? $v->status) === 'active';
        });

        $expiringSoon = $activeVouchers->filter(function ($v) {
            return $v->expires_at && now_datetime()->diffInDays($v->expires_at) <= 7;
        });

        $totalRedeemed = $vouchers->sum(function ($v) {
            return ($v->discount_type === 'fixed' ? $v->discount_value : 0)
                    * ($v->times_used ?? 0);
        });
        ?>

        <!-- ════════════════════════════ VOUCHERS ════ -->
        <div class="panel" id="panel-vouchers">

            <div class="flex items-center mb-6">

                <div class="tab-bar">
                    <button class="tab-btn active">All Vouchers</button>
                    <button class="tab-btn">Active</button>
                    <button class="tab-btn">Expired</button>
                </div>

                <div class="ml-auto">
                    <button class="btn btn-primary"
                            onclick="openModal('voucher-modal')">
                        + New Voucher
                    </button>
                </div>

            </div>

            <div class="two-col mb-6">

                <div class="stat-card green">
                    <div class="stat-label">Active Codes</div>
                    <div class="stat-value">
                        <?php echo $activeVouchers->count(); ?>
                    </div>
                    <div class="stat-delta">
                        <?php echo $expiringSoon->count(); ?> expiring within 7 days
                    </div>
                </div>

                <div class="stat-card amber">
                    <div class="stat-label">Total Vouchers</div>
                    <div class="stat-value">
                        <?php echo $vouchers->count(); ?>
                    </div>
                    <div class="stat-delta">Across all statuses</div>
                </div>

            </div>

            <div class="card">

                <div class="card-header">
                    <div class="card-title">Voucher Codes</div>
                </div>

                <div class="card-body">

                    <?php if (count($vouchers) > 0): ?>

                        <?php foreach ($vouchers as $voucher): ?>

                            <?php
                            $vStatus = $voucher->status->value ?? $voucher->status;

                            $isExpiringSoon =
                                    $voucher->expires_at
                                    && now_datetime()->diffInDays($voucher->expires_at) <= 7
                                    && $vStatus === 'active';

                            $voucherBgColor = match ($voucher->discount_type) {
                                'fixed' => 'var(--blue)',
                                default => 'var(--ink)'
                            };

                            $discountDisplay = $voucher->discount_type === 'percentage'
                                    ? $voucher->value . '%'
                                    : '£' . number_format($voucher->value);

                            $usageMeta = $voucher->usage_limit
                                    ? "Used {$voucher->times_used}/{$voucher->usage_limit}"
                                    : 'Unlimited uses';

                            $expMeta = $voucher->expires_at
                                    ? 'Expires ' . $voucher->expires_at->format('d M Y')
                                    : 'No expiry';
                            ?>

                            <div class="voucher-card">

                                <div class="voucher-left"
                                     style="background:<?php echo $voucherBgColor; ?>">

                                    <div class="voucher-pct">
                                        <?php echo $discountDisplay; ?>
                                    </div>

                                    <div class="voucher-off">off</div>

                                </div>

                                <div class="voucher-right">

                                    <div>
                                        <div class="voucher-code">
                                            <?php echo htmlspecialchars($voucher->code); ?>
                                        </div>

                                        <div class="voucher-meta">
                                            <?php echo $expMeta; ?>
                                            ·
                                            <?php echo $usageMeta; ?>
                                        </div>
                                    </div>

                                    <div class="voucher-actions">

                                        <?php if ($isExpiringSoon): ?>

                                            <span class="badge badge-red">
                                        Expiring soon
                                    </span>

                                        <?php elseif ($vStatus === 'active'): ?>

                                            <span class="badge badge-green">
                                        Active
                                    </span>

                                        <?php elseif ($vStatus === 'expired'): ?>

                                            <span class="badge badge-gray">
                                        Expired
                                    </span>

                                        <?php else: ?>

                                            <span class="badge badge-gray">
                                        <?php echo ucfirst($vStatus); ?>
                                    </span>

                                        <?php endif; ?>

                                        <button class="btn btn-sm btn-secondary"
                                                onclick="copyCode('<?php echo $voucher->code; ?>')">
                                            Copy
                                        </button>

                                        <?php if ($vStatus === 'expired'): ?>

                                            <button class="btn btn-sm btn-ghost">
                                                Clone
                                            </button>

                                        <?php elseif ($isExpiringSoon): ?>

                                            <button class="btn btn-sm btn-danger"
                                                    onclick="showNotif('Voucher deactivated')">
                                                Deactivate
                                            </button>

                                        <?php else: ?>

                                            <button class="btn btn-sm btn-ghost"
                                                    onclick="openModal('voucher-modal')">
                                                Edit
                                            </button>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="empty-state">
                            <div class="empty-state-icon">🎟️</div>
                            <div class="empty-state-text">No vouchers yet</div>
                            <div class="empty-state-sub">
                                Create a voucher to reward your customers
                            </div>
                        </div>

                    <?php endif; ?>

                </div>

            </div>
        </div>

        <?php
        $txBadgeMap = function ($status) {
            return match ($status) {
                'completed', 'paid' => 'badge-green',
                'processing', 'pending' => 'badge-amber',
                default => 'badge-gray'
            };
        };
        ?>

        <!-- ════════════════════════════ COMMISSION ════ -->
        <div class="panel" id="panel-commission">

            <div class="commission-summary">

                <div class="commission-block">
                    <div class="commission-label">Total Gross Sales</div>
                    <div class="commission-value">
                        £<?php echo number_format($commissionSummary['gross_sales'] ?? 0); ?>
                    </div>
                    <div class="commission-note">This month</div>
                </div>

                <div class="commission-divider"></div>

                <div class="commission-block">
                    <div class="commission-label">Commission Deducted</div>
                    <div class="commission-value" style="color:var(--accent)">
                        £<?php echo number_format($commissionSummary['commission_total'] ?? 0); ?>
                    </div>
                    <div class="commission-note">
                        Avg <?php echo $commissionSummary['blended_rate'] ?? 0; ?>% blended rate
                    </div>
                </div>

                <div class="commission-divider"></div>

                <div class="commission-block">
                    <div class="commission-label">Net Earnings</div>
                    <div class="commission-value" style="color:var(--green)">
                        £<?php echo number_format($commissionSummary['net_earnings'] ?? 0); ?>
                    </div>
                    <div class="commission-note">Available for payout</div>
                </div>

            </div>

            <div class="two-col">

                <!-- Commission Rates -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Commission Rates</div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Rate</th>
                                <th>Applies To</th>
                            </tr>
                            </thead>

                            <tbody>

                            <?php if (count($commissionRates) > 0): ?>

                                <?php foreach ($commissionRates as $rate):
                                    ?>
                                    <tr>
                                        <td class="font-mono" style="color:var(--ink-2)">
                                            <?php echo $rate['commission_rate']; ?>%
                                        </td>
                                        <td><?php echo $rate['product_names']; ?></td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="3"
                                        style="text-align:center;color:var(--ink-4);padding:24px">
                                        No rate data
                                    </td>
                                </tr>

                            <?php endif; ?>

                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Commission By Product -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Commission by Product</div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th>Revenue</th>
                                <th>Rate</th>
                                <th>Commission</th>
                            </tr>
                            </thead>

                            <tbody>

                            <?php if (count($commissionByProduct) > 0): ?>

                                <?php foreach ($commissionByProduct as $item):
                                    ?>
                                    <tr>
                                        <td style="font-weight:500">
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                        </td>

                                        <td class="font-mono">
                                            £<?php echo number_format($item['revenue']); ?>
                                        </td>

                                        <td class="font-mono">
                                            <?php echo $item['avg_rate']; ?>%
                                        </td>

                                        <td class="font-mono" style="color:var(--accent)">
                                            £<?php echo number_format($item['commission_amount']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="4"
                                        style="text-align:center;color:var(--ink-4);padding:24px">
                                        No commission data
                                    </td>
                                </tr>

                            <?php endif; ?>

                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- ════════════════════════════ INVOICES ════ -->
        <div class="panel" id="panel-invoices">

            <div class="flex items-center mb-6">

                <div class="tab-bar">
                    <button class="tab-btn active">All</button>
                    <button class="tab-btn">Paid</button>
                    <button class="tab-btn">Pending</button>
                    <button class="tab-btn">Overdue</button>
                </div>

                <div class="ml-auto">
                    <button class="btn btn-secondary">↓ Export CSV</button>
                </div>

            </div>

            <div class="card">

                <div class="card-header">
                    <div class="card-title">Invoice History</div>
                    <div class="card-sub">Last 12 months</div>
                </div>

                <div class="table-wrap">
                    <table>

                        <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Period</th>
                            <th>Gross</th>
                            <th>Commission</th>
                            <th>Net</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php if (count($transactions) > 0): ?>

                            <?php foreach ($transactions as $tx): ?>

                                <?php
                                $txStatus = $tx->status->value ?? $tx->status;
                                $txBadge = $txBadgeMap($txStatus);
                                ?>

                                <tr>
                                    <td class="font-mono text-xs">
                                        <?php echo $tx->reference ?? ('TXN-' . str_pad($tx->id, 6, '0', STR_PAD_LEFT)); ?>
                                    </td>

                                    <td>
                                        <?php echo optional($tx->period_start)?->format('M Y') ?? '—'; ?>
                                    </td>

                                    <td class="font-mono">
                                        £<?php echo number_format($tx->gross_amount ?? 0, 0); ?>
                                    </td>

                                    <td class="font-mono" style="color:var(--accent)">
                                        £<?php echo number_format($tx->commission_amount ?? 0, 0); ?>
                                    </td>

                                    <td class="font-mono" style="color:var(--green);font-weight:500">
                                        £<?php echo number_format($tx->net_amount ?? 0, 0); ?>
                                    </td>

                                    <td>
                                <span class="badge <?php echo $txBadge; ?>">
                                    <?php echo ucfirst($txStatus); ?>
                                </span>
                                    </td>

                                    <td class="text-xs">
                                        <?php echo optional($tx->created_at)?->format('d M') ?? '—'; ?>
                                    </td>

                                    <td>
                                        <button class="btn btn-sm btn-ghost">View</button>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8"
                                    style="text-align:center;color:var(--ink-4);padding:24px">
                                    No transactions found
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>
                </div>

            </div>
        </div>

        <!-- ════════════════════════════ REVIEWS ════ -->
        <div class="panel" id="panel-reviews">

            <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">

                <div class="stat-card green">
                    <div class="stat-label">Avg Rating</div>
                    <div class="stat-value">
                        <?php echo $stats->averageRating; ?>
                    </div>
                    <div class="stat-delta up">
                        <?php echo $reviewStats['total'] ?? 0; ?> approved reviews
                    </div>
                </div>

                <div class="stat-card amber">
                    <div class="stat-label">Pending Response</div>
                    <div class="stat-value">
                        <?php echo $reviewStats['pending_response'] ?? 0; ?>
                    </div>
                    <div class="stat-delta">Awaiting your reply</div>
                </div>

                <div class="stat-card blue">
                    <div class="stat-label">This Month</div>
                    <div class="stat-value">
                        <?php echo $reviewStats['this_month'] ?? 0; ?>
                    </div>

                    <?php
                    $reviewDelta = ($reviewStats['this_month'] ?? 0)
                            - ($reviewStats['previous_month'] ?? 0);
                    ?>

                    <div class="stat-delta <?php echo $reviewDelta >= 0 ? 'up' : 'down'; ?>">
                        <?php echo $reviewDelta >= 0 ? '↑' : '↓'; ?>
                        <?php echo abs($reviewDelta); ?> vs last month
                    </div>
                </div>

            </div>

            <div class="two-col">

                <!-- Recent Reviews -->
                <div class="card">

                    <div class="card-header">
                        <div class="card-title">Recent Reviews</div>
                        <div class="card-actions">
                            <div class="tab-bar" style="margin:0">
                                <button class="tab-btn active">All</button>
                                <button class="tab-btn">Pending</button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">

                        <?php if (count($recentReviews) > 0): ?>

                            <?php foreach ($recentReviews as $review): ?>

                                <?php
                                $nameParts = explode(' ', $review->reviewer_name ?? 'Unknown');
                                $avatarInitials = implode('', array_map(
                                        fn($w) => strtoupper($w[0]),
                                        array_slice($nameParts, 0, 2)
                                ));

                                $starsFilled = str_repeat('★', (int)$review->rating);
                                $starsEmpty = str_repeat('☆', 5 - (int)$review->rating);
                                $needsReply = empty($review->merchant_reply);
                                ?>

                                <div class="review-item">

                                    <div class="review-header">

                                        <div class="review-avatar">
                                            <?php echo $avatarInitials; ?>
                                        </div>

                                        <div>
                                            <div class="review-author">
                                                <?php echo $review->reviewer_name ?? 'Anonymous'; ?>
                                            </div>

                                            <div class="stars">
                                                <?php echo $starsFilled; ?>
                                                <span style="color:var(--paper-3)">
                                            <?php echo $starsEmpty; ?>
                                        </span>
                                            </div>
                                        </div>

                                        <div class="review-date">
                                            <?php echo $review->created_at->format('d M Y'); ?>
                                        </div>

                                    </div>

                                    <?php if ($review->title): ?>
                                        <div class="review-title">
                                            <?php echo htmlspecialchars($review->title); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="review-body">
                                        <?php echo htmlspecialchars($review->comment); ?>
                                    </div>

                                    <div class="review-product">
                                        📦 <?php echo $review->product_name
                                                ?? $review->product?->name
                                                ?? '—'; ?>
                                    </div>

                                    <div style="margin-top:10px;display:flex;gap:6px">

                                        <button class="btn btn-sm <?php echo $needsReply ? 'btn-primary' : 'btn-secondary'; ?>"
                                                onclick="showNotif('Reply sent')">
                                            <?php echo $needsReply ? 'Reply (Pending)' : 'Reply'; ?>
                                        </button>

                                        <button class="btn btn-sm btn-ghost">Flag</button>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="empty-state">
                                <div class="empty-state-icon">💬</div>
                                <div class="empty-state-text">No reviews yet</div>
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

                <!-- Rating Breakdown -->
                <div class="card">

                    <div class="card-header">
                        <div class="card-title">Rating Breakdown</div>
                    </div>

                    <div class="card-body">

                        <div style="margin-bottom:20px">

                            <div style="font-family:var(--font-serif);font-size:48px;text-align:center;letter-spacing:-2px">
                                <?php
                                echo $stats->averageRating; ?>
                            </div>

                            <div style="text-align:center;color:#f59e0b;font-size:20px;margin-bottom:4px">
                                <?php
                                $rounded = (int)round($stats->averageRating);
                                echo str_repeat('★', $rounded);
                                echo str_repeat('☆', 5 - $rounded);
                                ?>
                            </div>

                            <div style="text-align:center;font-size:12px;color:var(--ink-4);font-family:var(--font-mono)">
                                <?php echo $reviewStats['total'] ?? 0; ?> reviews total
                            </div>

                        </div>

                        <?php
                        $distribution = $reviewStats['rating_distribution'] ?? [];
                        $distColors = [5 => 'green', 4 => 'green', 3 => 'amber', 2 => 'accent', 1 => 'accent'];
                        ?>

                        <?php foreach ([5, 4, 3, 2, 1] as $star): ?>

                            <div class="progress-wrap mt-2">

                                <div class="progress-labels">
                                    <span class="text-xs"><?php echo $star; ?> ★</span>
                                    <span class="text-xs">
                                <?php echo $distribution[$star] ?? 0; ?>%
                            </span>
                                </div>

                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill <?php echo $distColors[$star]; ?>"
                                         style="width:<?php echo $distribution[$star] ?? 0; ?>%">
                                    </div>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

        </div>

        <!-- ═══ MODALS ═══ -->

        <!-- Product Modal -->
        <div class="modal-overlay" id="modal-product-modal">
            <div class="modal">
                <div class="modal-header">
                    <span>📦</span>
                    <div class="modal-title">Add / Edit Product</div>
                    <button class="modal-close" onclick="closeModal('product-modal')">✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Product Name</label><input type="text"
                                                                                                     class="form-control"
                                                                                                     placeholder="e.g. USB-C Hub Pro 7-in-1">
                        </div>
                        <div class="form-group"><label class="form-label">SKU</label><input type="text"
                                                                                            class="form-control"
                                                                                            placeholder="e.g. TCH-001">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Price (£)</label><input type="number"
                                                                                                  class="form-control"
                                                                                                  placeholder="64.99">
                        </div>
                        <div class="form-group"><label class="form-label">Sale Price (£)</label><input type="number"
                                                                                                       class="form-control"
                                                                                                       placeholder="49.99">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Category</label><select class="form-control">
                                <option>Electronics</option>
                                <option>Accessories</option>
                                <option>Portable</option>
                            </select></div>
                        <div class="form-group"><label class="form-label">Stock Quantity</label><input type="number"
                                                                                                       class="form-control"
                                                                                                       placeholder="100">
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Description</label><textarea class="form-control"
                                                                                                   placeholder="Product description…"></textarea>
                    </div>
                    <div class="form-group"><label class="form-label">Product URL</label><input type="url"
                                                                                                class="form-control"
                                                                                                placeholder="https://yourstore.com/product">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('product-modal')">Cancel</button>
                    <button class="btn btn-primary"
                            onclick="closeModal('product-modal');showNotif('Product saved successfully')">Save Product
                    </button>
                </div>
            </div>
        </div>

        <!-- Boost Modal -->
        <div class="modal-overlay" id="modal-boost-modal">
            <div class="modal">
                <div class="modal-header">
                    <span>⚡</span>
                    <div class="modal-title">Create Boost</div>
                    <button class="modal-close" onclick="closeModal('boost-modal')">✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Select Product to Boost</label>
                        <select class="form-control">
                            <option>USB-C Hub Pro 7-in-1</option>
                            <option>Wireless Charger 15W</option>
                            <option>Laptop Stand XL</option>
                        </select>
                    </div>
                    <div class="boost-grid" style="grid-template-columns:1fr 1fr;margin-bottom:16px">
                        <div class="boost-card selected" onclick="selectBoost(this)">
                            <div class="selected-check">✓</div>
                            <div class="boost-card-label">Context</div>
                            <div class="boost-card-name">Listing</div>
                            <div class="boost-card-desc">Appear higher in search and category listings</div>
                            <div class="boost-card-price">£1.50 <span class="boost-card-per">/day</span></div>
                        </div>
                        <div class="boost-card" onclick="selectBoost(this)">
                            <div class="selected-check">✓</div>
                            <div class="boost-card-label">Context</div>
                            <div class="boost-card-name">Deals</div>
                            <div class="boost-card-desc">Feature prominently on the deals & offers page</div>
                            <div class="boost-card-price">£2.00 <span class="boost-card-per">/day</span></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Start Date</label><input type="date"
                                                                                                   class="form-control">
                        </div>
                        <div class="form-group"><label class="form-label">End Date</label><input type="date"
                                                                                                 class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Multiplier <span id="mult-display"
                                                                   style="font-family:var(--font-mono);color:var(--accent)">×1.5</span></label>
                        <div class="range-wrap">
                            <input type="range" min="10" max="30" value="15" oninput="updateMultiplier(this.value)">
                            <div class="range-labels"><span>×1.0</span><span>×2.0</span><span>×3.0</span></div>
                        </div>
                        <div class="form-hint">Higher multiplier = greater boost, higher cost</div>
                    </div>
                    <div style="background:var(--paper);border-radius:var(--radius-md);padding:12px;border:1px solid var(--border)">
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span>Duration:</span><span
                                    class="font-mono">7 days</span></div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span>Context rate:</span><span
                                    class="font-mono">£1.50/day</span></div>
                        <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:600;padding-top:6px;border-top:1px solid var(--border)">
                            <span>Est. Total:</span><span class="font-mono" style="color:var(--accent)">£10.50</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('boost-modal')">Cancel</button>
                    <button class="btn btn-primary"
                            onclick="closeModal('boost-modal');showNotif('Boost created and pending activation')">Create
                        Boost
                    </button>
                </div>
            </div>
        </div>

        <!-- Offer Modal -->
        <div class="modal-overlay" id="modal-offer-modal">
            <div class="modal">
                <div class="modal-header">
                    <span>🏷️</span>
                    <div class="modal-title">Create / Edit Offer</div>
                    <button class="modal-close" onclick="closeModal('offer-modal')">✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label class="form-label">Offer Name</label><input type="text"
                                                                                               class="form-control"
                                                                                               placeholder="e.g. Summer Sale 2025">
                    </div>
                    <div class="form-group"><label class="form-label">Product</label><select class="form-control">
                            <option>USB-C Hub Pro</option>
                            <option>Wireless Charger</option>
                            <option>Laptop Stand XL</option>
                        </select></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Original Price (£)</label><input type="number"
                                                                                                           class="form-control"
                                                                                                           value="64.99">
                        </div>
                        <div class="form-group"><label class="form-label">Sale Price (£)</label><input type="number"
                                                                                                       class="form-control"
                                                                                                       value="44.99">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Start Date</label><input type="date"
                                                                                                   class="form-control">
                        </div>
                        <div class="form-group"><label class="form-label">End Date</label><input type="date"
                                                                                                 class="form-control">
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Status</label><select class="form-control">
                            <option>Pending</option>
                            <option>Published</option>
                        </select></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('offer-modal')">Cancel</button>
                    <button class="btn btn-primary"
                            onclick="closeModal('offer-modal');showNotif('Offer submitted for review')">Submit Offer
                    </button>
                </div>
            </div>
        </div>

        <!-- Voucher Modal -->
        <div class="modal-overlay" id="modal-voucher-modal">
            <div class="modal">
                <div class="modal-header">
                    <span>🎟️</span>
                    <div class="modal-title">Create Voucher</div>
                    <button class="modal-close" onclick="closeModal('voucher-modal')">✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Voucher Code</label><input type="text"
                                                                                                     class="form-control"
                                                                                                     placeholder="e.g. SAVE20"
                                                                                                     style="font-family:var(--font-mono);letter-spacing:2px">
                        </div>
                        <div class="form-group"><label class="form-label">Type</label><select class="form-control">
                                <option>Percentage (%)</option>
                                <option>Fixed Amount (£)</option>
                            </select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Discount Value</label><input type="number"
                                                                                                       class="form-control"
                                                                                                       placeholder="20">
                        </div>
                        <div class="form-group"><label class="form-label">Usage Limit</label><input type="number"
                                                                                                    class="form-control"
                                                                                                    placeholder="50 (blank = unlimited)">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Min Order Value (£)</label><input
                                    type="number" class="form-control" placeholder="0"></div>
                        <div class="form-group"><label class="form-label">Expires</label><input type="date"
                                                                                                class="form-control">
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Applies To</label><select class="form-control">
                            <option>All Products</option>
                            <option>Specific Product</option>
                            <option>Category</option>
                        </select></div>
                    <div class="form-group" style="display:flex;align-items:center;gap:10px">
                        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                        <span style="font-size:13px">Stackable with other vouchers</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('voucher-modal')">Cancel</button>
                    <button class="btn btn-primary"
                            onclick="closeModal('voucher-modal');showNotif('Voucher created successfully')">Create
                        Voucher
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification toast -->
        <div class="notif" id="notif">
            <span class="notif-icon">✓</span>
            <span id="notif-text">Done</span>
        </div>

        <script>
            // ─── NAVIGATION ──────────────────────────────────────
            const panels = {
                overview: {title: 'Overview', breadcrumb: 'Dashboard', action: 'Add Product', modal: 'product-modal'},
                products: {title: 'Products', breadcrumb: 'Products', action: 'Add Product', modal: 'product-modal'},
                offers: {title: 'Offers', breadcrumb: 'Offers', action: 'New Offer', modal: 'offer-modal'},
                boost: {title: 'Boost', breadcrumb: 'Marketing → Boost', action: 'New Boost', modal: 'boost-modal'},
                vouchers: {
                    title: 'Vouchers',
                    breadcrumb: 'Marketing → Vouchers',
                    action: 'New Voucher',
                    modal: 'voucher-modal'
                },
                commission: {title: 'Commission', breadcrumb: 'Finance → Commission', action: 'Export', modal: null},
                invoices: {title: 'Invoices', breadcrumb: 'Finance → Invoices', action: 'Export CSV', modal: null},
                reviews: {title: 'Reviews', breadcrumb: 'Community → Reviews', action: null, modal: null},
            };

            function navigate(panelKey) {
                // Update nav
                document.querySelectorAll('.nav-item').forEach(el => {
                    el.classList.toggle('active', el.dataset.panel === panelKey);
                });

                // Update panels
                document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
                document.getElementById('panel-' + panelKey)?.classList.add('active');

                // Update topbar
                const cfg = panels[panelKey];
                if (!cfg) return;
                document.getElementById('page-title').textContent = cfg.title;
                document.getElementById('page-breadcrumb').textContent = cfg.breadcrumb;

                const btn = document.getElementById('primary-action');
                if (cfg.action) {
                    btn.style.display = '';
                    btn.textContent = '+ ' + cfg.action;
                    btn.onclick = cfg.modal ? () => openModal(cfg.modal) : () => showNotif('Feature available in full integration');
                } else {
                    btn.style.display = 'none';
                }
            }

            document.querySelectorAll('.nav-item').forEach(el => {
                el.addEventListener('click', () => navigate(el.dataset.panel));
            });

            // ─── MODALS ───────────────────────────────────────────
            function openModal(id) {
                document.getElementById('modal-' + id)?.classList.add('open');
            }

            function closeModal(id) {
                document.getElementById('modal-' + id)?.classList.remove('open');
            }

            document.querySelectorAll('.modal-overlay').forEach(overlay => {
                overlay.addEventListener('click', e => {
                    if (e.target === overlay) overlay.classList.remove('open');
                });
            });

            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
            });

            // ─── NOTIFICATIONS ────────────────────────────────────
            let notifTimer;

            function showNotif(msg) {
                const el = document.getElementById('notif');
                document.getElementById('notif-text').textContent = msg;
                el.classList.add('show');
                clearTimeout(notifTimer);
                notifTimer = setTimeout(() => el.classList.remove('show'), 2800);
            }

            // ─── COPY CODE ────────────────────────────────────────
            function copyCode(code) {
                navigator.clipboard?.writeText(code).catch(() => {
                });
                showNotif('Copied: ' + code);
            }

            // ─── BOOST UI ─────────────────────────────────────────
            function selectBoost(card) {
                card.closest('.boost-grid').querySelectorAll('.boost-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
            }

            function updateMultiplier(val) {
                document.getElementById('mult-display').textContent = '×' + (val / 10).toFixed(1);
                // update range gradient
                const pct = ((val - 10) / 20) * 100;
                const input = document.querySelector('#modal-boost-modal input[type=range]');
                if (input) input.style.background = `linear-gradient(to right, var(--accent) 0%, var(--accent) ${pct}%, var(--border) ${pct}%, var(--border) 100%)`;
            }

            // ─── REVENUE CHART ────────────────────────────────────
            function buildChart() {
                const el = document.getElementById('revenue-chart');
                if (!el) return;
                const values = [42, 58, 35, 72, 88, 65, 91, 78, 104, 84, 112, 96, 88, 128, 110, 95, 142, 118, 106, 130, 155, 132, 148, 168, 145, 172, 160, 188, 175, 195];
                const max = Math.max(...values);
                el.innerHTML = values.map((v, i) => `
    <div class="chart-bar-wrap">
      <div class="chart-bar${i >= 27 ? ' highlight' : ''}" style="height:${(v / max) * 140}px" title="Day ${i + 1}: £${v * 18}"></div>
      ${i % 5 === 0 ? `<span class="chart-label">${i + 1}</span>` : '<span class="chart-label"></span>'}
    </div>
  `).join('');
            }

            // ─── TAB BUTTONS ─────────────────────────────────────
            document.querySelectorAll('.tab-bar').forEach(bar => {
                bar.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        bar.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                    });
                });
            });

            // ─── INIT ─────────────────────────────────────────────
            buildChart();
            navigate('overview');
        </script>
</body>
</html>