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
                    <button class="btn btn-primary" onclick="openModal('offer-modal')">+ New Offer</button>
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
                            $dateRange = ($offer->starts_at && $offer->ends_at)
                                    ? $offer->starts_at->format('d M') . ' – ' . $offer->ends_at->format('d M Y')
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
                                            data-product-id="<?= $offer->product_id ?>"
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
                            <button class="btn btn-secondary" onclick="showNotif('Auto Boost preview generated')">
                                Preview Plan
                            </button>
                            <button class="btn btn-primary" style="margin-left:8px"
                                    onclick="showNotif('Settings saved successfully')">Save Settings
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
                                <button class="btn btn-sm btn-primary" onclick="openModal('boost-modal')">+ New Boost
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
                                            <td style="font-weight:500"><?= htmlspecialchars($boost->product->name ?? '—') ?></td>
                                            <td>
                                                <span class="badge <?= $boost->context === 'listing' ? 'badge-blue' : 'badge-amber' ?>"><?= ucfirst($boost->context) ?></span>
                                            </td>
                                            <td class="font-mono">×<?= number_format($boost->multiplier, 1) ?></td>
                                            <td class="font-mono"><?= number_format($boost->impressions ?? 0) ?></td>
                                            <td class="font-mono"><?= number_format($boost->clicks ?? 0) ?></td>
                                            <td><span class="badge badge-green">Active</span></td>
                                            <td class="text-xs"><?= $boost->ends_at?->format('d M') ?? '—' ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-danger"
                                                        onclick="showNotif('Boost paused')">Pause
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
                            <div class="voucher-card" data-status="<?= $vStatus ?>">
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
                                            <button class="btn btn-sm btn-danger"
                                                    onclick="showNotif('Voucher deactivated')">Deactivate
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
                                                Edit</button>
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
                                <option>Electronics</option>
                                <option>Accessories</option>
                                <option>Portable</option>
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
                    <div class="form-group"><label class="form-label">Select Product to Boost</label><select
                                class="form-control">
                            <option>USB-C Hub Pro 7-in-1</option>
                            <option>Wireless Charger 15W</option>
                            <option>Laptop Stand XL</option>
                        </select></div>
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
                                                                                                   class="form-control">
                        </div>
                        <div class="form-group"><label class="form-label">End Date</label><input type="date"
                                                                                                 class="form-control">
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Multiplier <span id="mult-display"
                                                                                       style="font-family:var(--font-mono);color:var(--accent)">×1.5</span></label>
                        <div class="range-wrap"><input type="range" min="10" max="30" value="15"
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
                    <button class="btn btn-primary"
                            onclick="closeModal('boost-modal');showNotif('Boost created and pending activation')">Create
                        Boost
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
                                                                                             class="form-control"><?php foreach ($products as $p): ?>
                                <option
                                value="<?= $p->id ?>"><?= htmlspecialchars($p->name) ?></option><?php endforeach ?>
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
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Start Date</label><input type="date"
                                                                                                   id="offer-modal-start"
                                                                                                   class="form-control">
                        </div>
                        <div class="form-group"><label class="form-label">End Date</label><input type="date"
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
            // ─── NAVIGATION ──────────────────────────────────────────────────────────
            const panels = {
                overview: {title: 'Overview', breadcrumb: 'Dashboard', action: 'Add Product', modal: 'product-modal'},
                analytics: {title: 'Analytics', breadcrumb: 'Analytics', action: null, modal: null},
                products: {title: 'Products', breadcrumb: 'Products', action: 'Add Product', modal: 'product-modal'},
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

            navigate = function (key) {
                _originalNavigate(key);
                document.querySelectorAll('.mobile-nav-item[data-panel]').forEach(el => {
                    el.classList.toggle('active', el.dataset.panel === key);
                });
            };

            function _originalNavigate(key) {
                document.querySelectorAll('.nav-item').forEach(el => el.classList.toggle('active', el.dataset.panel === key));
                document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
                document.getElementById('panel-' + key)?.classList.add('active');
                if (key === 'analytics') buildAnalyticsCharts();
                const cfg = panels[key];
                if (!cfg) return;
                document.getElementById('page-title').textContent = cfg.title;
                document.getElementById('page-breadcrumb').textContent = cfg.breadcrumb;
                const btn = document.getElementById('primary-action');
                if (cfg.action) {
                    btn.style.display = '';
                    btn.textContent = '+ ' + cfg.action;
                    // Products and offers use dedicated openers to clear the form correctly
                    const openers = {
                        'product-modal': () => openProductModal(null),
                        'offer-modal': () => openOfferModal(null),
                        'voucher-modal': () => openVoucherModal(null)
                    };
                    btn.onclick = cfg.modal
                        ? (openers[cfg.modal] || (() => openModal(cfg.modal)))
                        : () => showNotif('Feature available in full integration');
                } else {
                    btn.style.display = 'none';
                }
            }

            document.querySelectorAll('.nav-item').forEach(el => el.addEventListener('click', () => navigate(el.dataset.panel)));

            // ─── MODALS ───────────────────────────────────────────────────────────────
            function openModal(id) {
                document.getElementById('modal-' + id)?.classList.add('open');
            }

            function closeModal(id) {
                document.getElementById('modal-' + id)?.classList.remove('open');
            }

            document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => {
                if (e.target === o) o.classList.remove('open');
            }));
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
            });

            // ─── NOTIFICATIONS ────────────────────────────────────────────────────────
            let notifTimer;
            function showNotif(msg) {
                const el = document.getElementById('notif');
                document.getElementById('notif-text').textContent = msg;
                el.classList.add('show');
                clearTimeout(notifTimer);
                notifTimer = setTimeout(() => el.classList.remove('show'), 2800);
            }

            // ─── COPY CODE ────────────────────────────────────────────────────────────
            function copyCode(code) {
                navigator.clipboard?.writeText(code).catch(() => {
                });
                showNotif('Copied: ' + code);
            }

            // ─── BOOST UI ────────────────────────────────────────────────────────────
            function selectBoost(card) {
                card.closest('div').querySelectorAll('.boost-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
            }
            function updateMultiplier(val) {
                document.getElementById('mult-display').textContent = '×' + (val / 10).toFixed(1);
                const pct = ((val - 10) / 20) * 100;
                const input = document.querySelector('#modal-boost-modal input[type=range]');
                if (input) input.style.background = `linear-gradient(to right, var(--accent) 0%, var(--accent) ${pct}%, var(--border) ${pct}%, var(--border) 100%)`;
            }

            // ─── REVENUE CHART ────────────────────────────────────────────────────────
            function buildRevenueChart() {
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

            // ─── ANALYTICS CHARTS ────────────────────────────────────────────────────
            // Deferred: charts are built the first time the analytics panel is shown.
            // Building them at parse time (inside a display:none parent) gives every
            // flex child zero computed width, making bars invisible.
            let analyticsChartsBuilt = false;

            function buildAnalyticsChart(containerId, data, valueKey, color) {
                const el = document.getElementById(containerId);
                if (!el || !Array.isArray(data) || !data.length) return;

                const values = data.map(d => Number(d[valueKey]) || 0);
                const max = Math.max(...values, 1);
                const step = Math.max(1, Math.ceil(data.length / 7));

                el.innerHTML = data.map((d, i) => {
                    const barH = Math.max(3, Math.round((values[i] / max) * 155));
                    const alpha = i >= data.length - 7 ? 'ff' : '88';
                    const dateLabel = i % step === 0 ? String(d.date || '').slice(5) : '';
                    // Always render value-label span — empty string = invisible spacer keeping layout stable
                    const valLabel = values[i] > 0 ? values[i].toLocaleString() : '';
                    return `<div class="analytics-bar-wrap">
                    <span class="bar-value-label">${valLabel}</span>
                    <div class="analytics-bar" style="height:${barH}px;background:${color}${alpha}"
                         title="${d.date}: ${values[i].toLocaleString()}"></div>
                    <span class="chart-label">${dateLabel}</span>
                </div>`;
                }).join('');
            }

            function buildAnalyticsCharts() {
                if (analyticsChartsBuilt) return;
                analyticsChartsBuilt = true;
                const d = window.__analyticsData || {};
                buildAnalyticsChart('chart-offer-clicks', d.offers || [], 'clicks', '#6b3fa0');
                buildAnalyticsChart('chart-deal-clicks', d.deals || [], 'clicks', '#1a6b6b');
                buildAnalyticsChart('chart-product-views', d.views || [], 'views', '#2a7a4b');
            }

            // ─── ANALYTICS WINDOW SWITCHER ────────────────────────────────────────────
            document.querySelectorAll('.window-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.window-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    // In a full integration this would trigger an AJAX reload of the analytics panel.
                    showNotif('Loading ' + btn.dataset.days + '-day analytics…');
                });
            });

            // ─── TAB FILTERING ───────────────────────────────────────────────────────
            // Tab bars with data-filter-target drive visibility of sibling items with data-status.
            // Tab bars without data-filter-target just style the active tab (e.g. overview 30d/90d).
            document.querySelectorAll('.tab-bar').forEach(bar => {
                bar.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        bar.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');

                        const targetId = bar.dataset.filterTarget;
                        if (!targetId) return;

                        const container = document.getElementById(targetId);
                        if (!container) return;

                        const filter = btn.dataset.filter || 'all';
                        // For product-grid, children may be product-card divs
                        const items = container.querySelectorAll('[data-status]');
                        items.forEach(item => {
                            const show = filter === 'all' || item.dataset.status === filter;
                            item.style.display = show ? '' : 'none';
                        });
                    });
                });
            });

            // ─── PRODUCT SEARCH ───────────────────────────────────────────────────────
            function filterProducts() {
                const q = (document.getElementById('product-search')?.value || '').toLowerCase();
                const grid = document.getElementById('product-grid');
                if (!grid) return;
                grid.querySelectorAll('.product-card[data-status]').forEach(card => {
                    const name = (card.querySelector('.product-name')?.textContent || '').toLowerCase();
                    card.style.display = name.includes(q) ? '' : 'none';
                });
            }

            // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
            // AJAX LAYER
            // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
            const SITE = '<?= $siteSlug ?? 'site' ?>';
            const MERCHANT_ID = '<?= $merchant->id ?>';
            // TODO: replace with dynamic token from session/cookie
            const API_TOKEN = 'REPLACE_WITH_BEARER_TOKEN';

            async function apiRequest(method, path, body = null) {
                const opts = {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + API_TOKEN,
                        'X-Site-Id': '<?= $siteId ?? '' ?>',
                    },
                };
                if (body) opts.body = JSON.stringify(body);
                const res = await fetch(path, opts);
                if (!res.ok) {
                    const err = await res.json().catch(() => ({message: 'Request failed'}));
                    throw new Error(err.message || 'Request failed (' + res.status + ')');
                }
                return res.json().catch(() => ({}));
            }

            function setLoading(btnId, loading) {
                const btn = document.getElementById(btnId);
                if (!btn) return;
                btn.disabled = loading;
                btn.textContent = loading ? 'Saving…' : btn.dataset.label || btn.textContent;
            }

            // ─── PRODUCT MODAL ────────────────────────────────────────────────────────
            function openProductModal(data) {
                const modal = document.getElementById('modal-product-modal');
                if (!modal) return;
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
                modal.querySelector('.modal-title').textContent = data?.id ? 'Edit Product' : 'Add Product';
                const saveBtn = document.getElementById('product-modal-save');
                if (saveBtn) saveBtn.dataset.label = data?.id ? 'Save Changes' : 'Save Product';
                modal.classList.add('open');
            }

            async function saveProduct() {
                const id = document.getElementById('product-modal-id')?.value;
                const body = {
                    name: document.getElementById('product-modal-name')?.value,
                    sku: document.getElementById('product-modal-sku')?.value,
                    price: document.getElementById('product-modal-price')?.value,
                    sale_price: document.getElementById('product-modal-sale-price')?.value || null,
                    stock_quantity: document.getElementById('product-modal-stock')?.value || null,
                    description: document.getElementById('product-modal-desc')?.value,
                    url: document.getElementById('product-modal-url')?.value,
                };
                setLoading('product-modal-save', true);
                try {
                    const url = id ? `/api/${SITE}/products/${id}` : `/api/${SITE}/products`;
                    const method = id ? 'PUT' : 'POST';
                    await apiRequest(method, url, body);
                    closeModal('product-modal');
                    showNotif(id ? 'Product updated' : 'Product created');
                } catch (e) {
                    showNotif('Error: ' + e.message);
                } finally {
                    setLoading('product-modal-save', false);
                }
            }

            // ─── OFFER MODAL ──────────────────────────────────────────────────────────
            function openOfferModal(data) {
                const modal = document.getElementById('modal-offer-modal');
                if (!modal) return;
                const set = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.value = val ?? '';
                };
                set('offer-modal-id', data?.id || '');
                set('offer-modal-product-id', data?.product_id || '');
                set('offer-modal-name', data?.name || '');
                set('offer-modal-orig-price', data?.original_price || '');
                set('offer-modal-sale-price', data?.sale_price || '');
                set('offer-modal-start', data?.starts_at || '');
                set('offer-modal-end', data?.ends_at || '');
                // Select correct product in dropdown
                const prodSel = document.getElementById('offer-modal-product');
                if (prodSel && data?.product_id) {
                    Array.from(prodSel.options).forEach(o => {
                        o.selected = o.value == data.product_id;
                    });
                }
                // Select correct status
                const statusSel = document.getElementById('offer-modal-status');
                if (statusSel && data?.status) {
                    Array.from(statusSel.options).forEach(o => {
                        o.selected = o.value.toLowerCase() === data.status.toLowerCase();
                    });
                }
                modal.querySelector('.modal-title').textContent = data?.id ? 'Edit Offer' : 'New Offer';
                const saveBtn = document.getElementById('offer-modal-save');
                if (saveBtn) saveBtn.dataset.label = 'Submit Offer';
                modal.classList.add('open');
            }

            async function saveOffer() {
                const id = document.getElementById('offer-modal-id')?.value;
                const productId = document.getElementById('offer-modal-product')?.value
                    || document.getElementById('offer-modal-product-id')?.value;
                const body = {
                    sale_price: document.getElementById('offer-modal-sale-price')?.value,
                    original_price: document.getElementById('offer-modal-orig-price')?.value || null,
                    starts_at: document.getElementById('offer-modal-start')?.value || null,
                    ends_at: document.getElementById('offer-modal-end')?.value || null,
                    status: document.getElementById('offer-modal-status')?.value,
                };
                if (!productId) {
                    showNotif('Please select a product');
                    return;
                }
                setLoading('offer-modal-save', true);
                try {
                    const url = id
                        ? `/api/${SITE}/products/${productId}/offers/${id}`
                        : `/api/${SITE}/products/${productId}/offers`;
                    const method = id ? 'PUT' : 'POST';
                    await apiRequest(method, url, body);
                    closeModal('offer-modal');
                    showNotif(id ? 'Offer updated' : 'Offer submitted for review');
                } catch (e) {
                    showNotif('Error: ' + e.message);
                } finally {
                    setLoading('offer-modal-save', false);
                }
            }

            // ─── OFFER PAUSE / UNPAUSE ────────────────────────────────────────────────
            async function pauseOffer(btn) {
                const row = btn.closest('[data-status]');
                const offerId = row?.dataset.offerId;
                const productId = row?.dataset.productId;
                try {
                    if (offerId && productId) {
                        await apiRequest('PUT', `/api/${SITE}/products/${productId}/offers/${offerId}`, {status: 'paused'});
                    }
                    if (row) {
                        row.dataset.status = 'paused';
                        const badge = row.querySelector('.badge');
                        if (badge) {
                            badge.className = 'badge badge-red';
                            badge.textContent = 'Paused';
                        }
                    }
                    btn.textContent = 'Unpause';
                    btn.classList.replace('btn-danger', 'btn-secondary');
                    btn.onclick = () => unpauseOffer(btn);
                    showNotif('Offer paused');
                } catch (e) {
                    showNotif('Error: ' + e.message);
                }
            }

            async function unpauseOffer(btn) {
                const row = btn.closest('[data-status]');
                const offerId = row?.dataset.offerId;
                const productId = row?.dataset.productId;
                try {
                    if (offerId && productId) {
                        await apiRequest('PUT', `/api/${SITE}/products/${productId}/offers/${offerId}`, {status: 'published'});
                    }
                    if (row) {
                        row.dataset.status = 'published';
                        const badge = row.querySelector('.badge');
                        if (badge) {
                            badge.className = 'badge badge-green';
                            badge.textContent = 'Published';
                        }
                    }
                    btn.textContent = 'Pause';
                    btn.classList.replace('btn-secondary', 'btn-danger');
                    btn.onclick = () => pauseOffer(btn);
                    showNotif('Offer resumed');
                } catch (e) {
                    showNotif('Error: ' + e.message);
                }
            }

            // ─── VOUCHER MODAL ────────────────────────────────────────────────────────
            function openVoucherModal(data) {
                const modal = document.getElementById('modal-voucher-modal');
                if (!modal) return;
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
                // type select
                const typeSel = document.getElementById('voucher-modal-type');
                if (typeSel && data?.type) {
                    Array.from(typeSel.options).forEach(o => {
                        o.selected = o.value === data.type;
                    });
                }
                // stackable checkbox
                const stackCb = document.getElementById('voucher-modal-stackable');
                if (stackCb) stackCb.checked = data?.stackable !== false;
                // lock code field if editing (code is immutable)
                const codeInput = document.getElementById('voucher-modal-code');
                if (codeInput) codeInput.readOnly = !!data?.id;
                modal.querySelector('.modal-title').textContent = data?.id ? 'Edit Voucher' : 'Create Voucher';
                const saveBtn = document.getElementById('voucher-modal-save');
                if (saveBtn) saveBtn.dataset.label = data?.id ? 'Save Changes' : 'Create Voucher';
                modal.classList.add('open');
            }

            async function saveVoucher() {
                const id = document.getElementById('voucher-modal-id')?.value;
                const body = {
                    code: document.getElementById('voucher-modal-code')?.value,
                    discount_type: document.getElementById('voucher-modal-type')?.value,
                    value: document.getElementById('voucher-modal-value')?.value,
                    usage_limit: document.getElementById('voucher-modal-limit')?.value || null,
                    minimum_order_value: document.getElementById('voucher-modal-min')?.value || null,
                    expires_at: document.getElementById('voucher-modal-expires')?.value || null,
                    is_stackable: document.getElementById('voucher-modal-stackable')?.checked || false,
                };
                setLoading('voucher-modal-save', true);
                try {
                    const url = id ? `/api/${SITE}/vouchers/${id}` : `/api/${SITE}/vouchers`;
                    const method = id ? 'PUT' : 'POST';
                    await apiRequest(method, url, body);
                    closeModal('voucher-modal');
                    showNotif(id ? 'Voucher updated' : 'Voucher created');
                } catch (e) {
                    showNotif('Error: ' + e.message);
                } finally {
                    setLoading('voucher-modal-save', false);
                }
            }

            // ─── CSV EXPORT ───────────────────────────────────────────────────────────
            function exportInvoicesCsv() {
                const table = document.getElementById('invoice-table');
                if (!table) {
                    showNotif('No invoice data to export');
                    return;
                }
                const rows = [];
                table.querySelectorAll('tr').forEach(tr => {
                    const cells = Array.from(tr.querySelectorAll('th,td'))
                        .slice(0, 7)
                        .map(td => '"' + td.textContent.replace(/"/g, '""').trim() + '"');
                    if (cells.length) rows.push(cells.join(','));
                });
                if (rows.length <= 1) {
                    showNotif('No invoice data to export');
                    return;
                }
                const blob = new Blob([rows.join('\n')], {type: 'text/csv'});
                const a = Object.assign(document.createElement('a'), {
                    href: URL.createObjectURL(blob), download: 'invoices.csv'
                });
                a.click();
                URL.revokeObjectURL(a.href);
                showNotif('CSV downloaded');
            }

            // ─── DELEGATED EDIT BUTTON HANDLERS ──────────────────────────────────────
            // Using event delegation avoids inline onclick breakage from special chars in data.
            document.addEventListener('click', function (e) {
                // Offer edit
                const offerBtn = e.target.closest('.edit-offer-btn');
                if (offerBtn) {
                    const d = offerBtn.dataset;
                    openOfferModal({
                        id: d.id,
                        product_id: d.productId,
                        name: d.name,
                        sale_price: d.salePrice,
                        original_price: d.originalPrice,
                        starts_at: d.startsAt,
                        ends_at: d.endsAt,
                        status: d.status,
                    });
                    return;
                }
                // Product edit
                const productBtn = e.target.closest('.edit-product-btn');
                if (productBtn) {
                    const d = productBtn.dataset;
                    openProductModal({
                        id: d.id,
                        name: d.name,
                        sku: d.sku,
                        price: d.price,
                        sale_price: d.salePrice,
                        stock: d.stock,
                        description: d.description,
                        url: d.url,
                    });
                    return;
                }
                // Voucher edit
                const voucherBtn = e.target.closest('.edit-voucher-btn');
                if (voucherBtn) {
                    const d = voucherBtn.dataset;
                    openVoucherModal({
                        id: d.id,
                        code: d.code,
                        type: d.type,
                        value: d.value,
                        limit: d.limit,
                        min: d.min,
                        expires: d.expires,
                        stackable: d.stackable === '1',
                    });
                    return;
                }
            });

            // ─── CHART TOOLTIP ────────────────────────────────────────────────────────
            // A single shared tooltip div that follows the mouse over chart bars.
            const chartTooltip = document.createElement('div');
            chartTooltip.id = 'chart-tooltip';
            chartTooltip.style.cssText = [
                'position:fixed',
                'pointer-events:none',
                'background:var(--ink)',
                'color:var(--white)',
                'font-family:var(--font-mono)',
                'font-size:11px',
                'padding:6px 10px',
                'border-radius:6px',
                'box-shadow:0 2px 8px rgba(0,0,0,.25)',
                'white-space:nowrap',
                'z-index:9999',
                'opacity:0',
                'transition:opacity .1s',
                'line-height:1.5',
            ].join(';');
            document.body.appendChild(chartTooltip);

            document.addEventListener('mouseover', function (e) {
                const bar = e.target.closest('.analytics-bar');
                if (!bar) return;
                const raw = bar.title;        // "2026-03-01: 5"
                if (!raw) return;
                const [datePart, countPart] = raw.split(': ');
                if (!datePart) return;
                // Format: "Fri 01 Mar 2026  ·  5 clicks"
                const d = new Date(datePart);
                const dateStr = isNaN(d)
                    ? datePart
                    : d.toLocaleDateString('en-GB', {
                        weekday: 'short',
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });
                const count = countPart || '0';
                // Determine metric label from container id
                const wrap = bar.closest('.analytics-chart-wrap');
                const metricLabel = wrap?.id === 'chart-offer-clicks' ? 'offer clicks'
                    : wrap?.id === 'chart-deal-clicks' ? 'deal clicks'
                        : wrap?.id === 'chart-product-views' ? 'views'
                            : 'events';
                chartTooltip.innerHTML = `<strong>${dateStr}</strong><br>${count} ${metricLabel}`;
                chartTooltip.style.opacity = '1';
            });

            document.addEventListener('mousemove', function (e) {
                if (chartTooltip.style.opacity === '0') return;
                const x = e.clientX, y = e.clientY;
                const tw = chartTooltip.offsetWidth, th = chartTooltip.offsetHeight;
                chartTooltip.style.left = (x + 12 + tw > window.innerWidth ? x - tw - 8 : x + 12) + 'px';
                chartTooltip.style.top = (y - 8 - th < 0 ? y + 8 : y - th - 8) + 'px';
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) closeSidebar();
            });

            document.addEventListener('mouseout', function (e) {
                if (!e.target.closest('.analytics-bar')) return;
                chartTooltip.style.opacity = '0';
            });

            // ─── MOBILE SIDEBAR ──────────────────────────────────────────────────────────
            function toggleSidebar() {
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                const isOpen = sidebar.classList.toggle('open');
                overlay?.classList.toggle('open', isOpen);
                document.body.style.overflow = isOpen ? 'hidden' : '';
            }

            function closeSidebar() {
                document.querySelector('.sidebar')?.classList.remove('open');
                document.getElementById('sidebar-overlay')?.classList.remove('open');
                document.body.style.overflow = '';
            }

            function mobileNavigate(key) {
                navigate(key);
                // Update mobile nav active state
                document.querySelectorAll('.mobile-nav-item[data-panel]').forEach(el => {
                    el.classList.toggle('active', el.dataset.panel === key);
                });
                closeSidebar();
            }

            // ─── INIT ─────────────────────────────────────────────────────────────────
            buildRevenueChart();
            navigate('overview');
        </script>
</body>
</html>