<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="api-token" content="<?= htmlspecialchars($apiToken ?? '', ENT_QUOTES) ?>">
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
            --purple: #6b3fa0;
            --purple-light: #e5d8f5;
            --teal: #1a6b6b;
            --teal-light: #d0ecec;
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
            padding: 0
        }

        html {
            font-size: 15px;
            -webkit-font-smoothing: antialiased
        }

        body {
            font-family: var(--font-sans);
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
        }

        /* ─── SIDEBAR ─── */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--ink);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: relative;
            z-index: 100
        }

        .sidebar::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 1px;
            background: linear-gradient(to bottom, transparent, rgba(255, 255, 255, .06) 30%, rgba(255, 255, 255, .06) 70%, transparent)
        }

        .sidebar-brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, .07)
        }

        .brand-mark {
            display: flex;
            align-items: center;
            gap: 10px
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
            flex-shrink: 0
        }

        .brand-name {
            font-family: var(--font-serif);
            font-size: 17px;
            color: rgba(255, 255, 255, .92);
            letter-spacing: -.3px;
            line-height: 1.1
        }

        .brand-sub {
            font-size: 10px;
            color: rgba(255, 255, 255, .3);
            font-family: var(--font-mono);
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-top: 1px
        }

        .merchant-tag {
            margin: 12px 20px;
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: var(--radius);
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 8px
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
            flex-shrink: 0
        }

        .merchant-info {
            flex: 1;
            min-width: 0
        }

        .merchant-name {
            font-size: 12px;
            font-weight: 500;
            color: rgba(255, 255, 255, .8);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .merchant-status {
            font-size: 10px;
            color: #4ade80;
            font-family: var(--font-mono);
            display: flex;
            align-items: center;
            gap: 4px
        }

        .merchant-status::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #4ade80
        }

        .nav-section {
            padding: 8px 12px 4px
        }

        .nav-label {
            font-size: 9px;
            font-family: var(--font-mono);
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .22);
            padding: 0 8px;
            margin-bottom: 2px
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
            user-select: none
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, .06);
            color: rgba(255, 255, 255, .82)
        }

        .nav-item.active {
            background: rgba(200, 73, 42, .18);
            color: rgba(255, 255, 255, .92)
        }

        .nav-item.active .nav-icon {
            color: var(--accent)
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
            border-radius: 0 2px 2px 0
        }

        .nav-icon {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            opacity: .7
        }

        .nav-item.active .nav-icon {
            opacity: 1
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
            text-align: center
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 12px;
            border-top: 1px solid rgba(255, 255, 255, .06)
        }

        .balance-card {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: var(--radius-md);
            padding: 12px 14px
        }

        .balance-label {
            font-size: 10px;
            color: rgba(255, 255, 255, .3);
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 4px
        }

        .balance-amount {
            font-family: var(--font-mono);
            font-size: 20px;
            color: rgba(255, 255, 255, .9);
            letter-spacing: -.5px
        }

        .balance-sub {
            font-size: 10px;
            color: rgba(255, 255, 255, .25);
            margin-top: 2px
        }

        /* ─── MAIN ─── */
        .main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden
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
            z-index: 50
        }

        .topbar-title {
            font-family: var(--font-serif);
            font-size: 19px;
            color: var(--ink);
            letter-spacing: -.3px
        }

        .topbar-breadcrumb {
            font-size: 12px;
            color: var(--ink-4);
            font-family: var(--font-mono)
        }

        .topbar-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px
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
            white-space: nowrap
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            border-color: var(--accent)
        }

        .btn-primary:hover {
            background: var(--accent-dark);
            border-color: var(--accent-dark)
        }

        .btn-secondary {
            background: var(--white);
            color: var(--ink-2);
            border-color: var(--border)
        }

        .btn-secondary:hover {
            background: var(--paper);
            border-color: var(--ink-4)
        }

        .btn-ghost {
            background: transparent;
            color: var(--ink-3);
            border-color: transparent
        }

        .btn-ghost:hover {
            background: var(--paper-2)
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 11.5px
        }

        .btn-danger {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca
        }

        .btn-danger:hover {
            background: #fee2e2
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 28px
        }

        /* ─── PANELS ─── */
        .panel {
            display: none
        }

        .panel.active {
            display: block;
            animation: fadeIn 200ms ease
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(4px)
            }
            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        /* ─── STATS ─── */
        .stats-grid {
            display: grid;
            grid-template-columns:repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px
        }

        .stats-grid-3 {
            display: grid;
            grid-template-columns:repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: box-shadow var(--transition)
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md)
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0
        }

        .stat-card.accent::before {
            background: var(--accent)
        }

        .stat-card.green::before {
            background: var(--green)
        }

        .stat-card.amber::before {
            background: var(--amber)
        }

        .stat-card.blue::before {
            background: var(--blue)
        }

        .stat-card.purple::before {
            background: var(--purple)
        }

        .stat-card.teal::before {
            background: var(--teal)
        }

        .stat-label {
            font-size: 10.5px;
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--ink-3);
            margin-bottom: 8px
        }

        .stat-value {
            font-family: var(--font-serif);
            font-size: 28px;
            color: var(--ink);
            letter-spacing: -.5px;
            line-height: 1;
            margin-bottom: 6px
        }

        .stat-delta {
            font-size: 11px;
            font-family: var(--font-mono);
            display: flex;
            align-items: center;
            gap: 3px
        }

        .stat-delta.up {
            color: var(--green)
        }

        .stat-delta.down {
            color: var(--accent)
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
            font-size: 15px
        }

        .accent-bg {
            background: var(--accent-light)
        }

        .green-bg {
            background: var(--green-light)
        }

        .amber-bg {
            background: var(--amber-light)
        }

        .blue-bg {
            background: var(--blue-light)
        }

        .purple-bg {
            background: var(--purple-light)
        }

        .teal-bg {
            background: var(--teal-light)
        }

        /* ─── CARD ─── */
        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink)
        }

        .card-sub {
            font-size: 12px;
            color: var(--ink-4);
            font-family: var(--font-mono)
        }

        .card-actions {
            margin-left: auto;
            display: flex;
            gap: 6px
        }

        .card-body {
            padding: 20px
        }

        /* ─── TABLE ─── */
        .table-wrap {
            overflow-x: auto
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px
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
            white-space: nowrap
        }

        tbody tr {
            border-bottom: 1px solid var(--paper-2);
            transition: background var(--transition)
        }

        tbody tr:last-child {
            border-bottom: none
        }

        tbody tr:hover {
            background: var(--paper)
        }

        tbody td {
            padding: 12px 16px;
            color: var(--ink-2);
            vertical-align: middle
        }

        /* ─── BADGE ─── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10.5px;
            font-family: var(--font-mono);
            font-weight: 500;
            white-space: nowrap
        }

        .badge-green {
            background: var(--green-light);
            color: var(--green)
        }

        .badge-amber {
            background: var(--amber-light);
            color: var(--amber)
        }

        .badge-red {
            background: var(--accent-light);
            color: var(--accent)
        }

        .badge-blue {
            background: var(--blue-light);
            color: var(--blue)
        }

        .badge-purple {
            background: var(--purple-light);
            color: var(--purple)
        }

        .badge-teal {
            background: var(--teal-light);
            color: var(--teal)
        }

        .badge-gray {
            background: var(--paper-3);
            color: var(--ink-3)
        }

        /* ─── FORM ─── */
        .form-group {
            margin-bottom: 16px
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--ink-2);
            margin-bottom: 5px
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
            outline: none
        }

        .form-control:focus {
            border-color: var(--ink-3);
            box-shadow: 0 0 0 3px rgba(15, 14, 13, .06)
        }

        .form-control::placeholder {
            color: var(--ink-4)
        }

        select.form-control {
            cursor: pointer
        }

        textarea.form-control {
            resize: vertical;
            min-height: 90px
        }

        .form-row {
            display: grid;
            grid-template-columns:1fr 1fr;
            gap: 16px
        }

        .form-hint {
            font-size: 11px;
            color: var(--ink-4);
            margin-top: 4px;
            font-family: var(--font-mono)
        }

        /* ─── GRID ─── */
        .two-col {
            display: grid;
            grid-template-columns:1fr 1fr;
            gap: 20px
        }

        .three-col {
            display: grid;
            grid-template-columns:repeat(3, 1fr);
            gap: 16px
        }

        /* ─── TABS ─── */
        .tab-bar {
            display: flex;
            gap: 2px;
            background: var(--paper-2);
            border-radius: var(--radius-md);
            padding: 3px;
            margin-bottom: 20px;
            width: fit-content
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
            font-family: var(--font-sans)
        }

        .tab-btn.active {
            background: var(--white);
            color: var(--ink);
            box-shadow: var(--shadow-sm)
        }

        /* ─── CHART ─── */
        .chart-area {
            height: 160px;
            display: flex;
            align-items: flex-end;
            gap: 4px;
            padding: 0 4px
        }

        .chart-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px
        }

        .chart-bar {
            width: 100%;
            border-radius: 3px 3px 0 0;
            transition: all .3s;
            cursor: pointer
        }

        .chart-bar:hover {
            filter: brightness(.85)
        }

        .chart-bar.accent-fill {
            background: var(--accent-light)
        }

        .chart-bar.accent-fill:hover {
            background: var(--accent)
        }

        .chart-bar.highlight.accent-fill {
            background: var(--accent)
        }

        .chart-bar.blue-fill {
            background: var(--blue-light)
        }

        .chart-bar.blue-fill:hover {
            background: var(--blue)
        }

        .chart-bar.highlight.blue-fill {
            background: var(--blue)
        }

        .chart-bar.green-fill {
            background: var(--green-light)
        }

        .chart-bar.green-fill:hover {
            background: var(--green)
        }

        .chart-bar.highlight.green-fill {
            background: var(--green)
        }

        .chart-bar.purple-fill {
            background: var(--purple-light)
        }

        .chart-bar.purple-fill:hover {
            background: var(--purple)
        }

        .chart-bar.highlight.purple-fill {
            background: var(--purple)
        }

        .chart-label {
            font-size: 9px;
            font-family: var(--font-mono);
            color: var(--ink-4)
        }

        /* ─── MINI SPARKLINE ─── */
        .sparkline-wrap {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            height: 32px
        }

        .spark-bar {
            width: 6px;
            border-radius: 2px 2px 0 0;
            min-height: 2px;
            transition: height .3s
        }

        /* ─── CTR PILL ─── */
        .ctr-pill {
            display: inline-flex;
            align-items: center;
            padding: 2px 7px;
            border-radius: 20px;
            font-size: 10px;
            font-family: var(--font-mono);
            font-weight: 500
        }

        .ctr-good {
            background: var(--green-light);
            color: var(--green)
        }

        .ctr-mid {
            background: var(--amber-light);
            color: var(--amber)
        }

        .ctr-low {
            background: var(--paper-3);
            color: var(--ink-3)
        }

        /* ─── PROGRESS ─── */
        .progress-wrap {
            margin: 6px 0
        }

        .progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 4px
        }

        .progress-bar-bg {
            height: 6px;
            background: var(--paper-2);
            border-radius: 3px;
            overflow: hidden
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width .6s cubic-bezier(.4, 0, .2, 1)
        }

        .progress-bar-fill.green {
            background: var(--green)
        }

        .progress-bar-fill.accent {
            background: var(--accent)
        }

        .progress-bar-fill.blue {
            background: var(--blue)
        }

        .progress-bar-fill.purple {
            background: var(--purple)
        }

        .progress-bar-fill.teal {
            background: var(--teal)
        }

        /* ─── TOGGLE ─── */
        .toggle {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 20px
        }

        .toggle input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: var(--border);
            border-radius: 20px;
            cursor: pointer;
            transition: background var(--transition)
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
            box-shadow: var(--shadow-sm)
        }

        .toggle input:checked + .toggle-slider {
            background: var(--green)
        }

        .toggle input:checked + .toggle-slider::before {
            transform: translateX(16px)
        }

        /* ─── ALERT ─── */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            border-left: 3px solid;
            margin-bottom: 16px
        }

        .alert-success {
            background: var(--green-light);
            border-color: var(--green);
            color: var(--green)
        }

        .alert-warning {
            background: var(--amber-light);
            border-color: var(--amber);
            color: var(--amber)
        }

        .alert-info {
            background: var(--blue-light);
            border-color: var(--blue);
            color: var(--blue)
        }

        /* ─── PRODUCT GRID ─── */
        .product-grid {
            display: grid;
            grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px
        }

        .product-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: box-shadow var(--transition)
        }

        .product-card:hover {
            box-shadow: var(--shadow-md)
        }

        .product-img {
            height: 140px;
            background: var(--paper-2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            position: relative
        }

        .product-badges {
            position: absolute;
            top: 8px;
            left: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px
        }

        .product-info {
            padding: 12px
        }

        .product-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--ink);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .product-cat {
            font-size: 10.5px;
            color: var(--ink-4);
            font-family: var(--font-mono);
            margin-bottom: 8px
        }

        .product-price {
            font-family: var(--font-mono);
            font-size: 15px;
            font-weight: 500;
            color: var(--ink)
        }

        .product-sale {
            color: var(--accent)
        }

        .product-orig {
            font-size: 11px;
            color: var(--ink-4);
            text-decoration: line-through
        }

        .product-actions {
            display: flex;
            gap: 6px;
            margin-top: 10px
        }

        /* ─── REVIEW ─── */
        .review-item {
            padding: 16px 0;
            border-bottom: 1px solid var(--paper-2)
        }

        .review-item:last-child {
            border-bottom: none
        }

        .review-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px
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
            color: var(--ink-3)
        }

        .review-author {
            font-size: 13px;
            font-weight: 500
        }

        .review-date {
            font-size: 11px;
            color: var(--ink-4);
            font-family: var(--font-mono);
            margin-left: auto
        }

        .stars {
            color: #f59e0b;
            font-size: 13px;
            letter-spacing: 1px
        }

        .review-title {
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 3px
        }

        .review-body {
            font-size: 13px;
            color: var(--ink-3);
            line-height: 1.55
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
            border: 1px solid var(--border)
        }

        /* ─── VOUCHER ─── */
        .voucher-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            display: flex;
            overflow: hidden;
            margin-bottom: 12px;
            transition: box-shadow var(--transition)
        }

        .voucher-card:hover {
            box-shadow: var(--shadow-sm)
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
            flex-shrink: 0
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
            background-size: 24px 24px
        }

        .voucher-pct {
            font-family: var(--font-mono);
            font-size: 22px;
            font-weight: 500;
            color: white
        }

        .voucher-off {
            font-size: 9px;
            color: rgba(255, 255, 255, .4);
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .1em
        }

        .voucher-right {
            flex: 1;
            padding: 14px 16px 14px 24px;
            display: flex;
            align-items: center;
            gap: 16px
        }

        .voucher-code {
            font-family: var(--font-mono);
            font-size: 16px;
            font-weight: 500;
            letter-spacing: 2px;
            color: var(--ink)
        }

        .voucher-meta {
            font-size: 11px;
            color: var(--ink-4);
            margin-top: 2px
        }

        .voucher-actions {
            margin-left: auto;
            display: flex;
            gap: 6px;
            align-items: center
        }

        /* ─── COMMISSION ─── */
        .commission-summary {
            display: grid;
            grid-template-columns:1fr 1px 1fr 1px 1fr;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 20px
        }

        .commission-block {
            padding: 20px
        }

        .commission-divider {
            background: var(--border)
        }

        .commission-label {
            font-size: 10px;
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--ink-4);
            margin-bottom: 6px
        }

        .commission-value {
            font-family: var(--font-serif);
            font-size: 24px;
            letter-spacing: -.3px
        }

        .commission-note {
            font-size: 11px;
            color: var(--ink-4);
            margin-top: 4px
        }

        /* ─── OFFER LIST ─── */
        .offer-list-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--paper-2)
        }

        .offer-list-item:last-child {
            border-bottom: none
        }

        .offer-img {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: var(--paper-2);
            display: grid;
            place-items: center;
            font-size: 20px;
            flex-shrink: 0
        }

        .offer-details {
            flex: 1;
            min-width: 0
        }

        .offer-name {
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .offer-dates {
            font-size: 11px;
            color: var(--ink-4);
            font-family: var(--font-mono);
            margin-top: 2px
        }

        .offer-price {
            text-align: right
        }

        .offer-sale {
            font-family: var(--font-mono);
            font-size: 15px;
            color: var(--accent);
            font-weight: 500
        }

        .offer-orig {
            font-size: 11px;
            color: var(--ink-4);
            text-decoration: line-through;
            font-family: var(--font-mono)
        }

        /* ─── BOOST ─── */
        .boost-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 18px;
            cursor: pointer;
            transition: all var(--transition);
            position: relative
        }

        .boost-card:hover {
            border-color: var(--accent);
            box-shadow: var(--shadow-md)
        }

        .boost-card.selected {
            border-color: var(--accent);
            background: #fff8f7
        }

        .boost-card-label {
            font-size: 11px;
            font-family: var(--font-mono);
            color: var(--ink-3);
            margin-bottom: 4px
        }

        .boost-card-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 8px
        }

        .boost-card-desc {
            font-size: 12px;
            color: var(--ink-3);
            line-height: 1.5
        }

        .boost-card-price {
            margin-top: 12px;
            font-family: var(--font-mono);
            font-size: 18px;
            color: var(--accent);
            font-weight: 500
        }

        .boost-card-per {
            font-size: 10px;
            color: var(--ink-4)
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
            place-items: center
        }

        .boost-card.selected .selected-check {
            display: grid
        }

        .range-wrap {
            margin: 16px 0
        }

        .range-labels {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            font-family: var(--font-mono);
            color: var(--ink-4);
            margin-top: 6px
        }

        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            height: 4px;
            border-radius: 2px;
            background: linear-gradient(to right, var(--accent) 0%, var(--accent) 50%, var(--border) 50%, var(--border) 100%);
            outline: none;
            cursor: pointer
        }

        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--white);
            border: 2px solid var(--accent);
            box-shadow: var(--shadow-sm);
            cursor: pointer
        }

        /* ─── ANALYTICS SPECIFIC ─── */
        .analytics-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px
        }

        .analytics-header h2 {
            font-family: var(--font-serif);
            font-size: 22px;
            letter-spacing: -.3px
        }

        .window-selector {
            display: flex;
            gap: 2px;
            background: var(--paper-2);
            border-radius: var(--radius-md);
            padding: 3px
        }

        .window-btn {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--ink-3);
            transition: all var(--transition);
            font-family: var(--font-mono)
        }

        .window-btn.active {
            background: var(--white);
            color: var(--ink);
            box-shadow: var(--shadow-sm)
        }

        .analytics-chart-wrap {
            height: 200px;
            display: flex;
            align-items: stretch;
            gap: 2px;
            padding: 0 2px
        }

        .analytics-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            gap: 2px
        }

        .analytics-bar {
            width: 100%;
            border-radius: 2px 2px 0 0;
            transition: all .25s;
            cursor: pointer;
            min-height: 2px
        }

        .analytics-bar:hover {
            filter: brightness(.8)
        }

        .bar-value-label {
            font-size: 9px;
            font-family: var(--font-mono);
            color: var(--ink-2);
            white-space: nowrap;
            line-height: 1;
            margin-bottom: 2px;
            min-height: 11px;
            text-align: center
        }

        .chart-section {
            padding: 20px;
            border-bottom: 1px solid var(--border)
        }

        .chart-section:last-child {
            border-bottom: none
        }

        .chart-section-label {
            font-size: 11px;
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--ink-3);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px
        }

        .chart-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0
        }

        .trend-indicator {
            font-size: 10px;
            font-family: var(--font-mono);
            padding: 2px 6px;
            border-radius: 4px
        }

        .trend-up {
            background: var(--green-light);
            color: var(--green)
        }

        .trend-down {
            background: var(--accent-light);
            color: var(--accent)
        }

        .trend-flat {
            background: var(--paper-3);
            color: var(--ink-3)
        }

        /* ─── MISC ─── */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px
        }

        ::-webkit-scrollbar-track {
            background: transparent
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--ink-4)
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 14, 13, .5);
            backdrop-filter: blur(4px);
            z-index: 500;
            display: none;
            align-items: center;
            justify-content: center
        }

        .modal-overlay.open {
            display: flex
        }

        .modal {
            background: var(--white);
            border-radius: var(--radius-lg);
            width: 520px;
            max-width: 95vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: slideUp 220ms cubic-bezier(.4, 0, .2, 1)
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px)
            }
            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .modal-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px
        }

        .modal-title {
            font-family: var(--font-serif);
            font-size: 18px;
            color: var(--ink)
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
            transition: all var(--transition)
        }

        .modal-close:hover {
            background: var(--paper-2);
            color: var(--ink)
        }

        .modal-body {
            padding: 24px
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 8px
        }

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
            gap: 10px
        }

        .notif.show {
            transform: translateY(0);
            opacity: 1
        }

        .notif-icon {
            font-size: 15px
        }

        .flex {
            display: flex
        }

        .flex-col {
            flex-direction: column
        }

        .items-center {
            align-items: center
        }

        .gap-2 {
            gap: 8px
        }

        .gap-3 {
            gap: 12px
        }

        .ml-auto {
            margin-left: auto
        }

        .mt-1 {
            margin-top: 4px
        }

        .mt-2 {
            margin-top: 8px
        }

        .mt-3 {
            margin-top: 12px
        }

        .mb-3 {
            margin-bottom: 12px
        }

        .mb-4 {
            margin-bottom: 16px
        }

        .mb-6 {
            margin-bottom: 24px
        }

        .text-sm {
            font-size: 12px
        }

        .text-xs {
            font-size: 11px;
            color: var(--ink-4);
            font-family: var(--font-mono)
        }

        .font-mono {
            font-family: var(--font-mono)
        }

        .section-title {
            font-family: var(--font-serif);
            font-size: 20px;
            margin-bottom: 16px
        }

        .inline-flex {
            display: inline-flex;
            align-items: center;
            gap: 6px
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0 12px;
            gap: 8px;
            width: 240px;
            transition: border-color var(--transition), box-shadow var(--transition)
        }

        .search-bar:focus-within {
            border-color: var(--ink-3);
            box-shadow: 0 0 0 3px rgba(15, 14, 13, .06)
        }

        .search-bar svg {
            color: var(--ink-4);
            flex-shrink: 0
        }

        .search-bar input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 13px;
            font-family: var(--font-sans);
            color: var(--ink);
            width: 100%;
            padding: 8px 0
        }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--ink-4)
        }

        .empty-state-icon {
            font-size: 36px;
            margin-bottom: 12px
        }

        .empty-state-text {
            font-size: 14px;
            color: var(--ink-3);
            margin-bottom: 4px
        }

        .empty-state-sub {
            font-size: 12px;
            font-family: var(--font-mono)
        }

        .mobile-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: var(--ink);
            border-top: 1px solid rgba(255, 255, 255, .08);
            z-index: 200;
            padding: 0 4px;
            align-items: center;
            justify-content: space-around;
            gap: 2px;
        }

        .mobile-nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 6px 4px;
            border-radius: var(--radius);
            cursor: pointer;
            color: rgba(255, 255, 255, .4);
            font-size: 9px;
            font-family: var(--font-mono);
            letter-spacing: .03em;
            text-transform: uppercase;
            transition: color var(--transition), background var(--transition);
            position: relative;
            user-select: none;
            text-align: center;
            min-width: 0;
            border: none;
            background: transparent;
        }

        .mobile-nav-item svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .mobile-nav-item.active {
            color: var(--accent);
        }

        .mobile-nav-item .mob-badge {
            position: absolute;
            top: 4px;
            right: 50%;
            transform: translateX(6px);
            background: var(--accent);
            color: white;
            font-size: 8px;
            font-family: var(--font-mono);
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            line-height: 1;
        }

        /* Hamburger button — shown only on mobile */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink-2);
            padding: 6px;
            border-radius: var(--radius);
            transition: background var(--transition);
            flex-shrink: 0;
        }

        .sidebar-toggle:hover {
            background: var(--paper-2);
        }

        /* Overlay for sidebar drawer on mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 14, 13, .55);
            backdrop-filter: blur(2px);
            z-index: 99;
        }

        .sidebar-overlay.open {
            display: block;
        }

        /* ── BREAKPOINTS ─────────────────────────── */

        /* ── 1280px: tighten stats grid ─────────── */
        @media (max-width: 1280px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .three-col {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* ── 1024px: collapse sidebar width ─────── */
        @media (max-width: 1024px) {
            :root {
                --sidebar-w: 200px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-grid-3 {
                grid-template-columns: repeat(2, 1fr);
            }

            .three-col {
                grid-template-columns: 1fr 1fr;
            }

            .commission-summary {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
            }

            .commission-divider {
                display: none;
            }

            .commission-block {
                border-bottom: 1px solid var(--border);
            }

            .commission-block:last-child {
                border-bottom: none;
            }
        }

        /* ── 768px: drawer sidebar, bottom nav ───── */
        @media (max-width: 768px) {
            /* Show toggle and mobile nav */
            .sidebar-toggle {
                display: flex;
                align-items: center;
            }

            .mobile-nav {
                display: flex;
            }

            /* Push content above bottom nav */
            body {
                padding-bottom: 60px;
            }

            /* Sidebar becomes a fixed drawer */
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                z-index: 100;
                transform: translateX(-100%);
                transition: transform 260ms cubic-bezier(.4, 0, .2, 1);
                width: 260px;
                overflow-y: auto;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            /* Topbar */
            .topbar {
                padding: 0 16px;
                gap: 10px;
            }

            .topbar-title {
                font-size: 16px;
            }

            .topbar-breadcrumb {
                display: none;
            }

            /* Hide topbar search & secondary btn on mobile */
            .topbar .search-bar {
                display: none;
            }

            .topbar .btn-secondary:not(.keep-mobile) {
                display: none;
            }

            /* Content */
            .content-area {
                padding: 16px;
            }

            /* Grids → single column */
            .stats-grid,
            .stats-grid-3,
            .two-col,
            .three-col {
                grid-template-columns: 1fr;
            }

            /* Offer/commission horizontal → vertical */
            .commission-summary {
                grid-template-columns: 1fr;
            }

            /* Form rows → stack */
            .form-row {
                grid-template-columns: 1fr;
            }

            /* Revenue chart — shorter */
            .chart-area {
                height: 110px;
            }

            .analytics-chart-wrap {
                height: 130px;
            }

            /* Product grid — 2 cols on tablet */
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 12px;
            }

            /* Tables: let them scroll */
            .table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 480px;
                font-size: 12px;
            }

            thead th,
            tbody td {
                padding: 9px 12px;
            }

            /* Voucher cards: allow wrapping */
            .voucher-right {
                flex-wrap: wrap;
                gap: 8px;
            }

            .voucher-actions {
                flex-wrap: wrap;
            }

            /* Modals: full-screen */
            .modal {
                width: 100%;
                max-width: 100%;
                margin: 0;
                border-radius: var(--radius-lg) var(--radius-lg) 0 0;
                max-height: 92vh;
                position: fixed;
                bottom: 0;
                left: 0;
            }

            .modal-overlay {
                align-items: flex-end;
            }

            /* Tab bars: allow horizontal scroll */
            .tab-bar {
                overflow-x: auto;
                overflow-y: hidden;
                scrollbar-width: none;
                flex-shrink: 0;
            }

            .tab-bar::-webkit-scrollbar {
                display: none;
            }

            .tab-btn {
                white-space: nowrap;
                flex-shrink: 0;
            }

            /* Offers row: stack */
            .offer-list-item {
                flex-wrap: wrap;
                gap: 8px;
            }

            .offer-price {
                margin-left: auto;
            }

            /* Balance card: smaller text */
            .balance-amount {
                font-size: 16px;
            }

            /* Notif: full width */
            .notif {
                left: 16px;
                right: 16px;
                bottom: 72px;
            }
        }

        /* ── 480px: single column tight ─────────── */
        @media (max-width: 480px) {
            .topbar {
                padding: 0 12px;
                gap: 8px;
            }

            .topbar-title {
                font-size: 15px;
            }

            .content-area {
                padding: 12px;
            }

            .stat-value {
                font-size: 22px;
            }

            .stat-card {
                padding: 14px;
            }

            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .product-img {
                height: 110px;
                font-size: 28px;
            }

            .card-header {
                padding: 12px 14px;
                flex-wrap: wrap;
                gap: 8px;
            }

            .card-body {
                padding: 14px;
            }

            .btn {
                padding: 6px 11px;
                font-size: 12px;
            }

            .btn-sm {
                padding: 4px 8px;
                font-size: 11px;
            }

            /* Hide label text in mobile nav to save space */
            .mobile-nav-item span {
                display: none;
            }

            .mobile-nav-item {
                gap: 0;
            }

            table {
                min-width: 420px;
            }
        }

        /* ── Landscape phone ─────────────────────── */
        @media (max-width: 768px) and (orientation: landscape) {
            .modal {
                border-radius: 0;
                position: fixed;
                inset: 0;
                max-height: 100vh;
            }

            .modal-overlay {
                align-items: center;
            }

            body {
                padding-bottom: 0;
            }

            .mobile-nav {
                position: relative;
            }
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

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<nav class="mobile-nav" id="mobile-nav">
    <button class="mobile-nav-item active" data-panel="overview" onclick="mobileNavigate('overview')">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="1" y="1" width="6" height="6" rx="1"/>
            <rect x="9" y="1" width="6" height="6" rx="1"/>
            <rect x="1" y="9" width="6" height="6" rx="1"/>
            <rect x="9" y="9" width="6" height="6" rx="1"/>
        </svg>
        <span>Overview</span>
    </button>
    <button class="mobile-nav-item" data-panel="products" onclick="mobileNavigate('products')">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M2 4l6-2 6 2v8l-6 2-6-2V4z"/>
        </svg>
        <span>Products</span>
    </button>
    <button class="mobile-nav-item" data-panel="offers" onclick="mobileNavigate('offers')">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="8" cy="8" r="6"/>
            <path d="M5 8l2 2 4-4"/>
        </svg>
        <span>Offers</span>
    </button>
    <button class="mobile-nav-item" data-panel="analytics" onclick="mobileNavigate('analytics')">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M2 12l3-4 3 2 3-5 3 3"/>
        </svg>
        <span>Analytics</span>
    </button>
    <button class="mobile-nav-item" onclick="toggleSidebar()">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M2 4h12M2 8h12M2 12h12"/>
        </svg>
        <span>More</span>
    </button>
</nav>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">
            <div class="brand-icon">M</div>
            <div>
                <div class="brand-name">Merchant Hub</div>
                <div class="brand-sub">Dashboard v2.5</div>
            </div>
        </div>
    </div>

    <div class="merchant-tag">
        <div class="merchant-avatar"><?= $initials ?></div>
        <div class="merchant-info">
            <div class="merchant-name"><?= htmlspecialchars($merchant->name) ?></div>
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
        <a class="nav-item" data-panel="analytics">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M2 12l3-4 3 2 3-5 3 3"/>
                <circle cx="2" cy="12" r="1" fill="currentColor" stroke="none"/>
                <circle cx="5" cy="8" r="1" fill="currentColor" stroke="none"/>
                <circle cx="8" cy="10" r="1" fill="currentColor" stroke="none"/>
                <circle cx="11" cy="5" r="1" fill="currentColor" stroke="none"/>
                <circle cx="14" cy="8" r="1" fill="currentColor" stroke="none"/>
            </svg>
            Analytics
            <span class="nav-badge" style="background:var(--purple)">New</span>
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-label">Products</div>
        <a class="nav-item" data-panel="products">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M2 4l6-2 6 2v8l-6 2-6-2V4z"/>
            </svg>
            Products
            <span class="nav-badge"><?= $products->count() ?></span>
        </a>
        <a class="nav-item" data-panel="offers">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="8" cy="8" r="6"/>
                <path d="M5 8l2 2 4-4"/>
            </svg>
            Offers
            <?php if ($pendingOffersCount > 0): ?>
                <span class="nav-badge"><?= $pendingOffersCount ?></span>
            <?php endif ?>
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-label">Marketing</div>
        <a class="nav-item" data-panel="boost">
            <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M8 2L10.5 7H14L11 9.5l1 4L8 11l-4 2.5 1-4L2 7h3.5L8 2z"/>
            </svg>
            Boost
            <?php if ($activeBoostCount > 0): ?>
                <span class="nav-badge"><?= $activeBoostCount ?></span>
            <?php endif ?>
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
            <?php if ($pendingReviewCount > 0): ?>
                <span class="nav-badge" style="background:var(--amber)"><?= $pendingReviewCount ?></span>
            <?php endif ?>
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="balance-card">
            <div class="balance-label">Available Balance</div>
            <div class="balance-amount">£<?= number_format($commissionSummary['net_earnings'] ?? 0, 0) ?></div>
            <div class="balance-sub">Net earnings this month</div>
        </div>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <header class="topbar">
        <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Menu">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M2 4h12M2 8h12M2 12h12"/>
            </svg>
        </button>
        <div>
            <div class="topbar-title" id="page-title">Overview</div>
            <div class="topbar-breadcrumb text-xs"><?= htmlspecialchars($merchant->name) ?> → <span
                        id="page-breadcrumb">Dashboard</span></div>
        </div>
        <div class="topbar-actions">
            <div class="search-bar">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="7" cy="7" r="5"/>
                    <path d="M12 12l2 2"/>
                </svg>
                <input type="text" placeholder="Search…">
            </div>
            <button class="btn btn-secondary" onclick="openBoostModal('boost-modal')">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 2L10.5 7H14L11 9.5l1 4L8 11l-4 2.5 1-4L2 7h3.5L8 2z"/>
                </svg>
                New Boost
            </button>
            <button class="btn btn-primary" id="primary-action" onclick="openProductModal('product-modal')">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M8 3v10M3 8h10"/>
                </svg>
                Add Product
            </button>
        </div>
    </header>

    <div class="content-area">

        <!-- ══════════════════════════ OVERVIEW ══════════════════════════ -->
        <div class="panel active" id="panel-overview">
            <?php if ($activeBoostCount > 0): ?>
                <div class="alert alert-info">
                    <strong>Active Boosts:</strong> You have <?= $activeBoostCount ?> active
                    boost<?= $activeBoostCount !== 1 ? 's' : '' ?> running.
                    <a href="#" style="color:inherit;text-decoration:underline" onclick="navigate('boost')">Manage →</a>
                </div>
            <?php endif ?>

            <!-- Revenue / orders / impressions / rating -->
            <div class="stats-grid">
                <div class="stat-card accent">
                    <div class="stat-icon accent-bg">📦</div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">£<?= number_format($stats->totalRevenue, 0) ?></div>
                    <div class="stat-delta <?= $stats->revenueIsUp() ? 'up' : 'down' ?>">
                        <?= $stats->revenueIsUp() ? '↑' : '↓' ?> <?= abs($stats->revenueDelta) ?>% vs last month
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon green-bg">🛒</div>
                    <div class="stat-label">Orders</div>
                    <div class="stat-value"><?= number_format($stats->totalOrders) ?></div>
                    <div class="stat-delta <?= $stats->ordersIsUp() ? 'up' : 'down' ?>">
                        <?= $stats->ordersIsUp() ? '↑' : '↓' ?> <?= abs($stats->ordersDelta) ?>% vs last month
                    </div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-icon amber-bg">👁</div>
                    <div class="stat-label">Impressions</div>
                    <div class="stat-value"><?= number_format($stats->totalImpressions / 1000, 1) ?>k</div>
                    <div class="stat-delta <?= $stats->impressionsIsUp() ? 'up' : 'down' ?>">
                        <?= $stats->impressionsIsUp() ? '↑' : '↓' ?> <?= abs($stats->impressionsDelta) ?>% vs last month
                    </div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-icon blue-bg">⭐</div>
                    <div class="stat-label">Avg. Rating</div>
                    <div class="stat-value"><?= $stats->averageRating ?></div>
                    <div class="stat-delta <?= $stats->ratingIsUp() ? 'up' : 'down' ?>">
                        <?= $stats->ratingIsUp() ? '↑' : '↓' ?> <?= abs($stats->ratingDelta) ?> this month
                    </div>
                </div>
            </div>

            <!-- Engagement quick-stats (new) -->
            <div class="stats-grid mb-6">
                <div class="stat-card purple">
                    <div class="stat-icon purple-bg">🏷️</div>
                    <div class="stat-label">Offer Clicks (30d)</div>
                    <div class="stat-value"><?= number_format($analytics->offerClicks) ?></div>
                    <div class="stat-delta <?= $analytics->offerClickIsUp() ? 'up' : 'down' ?>">
                        <?= $analytics->offerClickIsUp() ? '↑' : '↓' ?> <?= abs($analytics->offerClickDelta) ?>% vs
                        prior 30d
                        &nbsp;·&nbsp; CTR <?= $analytics->offerCtr() ?>%
                    </div>
                </div>
                <div class="stat-card teal">
                    <div class="stat-icon teal-bg">🔖</div>
                    <div class="stat-label">Deal Clicks (30d)</div>
                    <div class="stat-value"><?= number_format($analytics->dealClicks) ?></div>
                    <div class="stat-delta <?= $analytics->dealClickIsUp() ? 'up' : 'down' ?>">
                        <?= $analytics->dealClickIsUp() ? '↑' : '↓' ?> <?= abs($analytics->dealClickDelta) ?>% vs prior
                        30d
                        &nbsp;·&nbsp; CTR <?= $analytics->dealCtr() ?>%
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon green-bg">👁</div>
                    <div class="stat-label">Product Views (30d)</div>
                    <div class="stat-value"><?= number_format($analytics->productViews) ?></div>
                    <div class="stat-delta <?= $analytics->productViewIsUp() ? 'up' : 'down' ?>">
                        <?= $analytics->productViewIsUp() ? '↑' : '↓' ?> <?= abs($analytics->productViewDelta) ?>% vs
                        prior 30d
                        &nbsp;·&nbsp; <?= number_format($analytics->productViewsUnique) ?> unique
                    </div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-icon amber-bg">📊</div>
                    <div class="stat-label">Total Engagement (30d)</div>
                    <div class="stat-value"><?= number_format($analytics->totalEngagement()) ?></div>
                    <div class="stat-delta" style="color:var(--ink-3)">
                        Clicks + views combined
                        &nbsp;·&nbsp; <a href="#" onclick="navigate('analytics')"
                                         style="color:inherit;text-decoration:underline">Details →</a>
                    </div>
                </div>
            </div>

            <div class="two-col mb-6">
                <!-- Revenue chart -->
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

                <!-- Active boosts -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Active Boosts</div>
                        <span class="nav-badge" style="background:var(--accent)"><?= $activeBoostCount ?> live</span>
                        <div class="card-actions">
                            <button class="btn btn-sm btn-secondary" onclick="navigate('boost')">Manage</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($activeBoosts->count() > 0): ?>
                            <?php foreach ($activeBoosts as $boost): ?>
                                <div style="margin-bottom:14px">
                                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                        <span style="font-size:13px;font-weight:500"><?= htmlspecialchars($boost->product->name ?? 'Unknown product') ?></span>
                                        <span class="badge badge-<?= $boost->context === 'listing' ? 'green' : 'amber' ?>"><?= ucfirst($boost->context) ?></span>
                                    </div>
                                    <div class="progress-wrap">
                                        <?php
                                        $impressions = $boost->impressions ?? 0;
                                        $daysLeft = now_datetime()->diffInDays($boost->ends_at, false);
                                        $totalDays = $boost->starts_at->diff($boost->ends_at)->days;
                                        $pct = $totalDays > 0 ? min(100, round((1 - $daysLeft / $totalDays) * 100)) : 0;
                                        ?>
                                        <div class="progress-labels">
                                            <span class="text-xs">Impressions: <?= number_format($impressions) ?></span>
                                            <span class="text-xs"><?= $daysLeft ?> days left</span>
                                        </div>
                                        <div class="progress-bar-bg">
                                            <div class="progress-bar-fill <?= $boost->context === 'listing' ? 'green' : 'accent' ?>"
                                                 style="width:<?= $pct ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">⚡</div>
                                <div class="empty-state-text">No active boosts</div>
                                <div class="empty-state-sub">Create one to increase visibility</div>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>

            <div class="two-col">
                <!-- Recent orders -->
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
                                    <?php
                                    $statusMap = ['completed' => 'badge-green', 'processing' => 'badge-blue', 'pending' => 'badge-amber', 'cancelled' => 'badge-red'];
                                    $statusBadge = $statusMap[$order->status->value ?? $order->status] ?? 'badge-gray';
                                    ?>
                                    <tr>
                                        <td class="font-mono text-xs">#<?= $order->id ?></td>
                                        <td><?= htmlspecialchars($order->items->first()?->product_name ?? '—') ?></td>
                                        <td class="font-mono">£<?= number_format($order->total, 2) ?></td>
                                        <td>
                                            <span class="badge <?= $statusBadge ?>"><?= ucfirst($order->status->value ?? $order->status) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;color:var(--ink-4);padding:24px">No recent
                                        orders
                                    </td>
                                </tr>
                            <?php endif ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top products -->
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
                        <?php if ($topProducts->count() > 0): ?>
                            <?php foreach ($topProducts as $i => $product): ?>
                                <div class="progress-wrap mt-2">
                                    <div class="progress-labels">
                                        <span style="font-size:12px;font-weight:500"><?= htmlspecialchars($product->name) ?></span>
                                        <span class="text-xs">£<?= number_format($product->revenue ?? 0) ?></span>
                                    </div>
                                    <div class="progress-bar-bg">
                                        <div class="progress-bar-fill <?= $barColors[$i] ?? 'accent' ?>"
                                             style="width:<?= round(($product->revenue / $maxRevenue) * 100) ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-text">No revenue data yet</div>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════ ANALYTICS ══════════════════════════ -->
        <div class="panel" id="panel-analytics">

            <?php
            // Encode series data for JS — safe for inline script
            $offerClicksJson = $analytics->offerClicksJson();
            $dealClicksJson = $analytics->dealClicksJson();
            $productViewsJson = $analytics->productViewsJson();
            ?>

            <div class="analytics-header">
                <div>
                    <h2>Engagement Analytics</h2>
                    <div class="text-xs mt-1">Offer clicks · Deal clicks · Product views — last <span
                                id="analytics-days-label"><?= $analytics->days ?></span> days
                    </div>
                </div>
                <div class="ml-auto flex gap-2 items-center">
                    <div class="window-selector">
                        <button class="window-btn <?= $analytics->days === 7 ? 'active' : '' ?>" data-days="7">7d
                        </button>
                        <button class="window-btn <?= $analytics->days === 30 ? 'active' : '' ?>" data-days="30">30d
                        </button>
                        <button class="window-btn <?= $analytics->days === 90 ? 'active' : '' ?>" data-days="90">90d
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="stats-grid mb-6">
                <div class="stat-card purple">
                    <div class="stat-icon purple-bg">🏷️</div>
                    <div class="stat-label">Offer Clicks</div>
                    <div class="stat-value"><?= number_format($analytics->offerClicks) ?></div>
                    <div class="stat-delta <?= $analytics->offerClickIsUp() ? 'up' : 'down' ?>">
                        <?= $analytics->offerClickIsUp() ? '↑' : '↓' ?> <?= abs($analytics->offerClickDelta) ?>% &nbsp;·&nbsp;
                        CTR <?= $analytics->offerCtr() ?>%
                    </div>
                </div>
                <div class="stat-card teal">
                    <div class="stat-icon teal-bg">🔖</div>
                    <div class="stat-label">Deal Clicks</div>
                    <div class="stat-value"><?= number_format($analytics->dealClicks) ?></div>
                    <div class="stat-delta <?= $analytics->dealClickIsUp() ? 'up' : 'down' ?>">
                        <?= $analytics->dealClickIsUp() ? '↑' : '↓' ?> <?= abs($analytics->dealClickDelta) ?>% &nbsp;·&nbsp;
                        CTR <?= $analytics->dealCtr() ?>%
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon green-bg">👁</div>
                    <div class="stat-label">Product Views</div>
                    <div class="stat-value"><?= number_format($analytics->productViews) ?></div>
                    <div class="stat-delta <?= $analytics->productViewIsUp() ? 'up' : 'down' ?>">
                        <?= $analytics->productViewIsUp() ? '↑' : '↓' ?> <?= abs($analytics->productViewDelta) ?>%
                        &nbsp;·&nbsp; <?= number_format($analytics->productViewsUnique) ?> unique
                    </div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-icon amber-bg">📊</div>
                    <div class="stat-label">Total Engagement</div>
                    <div class="stat-value"><?= number_format($analytics->totalEngagement()) ?></div>
                    <div class="stat-delta" style="color:var(--ink-3)">Clicks + views combined</div>
                </div>
            </div>

            <!-- Three stacked charts -->
            <div class="card mb-6">
                <!-- Offer Clicks chart -->
                <div class="chart-section">
                    <div class="chart-section-label">
                        <div class="chart-dot" style="background:var(--purple)"></div>
                        Offer Clicks
                        <?php
                        $oTrend = $analytics->offerClickDelta;
                        $oClass = $oTrend > 0 ? 'trend-up' : ($oTrend < 0 ? 'trend-down' : 'trend-flat');
                        $oArrow = $oTrend > 0 ? '↑' : ($oTrend < 0 ? '↓' : '→');
                        ?>
                        <span class="trend-indicator <?= $oClass ?>"><?= $oArrow ?> <?= abs($oTrend) ?>%</span>
                        <span class="text-xs" style="margin-left:4px">vs prior period</span>
                    </div>
                    <div class="analytics-chart-wrap" id="chart-offer-clicks"></div>
                </div>

                <!-- Deal Clicks chart -->
                <div class="chart-section">
                    <div class="chart-section-label">
                        <div class="chart-dot" style="background:var(--teal)"></div>
                        Deal Clicks
                        <?php
                        $dTrend = $analytics->dealClickDelta;
                        $dClass = $dTrend > 0 ? 'trend-up' : ($dTrend < 0 ? 'trend-down' : 'trend-flat');
                        $dArrow = $dTrend > 0 ? '↑' : ($dTrend < 0 ? '↓' : '→');
                        ?>
                        <span class="trend-indicator <?= $dClass ?>"><?= $dArrow ?> <?= abs($dTrend) ?>%</span>
                        <span class="text-xs" style="margin-left:4px">vs prior period</span>
                    </div>
                    <div class="analytics-chart-wrap" id="chart-deal-clicks"></div>
                </div>

                <!-- Product Views chart -->
                <div class="chart-section">
                    <div class="chart-section-label">
                        <div class="chart-dot" style="background:var(--green)"></div>
                        Product Views
                        <?php
                        $vTrend = $analytics->productViewDelta;
                        $vClass = $vTrend > 0 ? 'trend-up' : ($vTrend < 0 ? 'trend-down' : 'trend-flat');
                        $vArrow = $vTrend > 0 ? '↑' : ($vTrend < 0 ? '↓' : '→');
                        ?>
                        <span class="trend-indicator <?= $vClass ?>"><?= $vArrow ?> <?= abs($vTrend) ?>%</span>
                        <span class="text-xs" style="margin-left:4px">vs prior period</span>
                    </div>
                    <div class="analytics-chart-wrap" id="chart-product-views"></div>
                </div>
            </div>

            <!-- Breakdown tables -->
            <div class="three-col">

                <!-- Top offers by clicks -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Top Offers by Clicks</div>
                        <span class="badge badge-purple">last <?= $analytics->days ?>d</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Offer / Product</th>
                                <th>Clicks</th>
                                <th>CTR</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($analytics->topOffers)): ?>
                                <?php foreach ($analytics->topOffers as $row): ?>
                                    <?php
                                    $ctrClass = $row['ctr'] >= 5 ? 'ctr-good' : ($row['ctr'] >= 2 ? 'ctr-mid' : 'ctr-low');
                                    ?>
                                    <tr>
                                        <td style="font-weight:500;font-size:12px"><?= htmlspecialchars($row['product_name']) ?></td>
                                        <td class="font-mono"><?= number_format($row['clicks']) ?></td>
                                        <td><span class="ctr-pill <?= $ctrClass ?>"><?= $row['ctr'] ?>%</span></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;color:var(--ink-4);padding:20px">No offer
                                        click data yet
                                    </td>
                                </tr>
                            <?php endif ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top products by deal clicks -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Top Products — Deal Clicks</div>
                        <span class="badge badge-teal">last <?= $analytics->days ?>d</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th>Clicks</th>
                                <th>CTR</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($analytics->topDealProducts)): ?>
                                <?php foreach ($analytics->topDealProducts as $row): ?>
                                    <?php $ctrClass = $row['ctr'] >= 5 ? 'ctr-good' : ($row['ctr'] >= 2 ? 'ctr-mid' : 'ctr-low') ?>
                                    <tr>
                                        <td style="font-weight:500;font-size:12px"><?= htmlspecialchars($row['product_name']) ?></td>
                                        <td class="font-mono"><?= number_format($row['clicks']) ?></td>
                                        <td><span class="ctr-pill <?= $ctrClass ?>"><?= $row['ctr'] ?>%</span></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;color:var(--ink-4);padding:20px">No deal
                                        click data yet
                                    </td>
                                </tr>
                            <?php endif ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top products by views -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Top Products — Views</div>
                        <span class="badge badge-green">last <?= $analytics->days ?>d</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th>Views</th>
                                <th>Unique</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($analytics->topViewedProducts)): ?>
                                <?php foreach ($analytics->topViewedProducts as $row): ?>
                                    <tr>
                                        <td style="font-weight:500;font-size:12px"><?= htmlspecialchars($row['product_name']) ?></td>
                                        <td class="font-mono"><?= number_format($row['views']) ?></td>
                                        <td class="font-mono text-xs"><?= number_format($row['unique_users']) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;color:var(--ink-4);padding:20px">No view
                                        data yet
                                    </td>
                                </tr>
                            <?php endif ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /three-col -->

            <!-- Analytics chart data — rendered on first panel activation, not at parse time.
                 Charts built inside display:none get zero computed width so bars are invisible.
                 Deferring to navigate() ensures the panel is visible before innerHTML is set. -->
            <script>
                window.__analyticsData = {
                    offers: <?= $offerClicksJson  ?>,
                    deals: <?= $dealClicksJson   ?>,
                    views: <?= $productViewsJson ?>,
                };
            </script>
        </div>

        <!-- ══════════════════════════ PRODUCTS ══════════════════════════ -->
        <?php
        $activeProducts = $products->where('status', 'active');
        $draftProducts = $products->where('status', 'draft');
        $oosProducts = $products->where('status', 'out_of_stock');
        $statusBadgeMap = ['active' => 'badge-green', 'draft' => 'badge-gray', 'out_of_stock' => 'badge-red'];
        $statusLabel = ['active' => 'Active', 'draft' => 'Draft', 'out_of_stock' => 'Out of stock'];
        ?>
        <div class="panel" id="panel-products">
            <div class="flex items-center gap-2 mb-6">
                <div class="tab-bar" data-filter-target="product-grid">
                    <button class="tab-btn active" data-filter="all">All (<?= $products->count() ?>)</button>
                    <button class="tab-btn" data-filter="active">Active (<?= $activeProducts->count() ?>)</button>
                    <button class="tab-btn" data-filter="draft">Draft (<?= $draftProducts->count() ?>)</button>
                    <button class="tab-btn" data-filter="out_of_stock">Out of Stock (<?= $oosProducts->count() ?>)
                    </button>
                </div>
                <div class="ml-auto flex gap-2">
                    <div class="search-bar" style="width:200px">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                             stroke-width="1.8">
                            <circle cx="7" cy="7" r="5"/>
                            <path d="M12 12l2 2"/>
                        </svg>
                        <input type="text" id="product-search" placeholder="Search products…"
                               oninput="filterProducts()">
                    </div>
                    <button class="btn btn-primary" onclick="openModal('product-modal')">+ Add Product</button>
                </div>
            </div>
            <div class="product-grid" id="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php
                    $productStatus = $product->is_active ? 'active' : 'draft';
                    $isBoosted = $activeBoosts->contains('product_id', $product->id);

                    // Attach per-product analytics (views) as a sparkline
                    $productViews = collect($analytics->topViewedProducts)->firstWhere('product_id', $product->id);
                    ?>
                    <div class="product-card" data-status="<?= $productStatus ?>">
                        <div class="product-img">
                            📦
                            <div class="product-badges">
                                <span class="badge <?= $statusBadgeMap[$productStatus] ?? 'badge-gray' ?>"><?= $statusLabel[$productStatus] ?? ucfirst($productStatus) ?></span>
                                <?php if ($isBoosted): ?><span class="badge badge-red"
                                                               style="margin-top:3px">Boosted</span><?php endif ?>
                            </div>
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($product->name) ?></div>
                            <div class="product-cat"><?= htmlspecialchars($product->category->name ?? '—') ?></div>
                            <?php if ($productViews): ?>
                                <div style="font-size:10.5px;color:var(--green);font-family:var(--font-mono);margin-bottom:6px">
                                    👁 <?= number_format($productViews['views']) ?> views
                                    &nbsp;·&nbsp; <?= number_format($productViews['unique_users']) ?> unique
                                </div>
                            <?php endif ?>
                            <div class="product-price">
                                <?php if ($product->sale_price && $product->sale_price < $product->price): ?>
                                    <span class="product-sale">£<?= number_format($product->sale_price, 2) ?></span>
                                    <span class="product-orig">£<?= number_format($product->price, 2) ?></span>
                                <?php else: ?>
                                    <span>£<?= number_format($product->price, 2) ?></span>
                                <?php endif ?>
                            </div>
                            <div class="product-actions">
                                <?php if ($productStatus === 'draft'): ?>
                                    <button class="btn btn-sm btn-primary">Publish</button>
                                <?php elseif ($productStatus === 'out_of_stock'): ?>
                                    <button class="btn btn-sm btn-secondary">Restock</button>
                                <?php endif ?>
                                <button class="btn btn-sm btn-secondary edit-product-btn"
                                        data-id="<?= $product->id ?>"
                                        data-name="<?= htmlspecialchars($product->name, ENT_QUOTES) ?>"
                                        data-sku="<?= htmlspecialchars($product->sku ?? '', ENT_QUOTES) ?>"
                                        data-price="<?= $product->price ?>"
                                        data-sale-price="<?= $product->sale_price ?? '' ?>"
                                        data-stock="<?= $product->stock_quantity ?? '' ?>"
                                        data-description="<?= htmlspecialchars($product->description ?? '', ENT_QUOTES) ?>"
                                        data-url="<?= htmlspecialchars($product->url ?? '', ENT_QUOTES) ?>">Edit
                                </button>
                                <?php if ($productStatus === 'active'): ?>
                                    <button class="btn btn-sm btn-ghost" onclick="navigate('boost')">Boost</button>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>
                <div class="product-card"
                     style="border:2px dashed var(--border);background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;min-height:240px"
                     onclick="openProductModal(null)">
                    <div style="text-align:center;color:var(--ink-4)">
                        <div style="font-size:32px;margin-bottom:8px">+</div>
                        <div style="font-size:13px;font-weight:500">Add Product</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════ OFFERS ══════════════════════════ -->
        <?php
        $activeOffers = $offers->where('status', 'published');
        $offerStatusMap = ['published' => 'badge-green', 'pending' => 'badge-amber', 'expired' => 'badge-gray', 'paused' => 'badge-red'];
        ?>
        <div class="panel" id="panel-offers">
            <div class="flex items-center mb-6">
                <div class="tab-bar" data-filter-target="offer-list">
                    <button class="tab-btn active" data-filter="all">All Offers</button>
                    <button class="tab-btn" data-filter="published">Active</button>
                    <button class="tab-btn" data-filter="pending">Pending</button>
                    <button class="tab-btn" data-filter="expired">Expired</button>
                </div>
                <div class="ml-auto">
                    <button class="btn btn-primary" onclick="openOfferModal('offer-modal')">+ New Offer</button>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title">Active Offers</div>
                    <span class="badge badge-green"><?= $activeOffers->count() ?> live</span>
                </div>
                <div class="card-body" id="offer-list">
                    <?php if (count($offers) > 0): ?>
                        <?php foreach ($offers as $offer): ?>
                            <?php
                            $offerStatus = $offer->status->value ?? $offer->status;
                            $dateRange = ($offer->start_date && $offer->end_date)
                                    ? $offer->start_date->format('d M') . ' – ' . $offer->end_date->format('d M Y')
                                    : 'Ongoing · No expiry set';

                            // Attach click stats per offer
                            $offerStats = collect($analytics->topOffers)->firstWhere('offer_id', $offer->id);
                            ?>
                            <div class="offer-list-item" data-status="<?= $offerStatus ?>"
                                 data-offer-id="<?= $offer->id ?>" data-product-id="<?= $offer->product_id ?>">
                                <div class="offer-img">🏷️</div>
                                <div class="offer-details">
                                    <div class="offer-name"><?= htmlspecialchars($offer->product->name ?? '—') ?></div>
                                    <div class="offer-dates"><?= $dateRange ?></div>
                                    <?php if ($offerStats): ?>
                                        <div style="font-size:10.5px;color:var(--purple);font-family:var(--font-mono);margin-top:2px">
                                            <?= number_format($offerStats['clicks']) ?> clicks
                                            &nbsp;·&nbsp; <?= $offerStats['ctr'] ?>% CTR
                                        </div>
                                    <?php endif ?>
                                </div>
                                <div>
                                    <span class="badge <?= $offerStatusMap[$offerStatus] ?? 'badge-gray' ?>"><?= ucfirst($offerStatus) ?></span>
                                </div>
                                <div class="offer-price">
                                    <div class="offer-sale">£<?= number_format($offer->sale_price, 2) ?></div>
                                    <?php if (!empty($offer->original_price)): ?>
                                        <div class="offer-orig">£<?= number_format($offer->original_price, 2) ?></div>
                                    <?php endif ?>
                                </div>
                                <div class="flex gap-2">
                                    <button class="btn btn-sm btn-secondary edit-offer-btn"
                                            data-id="<?= $offer->id ?>"
                                            data-link="<?= $offer->link ?>"
                                            data-product-id="<?= $offer->product_id ?>"
                                            data-start-date="<?= $offer->start_date->format('Y-m-d') ?>"
                                            data-end-date="<?= $offer->end_date->format('Y-m-d') ?>"
                                            data-name="<?= htmlspecialchars($offer->product->name ?? '', ENT_QUOTES) ?>"
                                            data-sale-price="<?= $offer->sale_price ?>"
                                            data-original-price="<?= $offer->original_price ?? '' ?>"
                                            data-starts-at="<?= $offer->starts_at ? $offer->starts_at->format('Y-m-d') : '' ?>"
                                            data-ends-at="<?= $offer->ends_at ? $offer->ends_at->format('Y-m-d') : '' ?>"
                                            data-status="<?= $offerStatus ?>">Edit
                                    </button>
                                    <?php if ($offerStatus === 'published'): ?>
                                        <button class="btn btn-sm btn-danger" onclick="pauseOffer(this)">Pause</button>
                                    <?php elseif ($offerStatus === 'pending'): ?>
                                        <button class="btn btn-sm btn-primary">Approve</button>
                                    <?php endif ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🏷️</div>
                            <div class="empty-state-text">No offers yet</div>
                            <div class="empty-state-sub">Create an offer to promote a product</div>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════ BOOST ══════════════════════════ -->
        <?php
        $budgetUsed = $boostStats->budget_used ?? 0;
        $budgetTotal = $boostStats->budget_total ?? 200;
        $budgetPct = $budgetTotal > 0 ? round(($budgetUsed / $budgetTotal) * 100) : 0;
        $budgetRemaining = $budgetTotal - $budgetUsed;
        ?>
        <div class="panel" id="panel-boost">
            <div class="two-col mb-6">
                <div>
                    <?php if ($boostStats): ?>
                        <div class="alert alert-success"><strong>Auto Boost enabled.</strong> Budget:
                            £<?= number_format($budgetTotal) ?>/month · Used: £<?= number_format($budgetUsed) ?>
                            · <?= $activeBoostCount ?> products auto-boosted this cycle.
                        </div>
                    <?php endif ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="card-title">Auto Boost Settings</div>
                            <label class="toggle ml-auto"><input
                                        type="checkbox" <?= ($boostStats?->auto_boost_enabled ?? false) ? 'checked' : '' ?>><span
                                        class="toggle-slider"></span></label></div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group"><label class="form-label">Monthly Budget</label><input
                                            type="number" class="form-control"
                                            value="<?= $boostStats?->budget_total ?? 200 ?>">
                                    <div class="form-hint">Max spend per calendar month</div>
                                </div>
                                <div class="form-group"><label class="form-label">Goal</label><select
                                            class="form-control">
                                        <option>Maximise Revenue</option>
                                        <option>Promote Deals</option>
                                        <option>Clear Inventory</option>
                                    </select></div>
                            </div>
                            <button class="btn btn-secondary" onclick="window.location.href='/merchant-portal/boost'">
                                Manage Boosts
                            </button>
                            <button class="btn btn-primary" style="margin-left:8px"
                                    onclick="window.location.href='/merchant-portal/boost'">Open Boost Manager
                            </button>
                        </div>
                    </div>
                    <?php if ($boostStats): ?>
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Budget Usage</div>
                                <span class="text-xs"><?= now_datetime()->format('M Y') ?></span></div>
                            <div class="card-body">
                                <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                                    <span style="font-family:var(--font-mono);font-size:22px;font-weight:500">£<?= number_format($budgetUsed) ?><span
                                                style="font-size:13px;color:var(--ink-4)"> / £<?= number_format($budgetTotal) ?></span></span>
                                    <span class="badge badge-green"><?= $budgetPct ?>% used</span>
                                </div>
                                <div class="progress-bar-bg" style="height:8px">
                                    <div class="progress-bar-fill green"
                                         style="width:<?= $budgetPct ?>%;height:100%"></div>
                                </div>
                                <div class="text-xs mt-2">£<?= number_format($budgetRemaining) ?> remaining ·
                                    Resets <?= now_datetime()->endOfMonth()->diffForHumans() ?></div>
                            </div>
                        </div>
                    <?php endif ?>
                </div>
                <div>
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="card-title">Active Boosts</div>
                            <div class="card-actions">
                                <button class="btn btn-sm btn-primary" onclick="openBoostModal('boost-modal')">+ New
                                    Boost
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
                                            <td style="font-weight:500"><?= htmlspecialchars($boost->product()?->name ?? '—') ?></td>
                                            <td>
                                                <span class="badge <?= $boost->context === 'listing' ? 'badge-blue' : 'badge-amber' ?>"><?= ucfirst($boost->context) ?></span>
                                            </td>
                                            <td class="font-mono">×<?= number_format($boost->multiplier, 1) ?></td>
                                            <td class="font-mono"><?= number_format($boost->impressions ?? 0) ?></td>
                                            <td class="font-mono"><?= number_format($boost->clicks ?? 0) ?></td>
                                            <td><span class="badge badge-green">Active</span></td>
                                            <td class="text-xs"><?= $boost->ends_at?->format('d M') ?? '—' ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-danger pause-boost-btn"
                                                        data-boost-id="<?= $boost->id ?>">Pause
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center;color:var(--ink-4);padding:24px">No
                                            active boosts
                                        </td>
                                    </tr>
                                <?php endif ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════ VOUCHERS ══════════════════════════ -->
        <?php
        $activeVouchers = $vouchers->filter(fn($v) => ($v->status->value ?? $v->status) === 'active');
        $expiringSoon = $activeVouchers->filter(fn($v) => $v->expires_at && now_datetime()->diffInDays($v->expires_at) <= 7);
        ?>
        <div class="panel" id="panel-vouchers">
            <div class="flex items-center mb-6">
                <div class="tab-bar" data-filter-target="voucher-list">
                    <button class="tab-btn active" data-filter="all">All Vouchers</button>
                    <button class="tab-btn" data-filter="active">Active</button>
                    <button class="tab-btn" data-filter="expired">Expired</button>
                </div>
                <div class="ml-auto">
                    <button class="btn btn-primary" onclick="openModal('voucher-modal')">+ New Voucher</button>
                </div>
            </div>
            <div class="two-col mb-6">
                <div class="stat-card green">
                    <div class="stat-label">Active Codes</div>
                    <div class="stat-value"><?= $activeVouchers->count() ?></div>
                    <div class="stat-delta"><?= $expiringSoon->count() ?> expiring within 7 days</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-label">Total Vouchers</div>
                    <div class="stat-value"><?= $vouchers->count() ?></div>
                    <div class="stat-delta">Across all statuses</div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Voucher Codes</div>
                </div>
                <div class="card-body" id="voucher-list">
                    <?php if (count($vouchers) > 0): ?>
                        <?php foreach ($vouchers as $voucher): ?>
                            <?php
                            $vStatus = $voucher->status->value ?? $voucher->status;
                            $isExpiringSoon = $voucher->expires_at && now_datetime()->diffInDays($voucher->expires_at) <= 7 && $vStatus === 'active';
                            $voucherBgColor = $voucher->discount_type === 'fixed' ? 'var(--blue)' : 'var(--ink)';
                            $discountDisplay = $voucher->discount_type === 'percentage' ? $voucher->value . '%' : '£' . number_format($voucher->value);
                            $usageMeta = $voucher->usage_limit ? "Used {$voucher->times_used}/{$voucher->usage_limit}" : 'Unlimited uses';
                            $expMeta = $voucher->expires_at ? 'Expires ' . $voucher->expires_at->format('d M Y') : 'No expiry';
                            ?>
                            <div class="voucher-card" data-status="<?= $vStatus ?>" data-id="<?= $voucher->id ?>">
                                <div class="voucher-left" style="background:<?= $voucherBgColor ?>">
                                    <div class="voucher-pct"><?= $discountDisplay ?></div>
                                    <div class="voucher-off">off</div>
                                </div>
                                <div class="voucher-right">
                                    <div>
                                        <div class="voucher-code"><?= htmlspecialchars($voucher->code) ?></div>
                                        <div class="voucher-meta"><?= $expMeta ?> · <?= $usageMeta ?></div>
                                    </div>
                                    <div class="voucher-actions">
                                        <?php if ($isExpiringSoon): ?><span class="badge badge-red">Expiring soon</span>
                                        <?php elseif ($vStatus === 'active'): ?><span
                                                class="badge badge-green">Active</span>
                                        <?php elseif ($vStatus === 'expired'): ?><span
                                                class="badge badge-gray">Expired</span>
                                        <?php else: ?><span class="badge badge-gray"><?= ucfirst($vStatus) ?></span>
                                        <?php endif ?>
                                        <button class="btn btn-sm btn-secondary"
                                                onclick="copyCode('<?= $voucher->code ?>')">Copy
                                        </button>
                                        <?php if ($vStatus === 'expired'): ?>
                                            <button class="btn btn-sm btn-ghost">Clone</button>
                                        <?php elseif ($isExpiringSoon): ?>
                                            <button class="btn btn-sm btn-danger deactivate-voucher-btn"
                                                    data-id="<?= $voucher->id ?>">Deactivate
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-ghost edit-voucher-btn"
                                                    data-id="<?= $voucher->id ?>"
                                                    data-code="<?= htmlspecialchars($voucher->code, ENT_QUOTES) ?>"
                                                    data-type="<?= $voucher->discount_type ?? 'percentage' ?>"
                                                    data-value="<?= $voucher->value ?? '' ?>"
                                                    data-limit="<?= $voucher->usage_limit ?? '' ?>"
                                                    data-min="<?= $voucher->minimum_order_value ?? '' ?>"
                                                    data-expires="<?= $voucher->expires_at ? $voucher->expires_at->format('Y-m-d') : '' ?>"
                                                    data-stackable="<?= ($voucher->is_stackable ?? true) ? '1' : '0' ?>">
                                                Edit
                                            </button>
                                        <?php endif ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🎟️</div>
                            <div class="empty-state-text">No vouchers yet</div>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════ COMMISSION ══════════════════════════ -->
        <div class="panel" id="panel-commission">
            <div class="commission-summary">
                <div class="commission-block">
                    <div class="commission-label">Total Gross Sales</div>
                    <div class="commission-value">£<?= number_format($commissionSummary['gross_sales'] ?? 0) ?></div>
                    <div class="commission-note">This month</div>
                </div>
                <div class="commission-divider"></div>
                <div class="commission-block">
                    <div class="commission-label">Commission Deducted</div>
                    <div class="commission-value" style="color:var(--accent)">
                        £<?= number_format($commissionSummary['commission_total'] ?? 0) ?></div>
                    <div class="commission-note">Avg <?= $commissionSummary['blended_rate'] ?? 0 ?>% blended rate</div>
                </div>
                <div class="commission-divider"></div>
                <div class="commission-block">
                    <div class="commission-label">Net Earnings</div>
                    <div class="commission-value" style="color:var(--green)">
                        £<?= number_format($commissionSummary['net_earnings'] ?? 0) ?></div>
                    <div class="commission-note">Available for payout</div>
                </div>
            </div>
            <div class="two-col">
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
                                <?php foreach ($commissionRates as $rate): ?>
                                    <tr>
                                        <td class="font-mono"><?= $rate['commission_rate'] ?>%</td>
                                        <td><?= $rate['product_names'] ?></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align:center;color:var(--ink-4);padding:24px">No rate
                                        data
                                    </td>
                                </tr><?php endif ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
                                <?php foreach ($commissionByProduct as $item): ?>
                                    <tr>
                                        <td style="font-weight:500"><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td class="font-mono">£<?= number_format($item['revenue']) ?></td>
                                        <td class="font-mono"><?= $item['avg_rate'] ?>%</td>
                                        <td class="font-mono" style="color:var(--accent)">
                                            £<?= number_format($item['commission_amount']) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;color:var(--ink-4);padding:24px">No
                                        commission data
                                    </td>
                                </tr><?php endif ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════ INVOICES ══════════════════════════ -->
        <?php $txBadgeMap = fn($s) => match ($s) {
            'completed', 'paid' => 'badge-green',
            'processing', 'pending' => 'badge-amber',
            default => 'badge-gray'
        }; ?>
        <div class="panel" id="panel-invoices">
            <div class="flex items-center mb-6">
                <div class="tab-bar" data-filter-target="invoice-tbody">
                    <button class="tab-btn active" data-filter="all">All</button>
                    <button class="tab-btn" data-filter="completed">Paid</button>
                    <button class="tab-btn" data-filter="pending">Pending</button>
                    <button class="tab-btn" data-filter="overdue">Overdue</button>
                </div>
                <div class="ml-auto">
                    <button class="btn btn-secondary" onclick="exportInvoicesCsv()">↓ Export CSV</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Invoice History</div>
                    <div class="card-sub">Last 12 months</div>
                </div>
                <div class="table-wrap">
                    <table id="invoice-table">
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
                        <tbody id="invoice-tbody">
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach ($transactions as $tx): ?>
                                <?php $txStatus = $tx->status->value ?? $tx->status; ?>
                                <tr data-status="<?= $txStatus ?>">
                                    <td class="font-mono text-xs"><?= $tx->reference ?? ('TXN-' . str_pad($tx->id, 6, '0', STR_PAD_LEFT)) ?></td>
                                    <td><?= optional($tx->period_start)?->format('M Y') ?? '—' ?></td>
                                    <td class="font-mono">£<?= number_format($tx->gross_amount ?? 0, 0) ?></td>
                                    <td class="font-mono" style="color:var(--accent)">
                                        £<?= number_format($tx->commission_amount ?? 0, 0) ?></td>
                                    <td class="font-mono" style="color:var(--green);font-weight:500">
                                        £<?= number_format($tx->net_amount ?? 0, 0) ?></td>
                                    <td>
                                        <span class="badge <?= $txBadgeMap($txStatus) ?>"><?= ucfirst($txStatus) ?></span>
                                    </td>
                                    <td class="text-xs"><?= optional($tx->created_at)?->format('d M') ?? '—' ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-ghost">View</button>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:var(--ink-4);padding:24px">No
                                    transactions found
                                </td>
                            </tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════ REVIEWS ══════════════════════════ -->
        <div class="panel" id="panel-reviews">
            <div class="stats-grid-3">
                <div class="stat-card green">
                    <div class="stat-label">Avg Rating</div>
                    <div class="stat-value"><?= $stats->averageRating ?></div>
                    <div class="stat-delta up"><?= $reviewStats['total'] ?? 0 ?> approved reviews</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-label">Pending Response</div>
                    <div class="stat-value"><?= $reviewStats['pending_response'] ?? 0 ?></div>
                    <div class="stat-delta">Awaiting your reply</div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-label">This Month</div>
                    <div class="stat-value"><?= $reviewStats['this_month'] ?? 0 ?></div>
                    <?php $reviewDelta = ($reviewStats['this_month'] ?? 0) - ($reviewStats['previous_month'] ?? 0) ?>
                    <div class="stat-delta <?= $reviewDelta >= 0 ? 'up' : 'down' ?>"><?= $reviewDelta >= 0 ? '↑' : '↓' ?> <?= abs($reviewDelta) ?>
                        vs last month
                    </div>
                </div>
            </div>
            <div class="two-col">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Recent Reviews</div>
                        <div class="card-actions">
                            <div class="tab-bar" style="margin:0" data-filter-target="reviews-list">
                                <button class="tab-btn active" data-filter="all">All</button>
                                <button class="tab-btn" data-filter="pending">Pending</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="reviews-list">
                        <?php if (count($recentReviews) > 0): ?>
                            <?php foreach ($recentReviews as $review): ?>
                                <?php
                                $nameParts = explode(' ', $review->reviewer_name ?? 'Unknown');
                                $avatarInitials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice($nameParts, 0, 2)));
                                $starsFilled = str_repeat('★', (int)$review->rating);
                                $starsEmpty = str_repeat('☆', 5 - (int)$review->rating);
                                $needsReply = empty($review->merchant_reply);
                                ?>
                                <div class="review-item" data-status="<?= $needsReply ? 'pending' : 'replied' ?>">
                                    <div class="review-header">
                                        <div class="review-avatar"><?= $avatarInitials ?></div>
                                        <div>
                                            <div class="review-author"><?= $review->reviewer_name ?? 'Anonymous' ?></div>
                                            <div class="stars"><?= $starsFilled ?><span
                                                        style="color:var(--paper-3)"><?= $starsEmpty ?></span></div>
                                        </div>
                                        <div class="review-date"><?= $review->created_at->format('d M Y') ?></div>
                                    </div>
                                    <?php if ($review->title): ?>
                                        <div class="review-title"><?= htmlspecialchars($review->title) ?></div><?php endif ?>
                                    <div class="review-body"><?= htmlspecialchars($review->comment) ?></div>
                                    <div class="review-product">
                                        📦 <?= $review->product_name ?? $review->product?->name ?? '—' ?></div>
                                    <div style="margin-top:10px;display:flex;gap:6px">
                                        <button class="btn btn-sm <?= $needsReply ? 'btn-primary' : 'btn-secondary' ?>"
                                                onclick="showNotif('Reply sent')"><?= $needsReply ? 'Reply (Pending)' : 'Reply' ?></button>
                                        <button class="btn btn-sm btn-ghost">Flag</button>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">💬</div>
                                <div class="empty-state-text">No reviews yet</div>
                            </div><?php endif ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Rating Breakdown</div>
                    </div>
                    <div class="card-body">
                        <div style="margin-bottom:20px">
                            <div style="font-family:var(--font-serif);font-size:48px;text-align:center;letter-spacing:-2px"><?= $stats->averageRating ?></div>
                            <div style="text-align:center;color:#f59e0b;font-size:20px;margin-bottom:4px">
                                <?php $rounded = (int)round($stats->averageRating);
                                echo str_repeat('★', $rounded) . str_repeat('☆', 5 - $rounded); ?>
                            </div>
                            <div style="text-align:center;font-size:12px;color:var(--ink-4);font-family:var(--font-mono)"><?= $reviewStats['total'] ?? 0 ?>
                                reviews total
                            </div>
                        </div>
                        <?php
                        $distribution = $reviewStats['rating_distribution'] ?? [];
                        $distColors = [5 => 'green', 4 => 'green', 3 => 'amber', 2 => 'accent', 1 => 'accent'];
                        ?>
                        <?php
                        $distTotal = array_sum($distribution);
                        foreach ([5, 4, 3, 2, 1] as $star):
                            $starCount = $distribution[$star] ?? 0;
                            // distribution may be counts or percentages — normalise to %
                            $pct = $distTotal > 0
                                    ? ($starCount > 100 ? round(($starCount / $distTotal) * 100) : $starCount)
                                    : 0;
                            ?>
                            <div class="progress-wrap mt-2">
                                <div class="progress-labels">
                                    <span class="text-xs"><?= $star ?> ★</span>
                                    <span class="text-xs"
                                          style="color:var(--ink-3)"><?= $starCount ?> (<?= $pct ?>%)</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill <?= $distColors[$star] ?>"
                                         style="width:<?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════ MODALS ══════════════════════════ -->
        <div class="modal-overlay" id="modal-product-modal">
            <div class="modal">
                <div class="modal-header"><span>📦</span>
                    <div class="modal-title">Add / Edit Product</div>
                    <button class="modal-close" onclick="closeModal('product-modal')">✕</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="product-modal-id">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Product Name</label><input type="text"
                                                                                                     id="product-modal-name"
                                                                                                     class="form-control"
                                                                                                     placeholder="e.g. USB-C Hub Pro 7-in-1">
                        </div>
                        <div class="form-group"><label class="form-label">SKU</label><input type="text"
                                                                                            id="product-modal-sku"
                                                                                            class="form-control"
                                                                                            placeholder="e.g. TCH-001">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Price (£)</label><input type="number"
                                                                                                  id="product-modal-price"
                                                                                                  class="form-control"
                                                                                                  placeholder="64.99">
                        </div>
                        <div class="form-group"><label class="form-label">Sale Price (£)</label><input type="number"
                                                                                                       id="product-modal-sale-price"
                                                                                                       class="form-control"
                                                                                                       placeholder="49.99">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Category</label><select
                                    id="product-modal-category" class="form-control">
                                <option value="">Loading categories…</option>
                            </select></div>
                        <div class="form-group"><label class="form-label">Stock Quantity</label><input type="number"
                                                                                                       id="product-modal-stock"
                                                                                                       class="form-control"
                                                                                                       placeholder="100">
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Description</label><textarea
                                id="product-modal-desc" class="form-control"
                                placeholder="Product description…"></textarea></div>
                    <div class="form-group"><label class="form-label">Product URL</label><input type="url"
                                                                                                id="product-modal-url"
                                                                                                class="form-control"
                                                                                                placeholder="https://yourstore.com/product">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('product-modal')">Cancel</button>
                    <button class="btn btn-primary" id="product-modal-save" onclick="saveProduct()">Save Product
                    </button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="modal-boost-modal">
            <div class="modal">
                <div class="modal-header"><span>⚡</span>
                    <div class="modal-title">Create Boost</div>
                    <button class="modal-close" onclick="closeModal('boost-modal')">✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label class="form-label">Select Product to Boost <span
                                    style="color:var(--accent)">*</span></label><select
                                id="boost-modal-product" class="form-control">
                            <option value="">Loading products…</option>
                        </select></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Boost Type</label><select
                                    id="boost-modal-type" class="form-control">
                                <option value="">— Select Type —</option>
                                <option value="product">Product</option>
                                <option value="offer">Offer</option>
                            </select></div>
                        <div class="form-group"><label class="form-label">Context</label><select
                                    id="boost-modal-context" class="form-control">
                                <option value="">— Select Context —</option>
                                <option value="listing">Listing</option>
                                <option value="deals">Deals</option>
                                <option value="recommendations">Recommendations</option>
                            </select></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
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
                            <div class="boost-card-desc">Feature prominently on the deals &amp; offers page</div>
                            <div class="boost-card-price">£2.00 <span class="boost-card-per">/day</span></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Start Date</label><input type="date"
                                                                                                   id="boost-modal-start"
                                                                                                   class="form-control">
                        </div>
                        <div class="form-group"><label class="form-label">End Date</label><input type="date"
                                                                                                 id="boost-modal-end"
                                                                                                 class="form-control">
                        </div>
                    </div>
                    <div id="boost-modal-price-preview"
                         style="display:none;background:var(--paper);border-radius:var(--radius-md);padding:12px;border:1px solid var(--border);margin-bottom:12px;justify-content:space-between;font-size:13px">
                        <span>Estimated cost:</span><span id="boost-modal-price-amount"
                                                          style="font-family:var(--font-mono);color:var(--accent)"></span>
                    </div>
                    <div class="form-group"><label class="form-label">Multiplier <span id="mult-display"
                                                                                       style="font-family:var(--font-mono);color:var(--accent)">×1.5</span></label>
                        <div class="range-wrap"><input type="range" min="10" max="30" value="15"
                                                       id="boost-modal-multiplier"
                                                       oninput="updateMultiplier(this.value)">
                            <div class="range-labels"><span>×1.0</span><span>×2.0</span><span>×3.0</span></div>
                        </div>
                    </div>
                    <div style="background:var(--paper);border-radius:var(--radius-md);padding:12px;border:1px solid var(--border)">
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span>Duration:</span><span
                                    class="font-mono">7 days</span></div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:600;padding-top:6px;border-top:1px solid var(--border)">
                            <span>Est. Total:</span><span class="font-mono" style="color:var(--accent)">£10.50</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('boost-modal')">Cancel</button>
                    <button class="btn btn-primary" id="boost-modal-save" data-label="Create Boost"
                            onclick="saveBoost()">Create Boost
                    </button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="modal-offer-modal">
            <div class="modal">
                <div class="modal-header"><span>🏷️</span>
                    <div class="modal-title">Create / Edit Offer</div>
                    <button class="modal-close" onclick="closeModal('offer-modal')">✕</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="offer-modal-id">
                    <input type="hidden" id="offer-modal-product-id">
                    <div class="form-group"><label class="form-label">Offer Name</label><input type="text"
                                                                                               id="offer-modal-name"
                                                                                               class="form-control"
                                                                                               placeholder="e.g. Summer Sale 2025">
                    </div>
                    <div class="form-group"><label class="form-label">Product</label><select id="offer-modal-product"
                                                                                             class="form-control">
                            <option value="">Loading products…</option>
                        </select></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Original Price (£)</label><input type="number"
                                                                                                           id="offer-modal-orig-price"
                                                                                                           class="form-control">
                        </div>
                        <div class="form-group"><label class="form-label">Sale Price (£)</label><input type="number"
                                                                                                       id="offer-modal-sale-price"
                                                                                                       class="form-control">
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Offer Link <span
                                    style="color:var(--accent)">*</span></label><input type="url"
                                                                                       id="offer-modal-link"
                                                                                       class="form-control"
                                                                                       placeholder="https://yourstore.com/sale">
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Start Date <span
                                        style="color:var(--accent)">*</span></label><input type="date"
                                                                                           id="offer-modal-start"
                                                                                           class="form-control">
                        </div>
                        <div class="form-group"><label class="form-label">End Date <span
                                        style="color:var(--accent)">*</span></label><input type="date"
                                                                                           id="offer-modal-end"
                                                                                           class="form-control">
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Status</label><select id="offer-modal-status"
                                                                                            class="form-control">
                            <option value="pending">Pending</option>
                            <option value="published">Published</option>
                        </select></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('offer-modal')">Cancel</button>
                    <button class="btn btn-primary" id="offer-modal-save" onclick="saveOffer()">Submit Offer</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="modal-voucher-modal">
            <div class="modal">
                <div class="modal-header"><span>🎟️</span>
                    <div class="modal-title">Create Voucher</div>
                    <button class="modal-close" onclick="closeModal('voucher-modal')">✕</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="voucher-modal-id">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Voucher Code</label><input type="text"
                                                                                                     id="voucher-modal-code"
                                                                                                     class="form-control"
                                                                                                     placeholder="e.g. SAVE20"
                                                                                                     style="font-family:var(--font-mono);letter-spacing:2px">
                        </div>
                        <div class="form-group"><label class="form-label">Type</label><select id="voucher-modal-type"
                                                                                              class="form-control">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (£)</option>
                            </select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Discount Value</label><input type="number"
                                                                                                       id="voucher-modal-value"
                                                                                                       class="form-control"
                                                                                                       placeholder="20">
                        </div>
                        <div class="form-group"><label class="form-label">Usage Limit</label><input type="number"
                                                                                                    id="voucher-modal-limit"
                                                                                                    class="form-control"
                                                                                                    placeholder="50">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Min Order Value (£)</label><input
                                    type="number" id="voucher-modal-min" class="form-control" placeholder="0"></div>
                        <div class="form-group"><label class="form-label">Expires</label><input type="date"
                                                                                                id="voucher-modal-expires"
                                                                                                class="form-control">
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Applies To</label><select
                                id="voucher-modal-applies" class="form-control">
                            <option value="all">All Products</option>
                            <option value="product">Specific Product</option>
                            <option value="category">Category</option>
                        </select></div>
                    <div class="form-group" style="display:flex;align-items:center;gap:10px"><label
                                class="toggle"><input type="checkbox" id="voucher-modal-stackable" checked><span
                                    class="toggle-slider"></span></label><span style="font-size:13px">Stackable with other vouchers</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('voucher-modal')">Cancel</button>
                    <button class="btn btn-primary" id="voucher-modal-save" onclick="saveVoucher()">Save Voucher
                    </button>
                </div>
            </div>
        </div>

        <div class="notif" id="notif"><span class="notif-icon">✓</span><span id="notif-text">Done</span></div>

        <script>
            if (!localStorage.getItem('merchant_portal_auth_token')) {
                localStorage.setItem('merchant_portal_auth_token', '<?= $apiToken ?>')
            }
            /**
             * ═══════════════════════════════════════════════════════════════
             * MERCHANT PORTAL — CLASS-BASED JS LAYER
             *
             * Architecture:
             *   ApiClient         – authenticated fetch wrapper
             *   NotificationManager – toast notifications
             *   ModalManager      – open / close / keyboard escape
             *   NavigationManager – sidebar panel switching
             *   ChartManager      – revenue + analytics chart rendering
             *   ProductManager    – product modal + save + search/filter
             *   OfferManager      – offer modal + save + pause/unpause
             *   BoostManager      – boost modal + save + pause/cancel wired to API
             *   VoucherManager    – voucher modal + save
             *   TabManager        – tab-bar filter wiring
             *   SidebarManager    – mobile drawer
             *   DashboardManager  – orchestrator; wires everything together
             * ═══════════════════════════════════════════════════════════════
             */

                // ─── CONSTANTS (injected from PHP) ────────────────────────────
            const SITE = '<?= htmlspecialchars($siteSlug ?? 'site', ENT_QUOTES) ?>';
            const MERCHANT_ID = <?= (int)($merchant->id ?? 0) ?>;
            const SITE_ID = <?= (int)($siteId ?? 1) ?>;
            // Token is stored in a meta tag set by the server after login
            // so it never appears as a literal string in markup.
            // <meta name="api-token" content="<?= htmlspecialchars($apiToken ?? '') ?>">
            const API_TOKEN = document.querySelector('meta[name="api-token"]')?.content ?? '';

            // ══════════════════════════════════════════════════════════════
            // ApiClient
            // ══════════════════════════════════════════════════════════════
            class ApiClient {
                async request(method, path, body = null) {
                    const opts = {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + API_TOKEN,
                            'X-Site-Id': String(SITE_ID),
                        },
                    };
                    if (body !== null) opts.body = JSON.stringify(body);
                    const res = await fetch(path, opts);
                    if (!res.ok) {
                        const err = await res.json().catch(() => ({message: 'Request failed'}));
                        throw new Error(err.message || `Request failed (${res.status})`);
                    }
                    return res.json().catch(() => ({}));
                }

                get(path) {
                    return this.request('GET', path);
                }

                post(path, body) {
                    return this.request('POST', path, body);
                }

                put(path, body) {
                    return this.request('PUT', path, body);
                }

                delete(path) {
                    return this.request('DELETE', path);
                }
            }

            // ══════════════════════════════════════════════════════════════
            // NotificationManager
            // ══════════════════════════════════════════════════════════════
            class NotificationManager {
                #timer = null;

                show(msg, isError = false) {
                    const el = document.getElementById('notif');
                    const text = document.getElementById('notif-text');
                    if (!el || !text) return;
                    text.textContent = msg;
                    el.style.background = isError ? 'var(--accent-dark, #9e3620)' : '';
                    el.classList.add('show');
                    clearTimeout(this.#timer);
                    this.#timer = setTimeout(() => el.classList.remove('show'), 3200);
                }

                error(msg) {
                    this.show(msg, true);
                }

                success(msg) {
                    this.show(msg, false);
                }
            }

            // ══════════════════════════════════════════════════════════════
            // ModalManager
            // ══════════════════════════════════════════════════════════════
            class ModalManager {
                open(id) {
                    document.getElementById(`modal-${id}`)?.classList.add('open');
                }

                close(id) {
                    document.getElementById(`modal-${id}`)?.classList.remove('open');
                }

                init() {
                    // Click-outside to close
                    document.querySelectorAll('.modal-overlay').forEach(o =>
                        o.addEventListener('click', e => {
                            if (e.target === o) o.classList.remove('open');
                        })
                    );
                    // Escape key
                    document.addEventListener('keydown', e => {
                        if (e.key === 'Escape')
                            document.querySelectorAll('.modal-overlay.open')
                                .forEach(m => m.classList.remove('open'));
                    });
                }
            }

            // ══════════════════════════════════════════════════════════════
            // NavigationManager
            // ══════════════════════════════════════════════════════════════
            class NavigationManager {
                #panels = {
                    overview: {
                        title: 'Overview',
                        breadcrumb: 'Dashboard',
                        action: 'Add Product',
                        modal: 'product-modal'
                    },
                    analytics: {title: 'Analytics', breadcrumb: 'Analytics', action: null, modal: null},
                    products: {
                        title: 'Products',
                        breadcrumb: 'Products',
                        action: 'Add Product',
                        modal: 'product-modal'
                    },
                    offers: {title: 'Offers', breadcrumb: 'Offers', action: 'New Offer', modal: 'offer-modal'},
                    boost: {title: 'Boost', breadcrumb: 'Marketing → Boost', action: 'New Boost', modal: 'boost-modal'},
                    vouchers: {
                        title: 'Vouchers',
                        breadcrumb: 'Marketing → Vouchers',
                        action: 'New Voucher',
                        modal: 'voucher-modal'
                    },
                    commission: {title: 'Commission', breadcrumb: 'Finance → Commission', action: null, modal: null},
                    invoices: {title: 'Invoices', breadcrumb: 'Finance → Invoices', action: 'Export CSV', modal: null},
                    reviews: {title: 'Reviews', breadcrumb: 'Community → Reviews', action: null, modal: null},
                };

                #charts = null; // ChartManager ref, set by DashboardManager
                #product = null; // ProductManager ref
                #offer = null; // OfferManager ref
                #voucher = null; // VoucherManager ref
                #modal = null; // ModalManager ref
                #notif = null; // NotificationManager ref

                init(deps) {
                    Object.assign(this, {
                        '#charts': deps.charts, '#product': deps.product,
                        '#offer': deps.offer, '#voucher': deps.voucher,
                        '#modal': deps.modal, '#notif': deps.notif
                    });

                    document.querySelectorAll('.nav-item[data-panel]').forEach(el =>
                        el.addEventListener('click', () => this.navigate(el.dataset.panel))
                    );
                }

                navigate(key) {
                    // Panels
                    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
                    document.getElementById(`panel-${key}`)?.classList.add('active');

                    // Sidebar active state
                    document.querySelectorAll('.nav-item').forEach(el =>
                        el.classList.toggle('active', el.dataset.panel === key)
                    );

                    // Mobile nav active state
                    document.querySelectorAll('.mobile-nav-item[data-panel]').forEach(el =>
                        el.classList.toggle('active', el.dataset.panel === key)
                    );

                    if (key === 'analytics' && this['#charts']) this['#charts'].buildAnalyticsCharts();

                    const cfg = this.#panels[key];
                    if (!cfg) return;

                    document.getElementById('page-title').textContent = cfg.title;
                    document.getElementById('page-breadcrumb').textContent = cfg.breadcrumb;

                    const btn = document.getElementById('primary-action');
                    if (cfg.action) {
                        btn.style.display = '';
                        btn.textContent = '+ ' + cfg.action;

                        const openers = {
                            'product-modal': () => this['#product']?.openModal(null),
                            'offer-modal': () => this['#offer']?.openModal(null),
                            'voucher-modal': () => this['#voucher']?.openModal(null),
                        };

                        btn.onclick = cfg.modal
                            ? (openers[cfg.modal] || (() => this['#modal']?.open(cfg.modal)))
                            : () => this['#notif']?.show('Feature available in full integration');

                        if (key === 'invoices') btn.onclick = () => window.dashboardApp.exportInvoicesCsv();
                    } else {
                        btn.style.display = 'none';
                    }
                }
            }

            // ══════════════════════════════════════════════════════════════
            // ChartManager
            // ══════════════════════════════════════════════════════════════
            class ChartManager {
                #analyticsBuilt = false;

                buildRevenueChart() {
                    const el = document.getElementById('revenue-chart');
                    if (!el) return;
                    const values = [42, 58, 35, 72, 88, 65, 91, 78, 104, 84, 112, 96, 88, 128, 110, 95, 142, 118, 106, 130, 155, 132, 148, 168, 145, 172, 160, 188, 175, 195];
                    const max = Math.max(...values);
                    el.innerHTML = values.map((v, i) => `
                        <div class="chart-bar-wrap">
                            <div class="chart-bar accent-fill${i >= 27 ? ' highlight' : ''}" style="height:${(v / max) * 140}px" title="Day ${i + 1}: £${v * 18}"></div>
                            ${i % 5 === 0 ? `<span class="chart-label">${i + 1}</span>` : '<span class="chart-label"></span>'}
                        </div>`).join('');
                }

                #buildSingleChart(containerId, data, valueKey, color) {
                    const el = document.getElementById(containerId);
                    if (!el || !Array.isArray(data) || !data.length) return;
                    const values = data.map(d => Number(d[valueKey]) || 0);
                    const max = Math.max(...values, 1);
                    const step = Math.max(1, Math.ceil(data.length / 7));
                    el.innerHTML = data.map((d, i) => {
                        const barH = Math.max(3, Math.round((values[i] / max) * 155));
                        const alpha = i >= data.length - 7 ? 'ff' : '88';
                        const dateLabel = i % step === 0 ? String(d.date || '').slice(5) : '';
                        const valLabel = values[i] > 0 ? values[i].toLocaleString() : '';
                        return `<div class="analytics-bar-wrap">
                            <span class="bar-value-label">${valLabel}</span>
                            <div class="analytics-bar" style="height:${barH}px;background:${color}${alpha}"
                                 title="${d.date}: ${values[i].toLocaleString()}"></div>
                            <span class="chart-label">${dateLabel}</span>
                        </div>`;
                    }).join('');
                }

                buildAnalyticsCharts() {
                    if (this.#analyticsBuilt) return;
                    this.#analyticsBuilt = true;
                    const d = window.__analyticsData || {};
                    this.#buildSingleChart('chart-offer-clicks', d.offers || [], 'clicks', '#6b3fa0');
                    this.#buildSingleChart('chart-deal-clicks', d.deals || [], 'clicks', '#1a6b6b');
                    this.#buildSingleChart('chart-product-views', d.views || [], 'views', '#2a7a4b');
                }

                initChartTooltip() {
                    const tooltip = document.createElement('div');
                    tooltip.id = 'chart-tooltip';
                    tooltip.style.cssText = [
                        'position:fixed', 'pointer-events:none', 'background:var(--ink)', 'color:var(--white)',
                        'font-family:var(--font-mono)', 'font-size:11px', 'padding:6px 10px', 'border-radius:6px',
                        'box-shadow:0 2px 8px rgba(0,0,0,.25)', 'white-space:nowrap', 'z-index:9999',
                        'opacity:0', 'transition:opacity .1s', 'line-height:1.5',
                    ].join(';');
                    document.body.appendChild(tooltip);

                    document.addEventListener('mouseover', e => {
                        const bar = e.target.closest('.analytics-bar');
                        if (!bar) return;
                        const [datePart, countPart] = (bar.title || '').split(': ');
                        if (!datePart) return;
                        const d = new Date(datePart);
                        const dateStr = isNaN(d) ? datePart : d.toLocaleDateString('en-GB', {
                            weekday: 'short',
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric'
                        });
                        const wrap = bar.closest('.analytics-chart-wrap');
                        const label = wrap?.id === 'chart-offer-clicks' ? 'offer clicks'
                            : wrap?.id === 'chart-deal-clicks' ? 'deal clicks'
                                : wrap?.id === 'chart-product-views' ? 'views' : 'events';
                        tooltip.innerHTML = `<strong>${dateStr}</strong><br>${countPart || '0'} ${label}`;
                        tooltip.style.opacity = '1';
                    });
                    document.addEventListener('mousemove', e => {
                        if (tooltip.style.opacity === '0') return;
                        const tw = tooltip.offsetWidth, th = tooltip.offsetHeight;
                        tooltip.style.left = (e.clientX + 12 + tw > window.innerWidth ? e.clientX - tw - 8 : e.clientX + 12) + 'px';
                        tooltip.style.top = (e.clientY - 8 - th < 0 ? e.clientY + 8 : e.clientY - th - 8) + 'px';
                    });
                    document.addEventListener('mouseout', e => {
                        if (e.target.closest('.analytics-bar')) tooltip.style.opacity = '0';
                    });
                }

                initWindowSwitcher(notif) {
                    document.querySelectorAll('.window-btn').forEach(btn =>
                        btn.addEventListener('click', () => {
                            document.querySelectorAll('.window-btn').forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                            notif.show(`Loading ${btn.dataset.days}-day analytics…`);
                        })
                    );
                }
            }

            // ══════════════════════════════════════════════════════════════
            // ProductManager
            // ══════════════════════════════════════════════════════════════
            class ProductManager {
                #api = null;
                #modal = null;
                #notif = null;

                // Cache fetched categories
                #categoriesLoaded = false;

                init(api, modal, notif) {
                    this.#api = api;
                    this.#modal = modal;
                    this.#notif = notif;
                }

                openModal(data) {
                    const m = document.getElementById('modal-product-modal');
                    if (!m) return;
                    const set = (id, val) => {
                        const el = document.getElementById(id);
                        if (el) el.value = val ?? '';
                    };
                    set('product-modal-id', data?.id || '');
                    set('product-modal-name', data?.name || '');
                    set('product-modal-sku', data?.sku || '');
                    set('product-modal-price', data?.price || '');
                    set('product-modal-sale-price', data?.sale_price || '');
                    set('product-modal-stock', data?.stock || '');
                    set('product-modal-url', data?.url || '');
                    const desc = document.getElementById('product-modal-desc');
                    if (desc) desc.value = data?.description || '';

                    // Set category if present
                    const catSel = document.getElementById('product-modal-category');
                    if (catSel && data?.category_id) {
                        Array.from(catSel.options).forEach(o => {
                            o.selected = o.value == data.category_id;
                        });
                    }

                    m.querySelector('.modal-title').textContent = data?.id ? 'Edit Product' : 'Add Product';
                    const saveBtn = document.getElementById('product-modal-save');
                    if (saveBtn) saveBtn.dataset.label = data?.id ? 'Save Changes' : 'Save Product';

                    this.#loadCategories();
                    m.classList.add('open');
                }

                async #loadCategories() {
                    if (this.#categoriesLoaded) return;
                    const sel = document.getElementById('product-modal-category');
                    if (!sel) return;
                    try {
                        const data = await this.#api.get(`/api/${SITE}/categories?per_page=200`);
                        const items = data?.items ?? data?.data ?? data?.categories ?? [];
                        if (!items.length) return;
                        sel.innerHTML = '<option value="">— Select Category —</option>'
                            + items.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
                        this.#categoriesLoaded = true;
                    } catch {
                        // Non-critical; keep existing options
                    }
                }

                async save() {
                    const id = document.getElementById('product-modal-id')?.value;
                    const body = {
                        name: document.getElementById('product-modal-name')?.value,
                        sku: document.getElementById('product-modal-sku')?.value || null,
                        price: parseFloat(document.getElementById('product-modal-price')?.value) || 0,
                        sale_price: parseFloat(document.getElementById('product-modal-sale-price')?.value) || null,
                        stock_quantity: parseInt(document.getElementById('product-modal-stock')?.value) || null,
                        description: document.getElementById('product-modal-desc')?.value || null,
                        category_id: document.getElementById('product-modal-category')?.value || null,
                        merchants: [{
                            //name: document.getElementById('product-modal-name')?.value,
                            url: document.getElementById('product-modal-url')?.value || 'http://www.test.com',
                            price: parseFloat(document.getElementById('product-modal-price')?.value) || 0,
                            is_available: true,
                            id: MERCHANT_ID,
                            name: '<?= $merchant?->name ?>'
                        }],
                    };

                    if (!body.name) {
                        this.#notif.error('Product name is required.');
                        return;
                    }
                    if (!body.price) {
                        this.#notif.error('Price is required.');
                        return;
                    }

                    this.#setLoading('product-modal-save', true);
                    try {
                        const url = id ? `/api/${SITE}/products/${id}` : `/api/${SITE}/products`;
                        const method = id ? 'put' : 'post';
                        const res = await this.#api[method](url, body);
                        this.#modal.close('product-modal');
                        this.#notif.success(id ? 'Product updated' : 'Product created');
                        // Update card in DOM without reload
                        if (id) {
                            this.#updateProductCard(id, body);
                        } else {
                            // New product — append a placeholder card prompting refresh
                            // (full card requires server-side category/view data)
                            this.#appendNewProductCard(res?.data?.product ?? res?.product ?? body);
                        }
                    } catch (e) {
                        this.#notif.error('Error: ' + e.message);
                    } finally {
                        this.#setLoading('product-modal-save', false);
                    }
                }

                #updateProductCard(id, body) {
                    const btn = document.querySelector(`.edit-product-btn[data-id="${id}"]`);
                    if (!btn) return;
                    const card = btn.closest('.product-card');
                    if (!card) return;
                    const nameEl = card.querySelector('.product-name');
                    if (nameEl) nameEl.textContent = body.name;
                    const priceEl = card.querySelector('.product-price span');
                    if (priceEl) {
                        const price = body.sale_price && body.sale_price < body.price
                            ? `£${parseFloat(body.sale_price).toFixed(2)}`
                            : `£${parseFloat(body.price).toFixed(2)}`;
                        priceEl.textContent = price;
                    }
                    // Keep data attributes in sync for subsequent edits
                    btn.dataset.name = body.name;
                    btn.dataset.price = body.price;
                    btn.dataset.salePrice = body.sale_price ?? '';
                    btn.dataset.stock = body.stock_quantity ?? '';
                }

                #appendNewProductCard(product) {
                    const grid = document.getElementById('product-grid');
                    if (!grid || !product?.id) return;
                    const price = product.sale_price && product.sale_price < product.price
                        ? `<span class="product-sale">£${parseFloat(product.sale_price).toFixed(2)}</span><span class="product-orig">£${parseFloat(product.price).toFixed(2)}</span>`
                        : `<span>£${parseFloat(product.price || 0).toFixed(2)}</span>`;
                    const card = document.createElement('div');
                    card.className = 'product-card';
                    card.dataset.status = 'active';
                    card.innerHTML = `
                        <div class="product-img">📦<div class="product-badges"><span class="badge badge-green">Active</span></div></div>
                        <div class="product-info">
                            <div class="product-name">${this.esc(product.name)}</div>
                            <div class="product-cat">—</div>
                            <div class="product-price">${price}</div>
                            <div class="product-actions">
                                <button class="btn btn-sm btn-secondary edit-product-btn"
                                    data-id="${product.id}"
                                    data-name="${this.esc(product.name)}"
                                    data-price="${product.price}"
                                    data-sale-price="${product.sale_price ?? ''}"
                                    data-stock="${product.stock_quantity ?? ''}">Edit</button>
                            </div>
                        </div>`;
                    // Insert before the "add product" placeholder card
                    const placeholder = grid.querySelector('.product-card:not([data-status])');
                    grid.insertBefore(card, placeholder ?? null);
                }

                filterProducts() {
                    const q = (document.getElementById('product-search')?.value || '').toLowerCase();
                    const grid = document.getElementById('product-grid');
                    if (!grid) return;
                    grid.querySelectorAll('.product-card[data-status]').forEach(card => {
                        const name = (card.querySelector('.product-name')?.textContent || '').toLowerCase();
                        card.style.display = name.includes(q) ? '' : 'none';
                    });
                }

                #setLoading(btnId, loading) {
                    const btn = document.getElementById(btnId);
                    if (!btn) return;
                    btn.disabled = loading;
                    btn.textContent = loading ? 'Saving…' : (btn.dataset.label || btn.textContent);
                }

                esc(str) {
                    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                }
            }

            // ══════════════════════════════════════════════════════════════
            // OfferManager
            // ══════════════════════════════════════════════════════════════
            class OfferManager {
                #api = null;
                #modal = null;
                #notif = null;
                #productsLoaded = false;

                init(api, modal, notif) {
                    this.#api = api;
                    this.#modal = modal;
                    this.#notif = notif;
                }

                openModal(data) {
                    const m = document.getElementById('modal-offer-modal');
                    if (!m) return;
                    const set = (id, val) => {
                        const el = document.getElementById(id);
                        if (el) el.value = val ?? '';
                    };

                    set('offer-modal-id', data?.id || '');
                    set('offer-modal-product-id', data?.product_id || '');
                    set('offer-modal-orig-price', data?.original_price || '');
                    set('offer-modal-sale-price', data?.sale_price || '');
                    set('offer-modal-link', data?.link || '');
                    set('offer-modal-start', data?.start_date || data?.starts_at || '');
                    set('offer-modal-end', data?.end_date || data?.ends_at || '');

                    const statusSel = document.getElementById('offer-modal-status');
                    if (statusSel && data?.status) {
                        Array.from(statusSel.options).forEach(o => {
                            o.selected = o.value.toLowerCase() === data.status.toLowerCase();
                        });
                    }

                    m.querySelector('.modal-title').textContent = data?.id ? 'Edit Offer' : 'New Offer';
                    const saveBtn = document.getElementById('offer-modal-save');
                    if (saveBtn) saveBtn.dataset.label = 'Submit Offer';

                    this.#loadProducts(data?.product_id);
                    m.classList.add('open');
                }

                async #loadProducts(selectedId = null) {
                    if (this.#productsLoaded && !selectedId) return;
                    const sel = document.getElementById('offer-modal-product');
                    if (!sel) return;
                    try {
                        const data = await this.#api.get(`/api/merchants/${MERCHANT_ID}/products/search`);
                        const items = data?.items ?? data?.data ?? data?.products ?? [];
                        if (!items.length) return;
                        sel.innerHTML = '<option value="">— Select Product —</option>'
                            + items.map(p => `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${this.esc(p.name)}</option>`).join('');
                        this.#productsLoaded = true;
                    } catch {
                        // Non-critical; allow manual ID entry
                    }
                }

                async save() {
                    const id = document.getElementById('offer-modal-id')?.value;
                    const productId = document.getElementById('offer-modal-product')?.value
                        || document.getElementById('offer-modal-product-id')?.value;
                    const link = document.getElementById('offer-modal-link')?.value;
                    const salePrice = document.getElementById('offer-modal-sale-price')?.value;
                    const startDate = document.getElementById('offer-modal-start')?.value;
                    const endDate = document.getElementById('offer-modal-end')?.value;

                    if (!productId) {
                        this.#notif.error('Please select a product.');
                        return;
                    }
                    if (!salePrice) {
                        this.#notif.error('Sale price is required.');
                        return;
                    }
                    if (!link) {
                        this.#notif.error('Offer link is required.');
                        return;
                    }
                    if (!startDate) {
                        this.#notif.error('Start date is required.');
                        return;
                    }
                    if (!endDate) {
                        this.#notif.error('End date is required.');
                        return;
                    }

                    const body = {
                        sale_price: parseFloat(salePrice),
                        original_price: parseFloat(document.getElementById('offer-modal-orig-price')?.value) || null,
                        link,
                        start_date: startDate,
                        end_date: endDate,
                        status: document.getElementById('offer-modal-status')?.value || 'pending',
                        is_active: true,
                        merchant_id: MERCHANT_ID
                    };

                    this.#setLoading('offer-modal-save', true);
                    try {
                        const url = id
                            ? `/api/${SITE}/products/${productId}/offers/${id}`
                            : `/api/${SITE}/products/${productId}/offers`;
                        const method = id ? 'put' : 'post';
                        const res = await this.#api[method](url, body);
                        this.#modal.close('offer-modal');
                        this.#notif.success(id ? 'Offer updated' : 'Offer submitted for review');
                        if (id) {
                            this.#updateOfferRow(id, body);
                        } else {
                            const offer = res?.data?.offer ?? res?.offer ?? {
                                ...body,
                                id: res?.id,
                                product_id: productId
                            };
                            this.#appendNewOfferRow(offer, productId);
                        }
                    } catch (e) {
                        this.#notif.error('Error: ' + e.message);
                    } finally {
                        this.#setLoading('offer-modal-save', false);
                    }
                }

                #updateOfferRow(id, body) {
                    const btn = document.querySelector(`.edit-offer-btn[data-id="${id}"]`);
                    if (!btn) return;
                    const row = btn.closest('[data-status]');
                    if (!row) return;
                    // Update status badge
                    if (body.status) {
                        row.dataset.status = body.status;
                        const badge = row.querySelector('.badge');
                        if (badge) {
                            const map = {published: 'badge-green', pending: 'badge-gray', paused: 'badge-red'};
                            badge.className = `badge ${map[body.status] ?? 'badge-gray'}`;
                            badge.textContent = body.status.charAt(0).toUpperCase() + body.status.slice(1);
                        }
                    }
                    // Update price display
                    const priceEl = row.querySelector('.offer-price, [class*="price"]');
                    if (priceEl && body.sale_price) {
                        priceEl.textContent = `£${parseFloat(body.sale_price).toFixed(2)}`;
                    }
                    // Keep data attributes in sync
                    btn.dataset.salePrice = body.sale_price ?? btn.dataset.salePrice;
                    btn.dataset.originalPrice = body.original_price ?? btn.dataset.originalPrice;
                    btn.dataset.startDate = body.start_date ?? btn.dataset.startDate;
                    btn.dataset.endDate = body.end_date ?? btn.dataset.endDate;
                    btn.dataset.status = body.status ?? btn.dataset.status;
                }

                #appendNewOfferRow(offer, productId) {
                    const list = document.getElementById('offer-list');
                    if (!list) return;
                    const isEmpty = list.querySelector('.empty-state');
                    if (isEmpty) isEmpty.remove();

                    const salePrice = parseFloat(offer.sale_price || 0).toFixed(2);
                    const origPrice = offer.original_price ? `<div class="offer-orig">£${parseFloat(offer.original_price).toFixed(2)}</div>` : '';
                    const start = offer.start_date ? new Date(offer.start_date).toLocaleDateString('en-GB', {
                        day: '2-digit',
                        month: 'short'
                    }) : '';
                    const end = offer.end_date ? new Date(offer.end_date).toLocaleDateString('en-GB', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    }) : '';
                    const dateRange = start && end ? `${start} – ${end}` : 'Ongoing · No expiry set';
                    const id = offer.id ?? '';

                    const item = document.createElement('div');
                    item.className = 'offer-list-item';
                    item.dataset.status = 'pending';
                    item.dataset.offerId = id;
                    item.dataset.productId = productId;
                    item.innerHTML = `
                        <div class="offer-img">🏷️</div>
                        <div class="offer-details">
                            <div class="offer-name">${this.esc(offer.product_name ?? '—')}</div>
                            <div class="offer-dates">${dateRange}</div>
                        </div>
                        <div><span class="badge badge-gray">Pending</span></div>
                        <div class="offer-price">
                            <div class="offer-sale">£${salePrice}</div>
                            ${origPrice}
                        </div>
                        <div class="flex gap-2">
                            <button class="btn btn-sm btn-secondary edit-offer-btn"
                                data-id="${id}"
                                data-product-id="${productId}"
                                data-sale-price="${offer.sale_price ?? ''}"
                                data-original-price="${offer.original_price ?? ''}"
                                data-link="${this.esc(offer.link ?? '')}"
                                data-start-date="${offer.start_date ?? ''}"
                                data-end-date="${offer.end_date ?? ''}"
                                data-status="pending">Edit</button>
                        </div>`;
                    list.prepend(item);
                }

                async pauseOffer(btn) {
                    const row = btn.closest('[data-status]');
                    const offerId = row?.dataset.offerId;
                    const productId = row?.dataset.productId;
                    try {
                        if (offerId && productId) {
                            await this.#api.put(`/api/${SITE}/products/${productId}/offers/${offerId}`, {status: 'paused'});
                        }
                        if (row) {
                            row.dataset.status = 'paused';
                            const b = row.querySelector('.badge');
                            if (b) {
                                b.className = 'badge badge-red';
                                b.textContent = 'Paused';
                            }
                        }
                        btn.textContent = 'Unpause';
                        btn.classList.replace('btn-danger', 'btn-secondary');
                        btn.onclick = () => this.unpauseOffer(btn);
                        this.#notif.success('Offer paused');
                    } catch (e) {
                        this.#notif.error(e.message);
                    }
                }

                async unpauseOffer(btn) {
                    const row = btn.closest('[data-status]');
                    const offerId = row?.dataset.offerId;
                    const productId = row?.dataset.productId;
                    try {
                        if (offerId && productId) {
                            await this.#api.put(`/api/${SITE}/products/${productId}/offers/${offerId}`, {status: 'published'});
                        }
                        if (row) {
                            row.dataset.status = 'published';
                            const b = row.querySelector('.badge');
                            if (b) {
                                b.className = 'badge badge-green';
                                b.textContent = 'Published';
                            }
                        }
                        btn.textContent = 'Pause';
                        btn.classList.replace('btn-secondary', 'btn-danger');
                        btn.onclick = () => this.pauseOffer(btn);
                        this.#notif.success('Offer resumed');
                    } catch (e) {
                        this.#notif.error(e.message);
                    }
                }

                #setLoading(btnId, loading) {
                    const btn = document.getElementById(btnId);
                    if (!btn) return;
                    btn.disabled = loading;
                    btn.textContent = loading ? 'Saving…' : (btn.dataset.label || btn.textContent);
                }

                esc(str) {
                    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                }
            }

            // ══════════════════════════════════════════════════════════════
            // BoostManager  — wired to /api/boosts
            // ══════════════════════════════════════════════════════════════
            class BoostManager {
                #api = null;
                #modal = null;
                #notif = null;
                #productsLoaded = false;

                static #RATES = {
                    listing: {product: 5.00, offer: 6.00},
                    deals: {product: 8.00, offer: 9.60},
                    recommendations: {product: 3.00, offer: 3.60},
                };

                init(api, modal, notif) {
                    this.#api = api;
                    this.#modal = modal;
                    this.#notif = notif;
                    this.#bindPricePreview();
                }

                openModal(data = null) {
                    const m = document.getElementById('modal-boost-modal');
                    if (!m) return;
                    // Reset form
                    ['boost-modal-type', 'boost-modal-context', 'boost-modal-start', 'boost-modal-end']
                        .forEach(id => {
                            const el = document.getElementById(id);
                            if (el) el.value = '';
                        });
                    const mult = document.getElementById('boost-modal-multiplier');
                    if (mult) {
                        mult.value = 15;
                        this.#updateMultiplierDisplay(15);
                    }
                    document.getElementById('boost-modal-price-preview')?.style.setProperty('display', 'none');

                    this.#loadProducts();
                    m.classList.add('open');
                }

                async #loadProducts() {
                    if (this.#productsLoaded) return;
                    const sel = document.getElementById('boost-modal-product');
                    if (!sel) return;
                    try {
                        const data = await this.#api.get(`/api/merchants/${MERCHANT_ID}/products/search`);
                        const items = data?.items ?? data?.data ?? data?.products ?? [];
                        if (!items.length) return;
                        sel.innerHTML = '<option value="">— Select Product —</option>'
                            + items.map(p => `<option value="${p.id}" data-type="product">${this.esc(p.name)}</option>`).join('');
                        this.#productsLoaded = true;
                    } catch {
                        // Non-critical
                    }
                }

                async save() {
                    const productId = document.getElementById('boost-modal-product')?.value;
                    const context = document.getElementById('boost-modal-context')?.value;
                    const start = document.getElementById('boost-modal-start')?.value;
                    const end = document.getElementById('boost-modal-end')?.value;
                    const multiplier = parseFloat(document.getElementById('boost-modal-multiplier')?.value || 15) / 10;

                    if (!productId) {
                        this.#notif.error('Please select a product to boost.');
                        return;
                    }
                    if (!context) {
                        this.#notif.error('Please select a boost context.');
                        return;
                    }
                    if (!start) {
                        this.#notif.error('Start date is required.');
                        return;
                    }
                    if (!end) {
                        this.#notif.error('End date is required.');
                        return;
                    }

                    const body = {
                        boostable_type: 'product',
                        target_id: parseInt(productId),
                        merchant_id: MERCHANT_ID,
                        context,
                        starts_at: start,
                        ends_at: end,
                        multiplier,
                    };

                    this.#setLoading('boost-modal-save', true);
                    try {
                        const res = await this.#api.post('/api/boosts', body);
                        this.#modal.close('boost-modal');
                        this.#notif.success('Boost created — pending activation');
                        const boost = res?.data?.boost ?? res?.boost ?? {...body, id: res?.id};
                        this.#appendNewBoostRow(boost);
                    } catch (e) {
                        this.#notif.error('Error: ' + e.message);
                    } finally {
                        this.#setLoading('boost-modal-save', false);
                    }
                }

                async pauseBoost(boostId) {
                    try {
                        await this.#api.post(`/api/boosts/${boostId}/pause`, {});
                        this.#notif.success('Boost paused');
                        return true;
                    } catch (e) {
                        this.#notif.error(e.message);
                        return false;
                    }
                }

                async cancelBoost(boostId) {
                    if (!confirm('Cancel this boost? This cannot be undone.')) return;
                    try {
                        await this.#api.post(`/api/boosts/${boostId}/cancel`, {});
                        this.#notif.success('Boost cancelled');
                        return true;
                    } catch (e) {
                        this.#notif.error(e.message);
                        return false;
                    }
                }

                #appendNewBoostRow(boost) {
                    const tbody = document.querySelector('#panel-boost table tbody');
                    if (!tbody) return;
                    // Remove "no active boosts" placeholder if present
                    const placeholder = tbody.querySelector('td[colspan]');
                    if (placeholder) placeholder.closest('tr').remove();

                    const productName = document.getElementById('boost-modal-product')?.selectedOptions?.[0]?.text ?? '—';
                    const context = boost.context ?? '—';
                    const multiplier = parseFloat(boost.multiplier ?? 1.5).toFixed(1);
                    const endsAt = boost.ends_at ? new Date(boost.ends_at).toLocaleDateString('en-GB', {
                        day: '2-digit',
                        month: 'short'
                    }) : '—';
                    const badgeClass = context === 'listing' ? 'badge-blue' : 'badge-amber';
                    const id = boost.id ?? '';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="font-weight:500">${this.esc(productName)}</td>
                        <td><span class="badge ${badgeClass}">${this.esc(context.charAt(0).toUpperCase() + context.slice(1))}</span></td>
                        <td class="font-mono">×${multiplier}</td>
                        <td class="font-mono">0</td>
                        <td class="font-mono">0</td>
                        <td><span class="badge badge-amber">Pending</span></td>
                        <td class="text-xs">${endsAt}</td>
                        <td>
                            <button class="btn btn-sm btn-danger pause-boost-btn" data-boost-id="${id}">Pause</button>
                        </td>`;
                    tbody.prepend(tr);
                }

                #bindPricePreview() {
                    ['boost-modal-type', 'boost-modal-context', 'boost-modal-start', 'boost-modal-end'].forEach(id =>
                        document.getElementById(id)?.addEventListener('change', () => this.#updatePrice())
                    );
                    document.getElementById('boost-modal-multiplier')?.addEventListener('input', e => {
                        this.#updateMultiplierDisplay(e.target.value);
                        this.#updatePrice();
                    });
                }

                #updatePrice() {
                    const type = document.getElementById('boost-modal-type')?.value;
                    const ctx = document.getElementById('boost-modal-context')?.value;
                    const start = document.getElementById('boost-modal-start')?.value;
                    const end = document.getElementById('boost-modal-end')?.value;
                    const preview = document.getElementById('boost-modal-price-preview');
                    if (!type || !ctx || !start || !end || !preview) {
                        if (preview) preview.style.display = 'none';
                        return;
                    }
                    const days = Math.ceil(Math.abs((new Date(end) - new Date(start)) / 86400000));
                    if (days <= 0) {
                        preview.style.display = 'none';
                        return;
                    }
                    const rate = (BoostManager.#RATES[ctx] || {})[type] || 0;
                    const total = (rate * days).toLocaleString('en-GB', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    const amtEl = document.getElementById('boost-modal-price-amount');
                    if (amtEl) amtEl.textContent = `£${total} (est.)`;
                    preview.style.display = 'flex';
                }

                #updateMultiplierDisplay(val) {
                    const el = document.getElementById('mult-display');
                    if (el) el.textContent = '×' + (val / 10).toFixed(1);
                    const pct = ((val - 10) / 20) * 100;
                    const inp = document.getElementById('boost-modal-multiplier');
                    if (inp) inp.style.background = `linear-gradient(to right, var(--accent) 0%, var(--accent) ${pct}%, var(--border) ${pct}%, var(--border) 100%)`;
                }

                #setLoading(btnId, loading) {
                    const btn = document.getElementById(btnId);
                    if (!btn) return;
                    btn.disabled = loading;
                    btn.textContent = loading ? 'Creating…' : (btn.dataset.label || 'Create Boost');
                }

                esc(str) {
                    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                }
            }

            // ══════════════════════════════════════════════════════════════
            // VoucherManager
            // ══════════════════════════════════════════════════════════════
            class VoucherManager {
                #api = null;
                #modal = null;
                #notif = null;

                init(api, modal, notif) {
                    this.#api = api;
                    this.#modal = modal;
                    this.#notif = notif;
                }

                openModal(data) {
                    const m = document.getElementById('modal-voucher-modal');
                    if (!m) return;
                    const set = (id, val) => {
                        const el = document.getElementById(id);
                        if (el) el.value = val ?? '';
                    };
                    set('voucher-modal-id', data?.id || '');
                    set('voucher-modal-code', data?.code || '');
                    set('voucher-modal-value', data?.value || '');
                    set('voucher-modal-limit', data?.limit || '');
                    set('voucher-modal-min', data?.min || '');
                    set('voucher-modal-expires', data?.expires || '');

                    const typeSel = document.getElementById('voucher-modal-type');
                    if (typeSel && data?.type) Array.from(typeSel.options).forEach(o => {
                        o.selected = o.value === data.type;
                    });

                    const stackCb = document.getElementById('voucher-modal-stackable');
                    if (stackCb) stackCb.checked = data?.stackable !== false;

                    const codeInput = document.getElementById('voucher-modal-code');
                    if (codeInput) codeInput.readOnly = !!data?.id;

                    m.querySelector('.modal-title').textContent = data?.id ? 'Edit Voucher' : 'Create Voucher';
                    const saveBtn = document.getElementById('voucher-modal-save');
                    if (saveBtn) saveBtn.dataset.label = data?.id ? 'Save Changes' : 'Create Voucher';
                    m.classList.add('open');
                }

                async save() {
                    const id = document.getElementById('voucher-modal-id')?.value;
                    const body = {
                        name: 'test', //todo
                        code: document.getElementById('voucher-modal-code')?.value,
                        type: document.getElementById('voucher-modal-type')?.value,
                        value: parseFloat(document.getElementById('voucher-modal-value')?.value) || 0,
                        usage_limit: parseInt(document.getElementById('voucher-modal-limit')?.value) || null,
                        minimum_order_value: parseFloat(document.getElementById('voucher-modal-min')?.value) || null,
                        expires_at: document.getElementById('voucher-modal-expires')?.value || null,
                        is_stackable: document.getElementById('voucher-modal-stackable')?.checked || false,
                        merchant_id: MERCHANT_ID
                    };

                    this.#setLoading('voucher-modal-save', true);
                    try {
                        const url = id ? `/api/${SITE}/vouchers/${id}` : `/api/${SITE}/vouchers`;
                        const method = id ? 'put' : 'post';
                        const res = await this.#api[method](url, body);
                        this.#modal.close('voucher-modal');
                        this.#notif.success(id ? 'Voucher updated' : 'Voucher created');
                        if (id) {
                            this.#updateVoucherCard(id, body);
                        } else {
                            this.#appendNewVoucherCard(res?.data?.voucher ?? res?.voucher ?? {...body, id: res?.id});
                        }
                    } catch (e) {
                        this.#notif.error('Error: ' + e.message);
                    } finally {
                        this.#setLoading('voucher-modal-save', false);
                    }
                }

                async deactivate(id) {
                    try {
                        await this.#api.post(`/api/${SITE}/vouchers/bulk-status`,
                            {ids: [parseInt(id)], status: 'inactive'}
                        );
                        // Update card in DOM
                        const card = document.querySelector(`.voucher-card[data-id="${id}"]`);
                        if (card) {
                            card.dataset.status = 'inactive';
                            const badge = card.querySelector('.badge');
                            if (badge) {
                                badge.className = 'badge badge-gray';
                                badge.textContent = 'Inactive';
                            }
                            const deactivateBtn = card.querySelector('.deactivate-voucher-btn');
                            if (deactivateBtn) deactivateBtn.remove();
                        }
                        this.#notif.success('Voucher deactivated');
                    } catch (e) {
                        this.#notif.error('Error: ' + e.message);
                    }
                }

                #updateVoucherCard(id, body) {
                    const editBtn = document.querySelector(`.edit-voucher-btn[data-id="${id}"]`);
                    if (!editBtn) return;
                    const card = editBtn.closest('.voucher-card');
                    if (!card) return;
                    // Update displayed value
                    const pctEl = card.querySelector('.voucher-pct');
                    if (pctEl) {
                        pctEl.textContent = body.type === 'percentage'
                            ? `${body.value}%`
                            : `£${parseFloat(body.value).toFixed(0)}`;
                    }
                    // Keep data attributes in sync
                    editBtn.dataset.value = body.value;
                    editBtn.dataset.limit = body.usage_limit ?? '';
                    editBtn.dataset.min = body.minimum_order_value ?? '';
                    editBtn.dataset.expires = body.expires_at ?? '';
                    editBtn.dataset.type = body.type;
                }

                #appendNewVoucherCard(voucher) {
                    const list = document.getElementById('voucher-list');
                    if (!list) return;
                    const isEmpty = list.querySelector('.empty-state');
                    if (isEmpty) isEmpty.remove();

                    const type = voucher.type ?? voucher.discount_type ?? 'percentage';
                    const value = parseFloat(voucher.value) || 0;
                    const bgColor = type === 'fixed' ? 'var(--blue)' : 'var(--ink)';
                    const discountDisplay = type === 'percentage' ? `${value}%` : `£${value.toFixed(0)}`;
                    const expMeta = voucher.expires_at ? `Expires ${new Date(voucher.expires_at).toLocaleDateString('en-GB', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    })}` : 'No expiry';
                    const usageMeta = voucher.usage_limit ? `Used 0/${voucher.usage_limit}` : 'Unlimited uses';

                    const card = document.createElement('div');
                    card.className = 'voucher-card';
                    card.dataset.status = 'active';
                    card.dataset.id = voucher.id ?? '';
                    card.innerHTML = `
                        <div class="voucher-left" style="background:${bgColor}">
                            <div class="voucher-pct">${discountDisplay}</div>
                            <div class="voucher-off">off</div>
                        </div>
                        <div class="voucher-right">
                            <div>
                                <div class="voucher-code">${this.esc(voucher.code)}</div>
                                <div class="voucher-meta">${expMeta} · ${usageMeta}</div>
                            </div>
                            <div class="voucher-actions">
                                <span class="badge badge-green">Active</span>
                                <button class="btn btn-sm btn-secondary" onclick="copyCode('${this.esc(voucher.code)}')">Copy</button>
                                <button class="btn btn-sm btn-ghost edit-voucher-btn"
                                    data-id="${voucher.id ?? ''}"
                                    data-code="${this.esc(voucher.code)}"
                                    data-type="${type}"
                                    data-value="${value}"
                                    data-limit="${voucher.usage_limit ?? ''}"
                                    data-min="${voucher.minimum_order_value ?? ''}"
                                    data-expires="${voucher.expires_at ?? ''}"
                                    data-stackable="${voucher.is_stackable ? '1' : '0'}">Edit</button>
                            </div>
                        </div>`;
                    list.prepend(card);

                    // Keep stat cards in sync
                    const activeCount = list.querySelectorAll('.voucher-card[data-status="active"]').length;
                    const totalCount = list.querySelectorAll('.voucher-card').length;
                    document.querySelector('#panel-vouchers .stat-card.green .stat-value')?.let?.(el => el.textContent = activeCount);
                    document.querySelector('#panel-vouchers .stat-card.amber .stat-value')?.let?.(el => el.textContent = totalCount);
                }

                copyCode(code) {
                    navigator.clipboard?.writeText(code).catch(() => {
                    });
                    this.#notif.success('Copied: ' + code);
                }

                #setLoading(btnId, loading) {
                    const btn = document.getElementById(btnId);
                    if (!btn) return;
                    btn.disabled = loading;
                    btn.textContent = loading ? 'Saving…' : (btn.dataset.label || btn.textContent);
                }

                esc(str) {
                    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                }
            }

            // ══════════════════════════════════════════════════════════════
            // TabManager
            // ══════════════════════════════════════════════════════════════
            class TabManager {
                init() {
                    document.querySelectorAll('.tab-bar').forEach(bar => {
                        bar.querySelectorAll('.tab-btn').forEach(btn =>
                            btn.addEventListener('click', () => {
                                bar.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                                btn.classList.add('active');
                                const targetId = bar.dataset.filterTarget;
                                if (!targetId) return;
                                const container = document.getElementById(targetId);
                                if (!container) return;
                                const filter = btn.dataset.filter || 'all';
                                container.querySelectorAll('[data-status]').forEach(item => {
                                    item.style.display = (filter === 'all' || item.dataset.status === filter) ? '' : 'none';
                                });
                            })
                        );
                    });
                }
            }

            // ══════════════════════════════════════════════════════════════
            // SidebarManager
            // ══════════════════════════════════════════════════════════════
            class SidebarManager {
                toggle() {
                    const sidebar = document.querySelector('.sidebar');
                    const overlay = document.getElementById('sidebar-overlay');
                    const isOpen = sidebar.classList.toggle('open');
                    overlay?.classList.toggle('open', isOpen);
                    document.body.style.overflow = isOpen ? 'hidden' : '';
                }

                close() {
                    document.querySelector('.sidebar')?.classList.remove('open');
                    document.getElementById('sidebar-overlay')?.classList.remove('open');
                    document.body.style.overflow = '';
                }

                init() {
                    document.getElementById('sidebar-overlay')?.addEventListener('click', () => this.close());
                    window.addEventListener('resize', () => {
                        if (window.innerWidth > 768) this.close();
                    });
                }
            }

            // ══════════════════════════════════════════════════════════════
            // DashboardManager  — orchestrates everything
            // ══════════════════════════════════════════════════════════════
            class DashboardManager {
                api = new ApiClient();
                notif = new NotificationManager();
                modal = new ModalManager();
                nav = new NavigationManager();
                charts = new ChartManager();
                product = new ProductManager();
                offer = new OfferManager();
                boost = new BoostManager();
                voucher = new VoucherManager();
                tabs = new TabManager();
                sidebar = new SidebarManager();

                init() {
                    // Wire all managers
                    this.modal.init();
                    this.sidebar.init();
                    this.tabs.init();

                    this.product.init(this.api, this.modal, this.notif);
                    this.offer.init(this.api, this.modal, this.notif);
                    this.boost.init(this.api, this.modal, this.notif);
                    this.voucher.init(this.api, this.modal, this.notif);

                    this.nav.init({
                        charts: this.charts, product: this.product,
                        offer: this.offer, voucher: this.voucher,
                        modal: this.modal, notif: this.notif,
                    });

                    this.charts.buildRevenueChart();
                    this.charts.initChartTooltip();
                    this.charts.initWindowSwitcher(this.notif);

                    this.#bindDelegatedClicks();
                    this.#bindProductSearch();

                    this.nav.navigate('overview');
                }

                // ── Public façade methods (called from inline onclick attrs) ──

                navigate(key) {
                    this.nav.navigate(key);
                }

                openModal(id) {
                    this.modal.open(id);
                }

                closeModal(id) {
                    this.modal.close(id);
                }

                openProductModal(data) {
                    this.product.openModal(data);
                }

                saveProduct() {
                    this.product.save();
                }

                filterProducts() {
                    this.product.filterProducts();
                }

                openOfferModal(data) {
                    this.offer.openModal(data);
                }

                saveOffer() {
                    this.offer.save();
                }

                pauseOffer(btn) {
                    this.offer.pauseOffer(btn);
                }

                unpauseOffer(btn) {
                    this.offer.unpauseOffer(btn);
                }

                openBoostModal(data) {
                    this.boost.openModal(data);
                }

                saveBoost() {
                    this.boost.save();
                }

                pauseBoost(id) {
                    return this.boost.pauseBoost(id);
                }

                cancelBoost(id) {
                    return this.boost.cancelBoost(id);
                }

                openVoucherModal(data) {
                    this.voucher.openModal(data);
                }

                saveVoucher() {
                    this.voucher.save();
                }

                copyCode(code) {
                    this.voucher.copyCode(code);
                }

                deactivateVoucher(id) {
                    this.voucher.deactivate(id);
                }

                toggleSidebar() {
                    this.sidebar.toggle();
                }

                closeSidebar() {
                    this.sidebar.close();
                }

                mobileNavigate(key) {
                    this.nav.navigate(key);
                    this.sidebar.close();
                }

                showNotif(msg) {
                    this.notif.show(msg);
                }

                exportInvoicesCsv() {
                    const table = document.getElementById('invoice-table');
                    if (!table) {
                        this.notif.error('No invoice data to export');
                        return;
                    }
                    const rows = [];
                    table.querySelectorAll('tr').forEach(tr => {
                        const cells = Array.from(tr.querySelectorAll('th,td'))
                            .slice(0, 7)
                            .map(td => `"${td.textContent.replace(/"/g, '""').trim()}"`);
                        if (cells.length) rows.push(cells.join(','));
                    });
                    if (rows.length <= 1) {
                        this.notif.show('No invoice data to export');
                        return;
                    }
                    const blob = new Blob([rows.join('\n')], {type: 'text/csv'});
                    const a = Object.assign(document.createElement('a'), {
                        href: URL.createObjectURL(blob), download: 'invoices.csv',
                    });
                    a.click();
                    URL.revokeObjectURL(a.href);
                    this.notif.success('CSV downloaded');
                }

                // ── Private ───────────────────────────────────────────────

                #bindDelegatedClicks() {
                    document.addEventListener('click', e => {
                        // Offer edit button
                        const offerBtn = e.target.closest('.edit-offer-btn');
                        if (offerBtn) {
                            const d = offerBtn.dataset;
                            console.log('d', d)
                            this.offer.openModal({
                                id: d.id, product_id: d.productId,
                                sale_price: d.salePrice, original_price: d.originalPrice,
                                start_date: d.startDate, end_date: d.endDate,
                                link: d.link, status: d.status
                            });
                            return;
                        }
                        // Product edit button
                        const productBtn = e.target.closest('.edit-product-btn');
                        if (productBtn) {
                            const d = productBtn.dataset;
                            this.product.openModal({
                                id: d.id, name: d.name, sku: d.sku,
                                price: d.price, sale_price: d.salePrice,
                                stock: d.stock, description: d.description,
                                url: d.url,
                            });
                            return;
                        }
                        // Voucher edit button
                        const voucherBtn = e.target.closest('.edit-voucher-btn');
                        if (voucherBtn) {
                            const d = voucherBtn.dataset;
                            this.voucher.openModal({
                                id: d.id, code: d.code, type: d.type,
                                value: d.value, limit: d.limit, min: d.min,
                                expires: d.expires, stackable: d.stackable === '1',
                            });
                            return;
                        }
                        // Voucher deactivate button
                        const deactivateBtn = e.target.closest('.deactivate-voucher-btn');
                        if (deactivateBtn) {
                            const id = deactivateBtn.dataset.id;
                            if (id) this.voucher.deactivate(id);
                            return;
                        }
                        // Boost pause/cancel from active-boosts table
                        const pauseBoostBtn = e.target.closest('.pause-boost-btn');
                        if (pauseBoostBtn) {
                            const id = pauseBoostBtn.dataset.boostId;
                            if (id) this.boost.pauseBoost(parseInt(id));
                            return;
                        }
                        const cancelBoostBtn = e.target.closest('.cancel-boost-btn');
                        if (cancelBoostBtn) {
                            const id = cancelBoostBtn.dataset.boostId;
                            if (id) this.boost.cancelBoost(parseInt(id));
                            return;
                        }
                    });
                }

                #bindProductSearch() {
                    document.getElementById('product-search')
                        ?.addEventListener('input', () => this.product.filterProducts());
                }
            }

            // ── Bootstrap ─────────────────────────────────────────────────
            const dashboardApp = new DashboardManager();
            // Expose globally so inline onclick attrs on PHP-rendered HTML still work
            window.dashboardApp = dashboardApp;

            // Shim legacy global function names used in the PHP template's onclick attrs
            const navigate = (k) => dashboardApp.navigate(k);
            const openModal = (id) => dashboardApp.openModal(id);
            const closeModal = (id) => dashboardApp.closeModal(id);
            const openProductModal = (d) => dashboardApp.openProductModal(d);
            const saveProduct = () => dashboardApp.saveProduct();
            const filterProducts = () => dashboardApp.filterProducts();
            const openOfferModal = (d) => dashboardApp.openOfferModal(d);
            const saveOffer = () => dashboardApp.saveOffer();
            const pauseOffer = (btn) => dashboardApp.pauseOffer(btn);
            const unpauseOffer = (btn) => dashboardApp.unpauseOffer(btn);
            const openBoostModal = (d) => dashboardApp.openBoostModal(d);
            const saveBoost = () => dashboardApp.saveBoost();
            const openVoucherModal = (d) => dashboardApp.openVoucherModal(d);
            const saveVoucher = () => dashboardApp.saveVoucher();
            const copyCode = (c) => dashboardApp.copyCode(c);
            const deactivateVoucher = (id) => dashboardApp.deactivateVoucher(id);
            const toggleSidebar = () => dashboardApp.toggleSidebar();
            const closeSidebar = () => dashboardApp.closeSidebar();
            const mobileNavigate = (k) => dashboardApp.mobileNavigate(k);
            const showNotif = (msg) => dashboardApp.showNotif(msg);

            // Alias used only in boost panel — keep for backwards compat
            const selectBoost = (card) => {
                card.closest('div').querySelectorAll('.boost-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
            };
            const updateMultiplier = (val) => {
                document.getElementById('mult-display').textContent = '×' + (val / 10).toFixed(1);
                const pct = ((val - 10) / 20) * 100;
                const inp = document.querySelector('#modal-boost-modal input[type=range]');
                if (inp) inp.style.background = `linear-gradient(to right, var(--accent) 0%, var(--accent) ${pct}%, var(--border) ${pct}%, var(--border) 100%)`;
            };

            // Kick off
            dashboardApp.init();
        </script>

</body>
</html>