<?php
$apiBase = '/api/boosts';
$merchantId = 1; // Replace with auth context
$boostContexts = ['listing', 'deals', 'recommendations'];
$boostTypes = ['product', 'offer'];
$boostStatuses = ['pending', 'active', 'paused', 'expired', 'cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boost Manager</title>
    <style>
        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface2: #22263a;
            --border: #2e3347;
            --accent: #6c63ff;
            --accent2: #a78bfa;
            --green: #22c55e;
            --yellow: #eab308;
            --red: #ef4444;
            --orange: #f97316;
            --text: #e2e8f0;
            --muted: #64748b;
            --radius: 12px;
            --radius-sm: 7px;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
        }

        /* ── Layout ────────────────────────────────────────── */
        .shell {
            display: grid;
            grid-template-rows: auto 1fr;
            min-height: 100vh;
        }

        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: -0.01em;
        }

        .topbar-brand svg {
            color: var(--accent);
        }

        .topbar-meta {
            font-size: 0.8125rem;
            color: var(--muted);
        }

        .main {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 0;
            height: calc(100vh - 60px);
            overflow: hidden;
        }

        @media (max-width: 900px) {
            .main {
                grid-template-columns: 1fr;
                height: auto;
                overflow: auto;
            }
        }

        /* ── Sidebar ───────────────────────────────────────── */
        .sidebar {
            background: var(--surface);
            border-right: 1px solid var(--border);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar-inner {
            padding: 24px;
            flex: 1;
        }

        .section-label {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 14px;
        }

        /* ── Content ───────────────────────────────────────── */
        .content {
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .content-header {
            padding: 20px 28px 0;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .content-body {
            padding: 20px 28px 40px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* ── Stats strip ───────────────────────────────────── */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        @media (max-width: 700px) {
            .stats-strip {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 18px;
        }

        .stat-card-label {
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .stat-card-value {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text);
        }

        .stat-card-sub {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 2px;
        }

        .stat-card--accent .stat-card-value {
            color: var(--accent2);
        }

        .stat-card--green .stat-card-value {
            color: var(--green);
        }

        /* ── Card ──────────────────────────────────────────── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 0.875rem;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        /* ── Form ──────────────────────────────────────────── */
        .field {
            margin-bottom: 14px;
        }

        .field label {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .field input, .field select {
            width: 100%;
            padding: 9px 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 0.875rem;
            transition: border-color 0.15s, box-shadow 0.15s;
            appearance: none;
        }

        .field input:focus, .field select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.15);
        }

        .field input::placeholder {
            color: var(--muted);
        }

        .field-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .field-hint {
            font-size: 0.7rem;
            color: var(--muted);
            margin-top: 4px;
        }

        /* ── Collapsible limits ────────────────────────────── */
        .limits-toggle {
            background: none;
            border: 1px dashed var(--border);
            border-radius: var(--radius-sm);
            color: var(--muted);
            font-size: 0.8125rem;
            padding: 8px 12px;
            width: 100%;
            cursor: pointer;
            text-align: left;
            transition: border-color 0.15s, color 0.15s;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .limits-toggle:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .limits-section {
            display: none;
        }

        .limits-section.open {
            display: block;
        }

        /* ── Price preview ─────────────────────────────────── */
        .price-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            margin-bottom: 14px;
            font-size: 0.875rem;
        }

        .price-badge .amount {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent2);
            margin-left: auto;
        }

        /* ── Buttons ───────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.15s, filter 0.15s;
            white-space: nowrap;
        }

        .btn:hover {
            filter: brightness(1.1);
        }

        .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            filter: none;
        }

        .btn--primary {
            background: var(--accent);
            color: #fff;
        }

        .btn--success {
            background: var(--green);
            color: #fff;
        }

        .btn--danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--red);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn--ghost {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn--warning {
            background: rgba(234, 179, 8, 0.15);
            color: var(--yellow);
            border: 1px solid rgba(234, 179, 8, 0.3);
        }

        .btn--full {
            width: 100%;
            justify-content: center;
        }

        .btn--sm {
            padding: 5px 10px;
            font-size: 0.75rem;
        }

        /* ── Alert ─────────────────────────────────────────── */
        .alert {
            padding: 11px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.8125rem;
            border: 1px solid transparent;
        }

        .alert--success {
            background: rgba(34, 197, 94, 0.1);
            border-color: rgba(34, 197, 94, 0.3);
            color: var(--green);
        }

        .alert--error {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert--warning {
            background: rgba(234, 179, 8, 0.1);
            border-color: rgba(234, 179, 8, 0.3);
            color: var(--yellow);
        }

        .alert--hidden {
            display: none;
        }

        /* ── Filters ───────────────────────────────────────── */
        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filters .field {
            margin: 0;
            min-width: 140px;
        }

        .search-wrap {
            position: relative;
        }

        .search-wrap input {
            padding-left: 34px;
        }

        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            pointer-events: none;
        }

        /* ── Table ─────────────────────────────────────────── */
        .table-scroll {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        thead th {
            background: var(--surface2);
            padding: 10px 14px;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.1s;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover {
            background: var(--surface2);
        }

        tbody tr.faded {
            opacity: 0.45;
        }

        tbody td {
            padding: 12px 14px;
            vertical-align: middle;
        }

        /* ── Boost detail panel ────────────────────────────── */
        .detail-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 100;
            align-items: flex-start;
            justify-content: flex-end;
            padding: 16px;
        }

        .detail-overlay.open {
            display: flex;
        }

        .detail-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            width: 420px;
            max-width: 100%;
            max-height: calc(100vh - 32px);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .detail-panel-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .detail-panel-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .detail-close {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 1.25rem;
            line-height: 1;
            padding: 2px;
        }

        .detail-close:hover {
            color: var(--text);
        }

        /* ── Score ring ────────────────────────────────────── */
        .score-ring {
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--surface2);
            border-radius: var(--radius-sm);
            padding: 14px;
        }

        .ring-wrap {
            position: relative;
            width: 56px;
            height: 56px;
            flex-shrink: 0;
        }

        .ring-wrap svg {
            transform: rotate(-90deg);
        }

        .ring-bg {
            fill: none;
            stroke: var(--border);
            stroke-width: 5;
        }

        .ring-fill {
            fill: none;
            stroke: var(--accent);
            stroke-width: 5;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.6s ease;
        }

        .ring-label {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--accent2);
        }

        .score-info .score-title {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .score-info .score-sub {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 2px;
        }

        /* ── Stat grid ─────────────────────────────────────── */
        .stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .mini-stat {
            background: var(--surface2);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
        }

        .mini-stat-label {
            font-size: 0.7rem;
            color: var(--muted);
            margin-bottom: 3px;
        }

        .mini-stat-value {
            font-size: 1rem;
            font-weight: 700;
        }

        /* ── Detail actions ────────────────────────────────── */
        .detail-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ── Breach banner ─────────────────────────────────── */
        .breach-banner {
            background: rgba(234, 179, 8, 0.1);
            border: 1px solid rgba(234, 179, 8, 0.3);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            font-size: 0.8125rem;
            color: var(--yellow);
        }

        .breach-banner strong {
            display: block;
            margin-bottom: 4px;
        }

        /* ── Badges ────────────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .badge--pending {
            background: rgba(234, 179, 8, 0.15);
            color: var(--yellow);
        }

        .badge--active {
            background: rgba(34, 197, 94, 0.15);
            color: var(--green);
        }

        .badge--paused {
            background: rgba(249, 115, 22, 0.15);
            color: var(--orange);
        }

        .badge--expired {
            background: rgba(100, 116, 139, 0.15);
            color: var(--muted);
        }

        .badge--cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: var(--red);
        }

        .badge--sponsored {
            background: rgba(167, 139, 250, 0.2);
            color: var(--accent2);
        }

        .badge--ranked {
            background: rgba(108, 99, 255, 0.2);
            color: var(--accent);
        }

        /* ── Pagination ────────────────────────────────────── */
        .pagination {
            display: flex;
            gap: 4px;
            justify-content: flex-end;
        }

        .pagination button {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: var(--radius-sm);
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 0.8125rem;
        }

        .pagination button.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .pagination button:hover:not(.active) {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ── Spinner ───────────────────────────────────────── */
        .spin {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            vertical-align: middle;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .table-placeholder {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }

        /* ── Mobile cards ──────────────────────────────────── */
        @media (max-width: 640px) {
            .table-scroll table {
                display: none;
            }

            .mobile-cards {
                display: block;
            }
        }

        @media (min-width: 641px) {
            .mobile-cards {
                display: none;
            }
        }

        .mobile-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px;
            margin-bottom: 10px;
        }

        .mobile-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .mobile-card-row {
            font-size: 0.8125rem;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .mobile-card-row span {
            color: var(--text);
            font-weight: 500;
        }

        .mobile-card-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        /* ── Divider ───────────────────────────────────────── */
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 0;
        }

        /* ── Suggestions ──────────────────────────────────────────────── */
        .suggestions-wrap {
            padding: 0 28px;
            margin-bottom: 20px;
        }

        .suggestions-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .suggestions-header h2 {
            font-size: 0.9375rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .suggestions-header .pill {
            background: var(--accent);
            color: #fff;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 99px;
            font-weight: 600;
        }

        .suggestion-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }

        .suggestion-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: border-color 0.15s;
        }

        .suggestion-card:hover {
            border-color: var(--accent);
        }

        .suggestion-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
        }

        .suggestion-card-title {
            font-weight: 600;
            font-size: 0.875rem;
            line-height: 1.3;
        }

        .suggestion-type-badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 99px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .type--high_potential_low_visibility {
            background: rgba(108, 99, 255, 0.2);
            color: var(--accent2);
        }

        .type--strong_deal {
            background: rgba(234, 179, 8, 0.15);
            color: var(--yellow);
        }

        .type--slow_mover_inventory_risk {
            background: rgba(249, 115, 22, 0.15);
            color: var(--orange);
        }

        .type--top_rated {
            background: rgba(34, 197, 94, 0.15);
            color: var(--green);
        }

        .type--boost_ending_soon {
            background: rgba(239, 68, 68, 0.15);
            color: var(--red);
        }

        .suggestion-reason {
            font-size: 0.78rem;
            color: var(--muted);
            line-height: 1.4;
        }

        .suggestion-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .sug-metric {
            background: var(--surface2);
            border-radius: 6px;
            padding: 7px 10px;
        }

        .sug-metric-label {
            font-size: 0.65rem;
            color: var(--muted);
        }

        .sug-metric-value {
            font-size: 0.9rem;
            font-weight: 700;
            margin-top: 1px;
        }

        .suggestion-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 4px;
        }

        .suggestion-cost {
            font-size: 0.75rem;
            color: var(--muted);
        }

        .suggestion-score {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .score-bar-wrap {
            width: 60px;
            height: 5px;
            background: var(--border);
            border-radius: 99px;
            overflow: hidden;
        }

        .score-bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 99px;
            transition: width 0.4s ease;
        }

        .score-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--accent2);
        }

        .suggestions-empty {
            text-align: center;
            padding: 30px;
            color: var(--muted);
            font-size: 0.875rem;
            background: var(--surface);
            border: 1px dashed var(--border);
            border-radius: var(--radius);
        }

        .suggestions-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 30px;
            color: var(--muted);
            font-size: 0.875rem;
        }

        /* ── Auto Boost settings ──────────────────────────────────────── */
        .auto-boost-panel {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0;
            overflow: hidden;
            margin-top: 20px;
        }

        .auto-boost-panel-header {
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
        }

        .auto-boost-panel-header h3 {
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .auto-boost-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toggle-switch {
            position: relative;
            width: 38px;
            height: 21px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: var(--border);
            border-radius: 99px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 15px;
            height: 15px;
            left: 3px;
            top: 3px;
            background: #fff;
            border-radius: 50%;
            transition: transform 0.2s;
        }

        .toggle-switch input:checked + .toggle-slider {
            background: var(--green);
        }

        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(17px);
        }

        .auto-boost-body {
            padding: 16px 18px;
            border-top: 1px solid var(--border);
        }

        .goal-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        .goal-option {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            cursor: pointer;
            transition: border-color 0.15s;
        }

        .goal-option.selected {
            border-color: var(--accent);
            background: rgba(108, 99, 255, 0.1);
        }

        .goal-option-label {
            font-size: 0.75rem;
            font-weight: 600;
        }

        .goal-option-sub {
            font-size: 0.7rem;
            color: var(--muted);
            margin-top: 2px;
        }

        .auto-boost-preview-wrap {
            margin-top: 14px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px;
        }

        .preview-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.8125rem;
            padding: 4px 0;
            border-bottom: 1px solid var(--border);
        }

        .preview-row:last-child {
            border-bottom: none;
        }

        .preview-row-label {
            color: var(--muted);
        }

        .preview-row-value {
            font-weight: 600;
        }

        /* ── Typeahead ──────────────────────────────────────────────── */
        .ta-item {
            padding: 9px 12px;
            cursor: pointer;
            font-size: 0.8125rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 2px;
            transition: background 0.1s;
        }

        .ta-item:last-child {
            border-bottom: none;
        }

        .ta-item:hover, .ta-item.active {
            background: rgba(108, 99, 255, 0.15);
        }

        .ta-item-name {
            font-weight: 500;
        }

        .ta-item-meta {
            font-size: 0.7rem;
            color: var(--muted);
        }

        .ta-empty {
            padding: 12px;
            text-align: center;
            color: var(--muted);
            font-size: 0.8125rem;
        }

        .ta-loading {
            padding: 12px;
            text-align: center;
            color: var(--muted);
            font-size: 0.8125rem;
        }
    </style>
</head>
<body>
<div class="shell">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-brand">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
            </svg>
            Boost Manager
        </div>
        <span class="topbar-meta" id="topbar-updated">Loading stats...</span>
    </header>

    <div class="main">

        <!-- ── Sidebar: create form ── -->
        <aside class="sidebar">
            <div class="sidebar-inner">

                <p class="section-label">New Boost</p>

                <div id="form-alert" class="alert alert--hidden" style="margin-bottom:14px"></div>

                <div class="field">
                    <label>Target Type</label>
                    <select id="f-type">
                        <option value="">— Select —</option>
                        <?php foreach ($boostTypes as $t): ?>
                            <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Product / Offer</label>
                    <div style="position:relative">
                        <input
                                type="text"
                                id="f-target-search"
                                placeholder="Search by name..."
                                autocomplete="off"
                                oninput="debouncedTargetSearch()"
                                onfocus="debouncedTargetSearch()"
                        >
                        <input type="hidden" id="f-target">
                        <div id="target-dropdown" style="
            display:none;
            position:absolute;
            top:calc(100% + 4px);
            left:0; right:0;
            background:var(--surface2);
            border:1px solid var(--border);
            border-radius:var(--radius-sm);
            max-height:220px;
            overflow-y:auto;
            z-index:50;
            box-shadow:0 8px 24px rgba(0,0,0,0.4);
        "></div>
                    </div>
                    <div id="f-target-selected"
                         style="display:none;margin-top:6px;font-size:0.75rem;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:6px 10px;display:flex;align-items:center;justify-content:space-between">
                        <span id="f-target-selected-label"></span>
                        <button onclick="clearTargetSelection()"
                                style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:1rem;line-height:1">
                            ×
                        </button>
                    </div>
                </div>
                <div class="field">
                    <label>Context</label>
                    <select id="f-context">
                        <option value="">— Select —</option>
                        <?php foreach ($boostContexts as $c): ?>
                            <option value="<?= $c ?>"><?= ucfirst($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label>Start (UTC)</label>
                        <input type="datetime-local" id="f-starts">
                    </div>
                    <div class="field">
                        <label>End (UTC)</label>
                        <input type="datetime-local" id="f-ends">
                    </div>
                </div>
                <div class="field">
                    <label>Multiplier</label>
                    <input type="number" id="f-multiplier" step="0.1" min="1.1" placeholder="e.g. 1.5">
                    <div class="field-hint">Must be greater than 1. Higher = more prominent.</div>
                </div>

                <div id="price-preview" style="display:none" class="price-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                    Estimated cost
                    <span class="amount" id="price-amount">—</span>
                </div>

                <button class="limits-toggle" onclick="toggleLimits()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
                    </svg>
                    <span id="limits-toggle-label">Set spend / impression limits (optional)</span>
                </button>

                <div class="limits-section" id="limits-section">
                    <div class="field">
                        <label>Max Spend (£)</label>
                        <input type="number" id="f-max-spend" step="0.01" min="0" placeholder="e.g. 50.00">
                        <div class="field-hint">Boost pauses when spend reaches this amount.</div>
                    </div>
                    <div class="field">
                        <label>Max Clicks</label>
                        <input type="number" id="f-max-clicks" min="0" placeholder="e.g. 200">
                    </div>
                    <div class="field">
                        <label>Max Impressions</label>
                        <input type="number" id="f-max-impressions" min="0" placeholder="e.g. 5000">
                    </div>
                    <div class="field-hint" style="margin-bottom:14px">
                        Limits are checked every 5 minutes. Your boost may slightly exceed a limit before pausing.
                    </div>
                </div>

                <button class="btn btn--primary btn--full" id="create-btn" onclick="createBoost()">
                    Create Boost
                </button>
            </div>
        </aside>

        <!-- ── Content ── -->
        <div class="content">

            <!-- Merchant stats -->
            <div class="content-header">
                <div class="stats-strip" id="merchant-stats">
                    <div class="stat-card">
                        <div class="stat-card-label">Total Impressions</div>
                        <div class="stat-card-value" id="ms-impressions">—</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-label">Total Clicks</div>
                        <div class="stat-card-value" id="ms-clicks">—</div>
                    </div>
                    <div class="stat-card stat-card--green">
                        <div class="stat-card-label">Total Conversions</div>
                        <div class="stat-card-value" id="ms-conversions">—</div>
                    </div>
                    <div class="stat-card stat-card--accent">
                        <div class="stat-card-label">Spend Attributed</div>
                        <div class="stat-card-value" id="ms-spend">—</div>
                    </div>
                </div>
            </div>

            <div class="content-body">

                <div id="table-alert" class="alert alert--hidden"></div>

                <!-- Suggested Boosts -->
                <div class="suggestions-wrap" style="padding:0">
                    <div class="suggestions-header">
                        <h2>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5">
                                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                            </svg>
                            Suggested Boosts
                            <span class="pill" id="suggestions-count" style="display:none">0</span>
                        </h2>
                        <div style="display:flex;gap:8px;align-items:center">
                            <select id="suggestion-goal" onchange="loadSuggestions()"
                                    style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:0.8125rem;padding:6px 10px">
                                <option value="maximise_revenue">Maximise Revenue</option>
                                <option value="promote_deals">Promote Deals</option>
                                <option value="clear_inventory">Clear Inventory</option>
                            </select>
                            <button class="btn btn--ghost btn--sm" onclick="loadSuggestions()">↻ Refresh</button>
                        </div>
                    </div>
                    <div id="suggestions-container">
                        <div class="suggestions-loading"><span class="spin"></span> Analysing your catalogue...</div>
                    </div>

                    <!-- Auto Boost settings panel -->
                    <div class="auto-boost-panel" style="margin-top:16px">
                        <div class="auto-boost-panel-header" onclick="toggleAutoBoostPanel()">
                            <h3>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <circle cx="12" cy="12" r="3"/>
                                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>
                                </svg>
                                Smart Auto Boost
                                <span style="font-size:0.7rem;color:var(--muted);font-weight:400">Let the system optimise your visibility automatically</span>
                            </h3>
                            <div class="auto-boost-toggle" onclick="event.stopPropagation()">
                                <span style="font-size:0.75rem;color:var(--muted)"
                                      id="auto-boost-status-label">Disabled</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="auto-boost-enabled" onchange="saveAutoBoostSettings()">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="auto-boost-body" id="auto-boost-body" style="display:none">
                            <div class="field" style="margin-bottom:14px">
                                <label>Monthly Budget (£)</label>
                                <input type="number" id="ab-budget" min="0" step="10" placeholder="e.g. 200"
                                       oninput="debouncedPreview()">
                                <div class="field-hint">The system will not exceed this amount per calendar month.</div>
                            </div>
                            <div style="font-size:0.75rem;color:var(--muted);margin-bottom:8px;font-weight:500">Goal
                            </div>
                            <div class="goal-options">
                                <div class="goal-option selected" data-goal="maximise_revenue"
                                     onclick="selectGoal('maximise_revenue', this)">
                                    <div class="goal-option-label">📈 Maximise Revenue</div>
                                    <div class="goal-option-sub">Weight conversion rate + rating</div>
                                </div>
                                <div class="goal-option" data-goal="promote_deals"
                                     onclick="selectGoal('promote_deals', this)">
                                    <div class="goal-option-label">🏷 Promote Deals</div>
                                    <div class="goal-option-sub">Weight discount % + urgency</div>
                                </div>
                                <div class="goal-option" data-goal="clear_inventory"
                                     onclick="selectGoal('clear_inventory', this)">
                                    <div class="goal-option-label">📦 Clear Inventory</div>
                                    <div class="goal-option-sub">Weight high stock + low velocity</div>
                                </div>
                            </div>
                            <button class="btn btn--ghost btn--sm" onclick="loadAutoBoostPreview()"
                                    style="margin-bottom:12px">
                                Preview what would be boosted
                            </button>
                            <div id="auto-boost-preview" style="display:none" class="auto-boost-preview-wrap">
                                <div id="auto-boost-preview-content"></div>
                            </div>
                            <div style="margin-top:14px;display:flex;gap:8px">
                                <button class="btn btn--primary" onclick="saveAutoBoostSettings()">Save Settings
                                </button>
                                <div id="ab-save-status"
                                     style="font-size:0.8125rem;color:var(--green);align-self:center;display:none">✓
                                    Saved
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card">
                    <div class="card-body" style="padding:14px 18px">
                        <div class="filters">
                            <div class="field" style="flex:1;min-width:200px;margin:0">
                                <div class="search-wrap">
                                    <svg class="search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/>
                                        <path d="m21 21-4.35-4.35"/>
                                    </svg>
                                    <input type="text" id="filter-search" placeholder="Search target ID, context..."
                                           oninput="debouncedLoad()">
                                </div>
                            </div>
                            <?php foreach ([
                                                   ['filter-type', 'Type', $boostTypes],
                                                   ['filter-context', 'Context', $boostContexts],
                                                   ['filter-status', 'Status', $boostStatuses],
                                           ] as [$id, $label, $opts]): ?>
                                <div class="field" style="margin:0;min-width:130px">
                                    <select id="<?= $id ?>" onchange="loadBoosts(1)">
                                        <option value="">All <?= $label ?>s</option>
                                        <?php foreach ($opts as $o): ?>
                                            <option value="<?= $o ?>"><?= ucfirst($o) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                            <button class="btn btn--ghost btn--sm" onclick="clearFilters()">Clear</button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-scroll">
                    <table>
                        <thead>
                        <tr>
                            <th>Target</th>
                            <th>Context</th>
                            <th>Rank Score</th>
                            <th>Stats</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody id="boosts-tbody">
                        <tr>
                            <td colspan="8" class="table-placeholder"><span class="spin"></span></td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div class="mobile-cards" id="mobile-cards"></div>

                <div id="pagination" class="pagination"></div>

            </div>
        </div><!-- /content -->
    </div><!-- /main -->
</div><!-- /shell -->

<!-- ── Detail panel ── -->
<div class="detail-overlay" id="detail-overlay" onclick="closeDetail(event)">
    <div class="detail-panel" id="detail-panel">
        <div class="detail-panel-header">
            <span id="detail-title" style="font-weight:600;font-size:0.9rem">Boost Details</span>
            <button class="detail-close" onclick="closeDetailPanel()">×</button>
        </div>
        <div class="detail-panel-body" id="detail-body">
            <div style="text-align:center;padding:30px;color:var(--muted)"><span class="spin"></span></div>
        </div>
    </div>
</div>

<script>
    const API = '<?= $apiBase ?>';
    const MERCHANT = <?= $merchantId ?>;
    let currentPage = 1;
    let searchTimer = null;
    let targetSearchTimer = null;
    let targetDropdownOpen = false;
    let targetActiveIndex = -1;
    let targetResults = [];

    const RATES = {
        listing: {product: 5.00, offer: 6.00},
        deals: {product: 8.00, offer: 9.60},
        recommendations: {product: 3.00, offer: 3.60},
    };

    // ── Helpers ───────────────────────────────────────────
    function fmt(n) {
        return Number(n).toLocaleString('en-GB');
    }

    function fmtGbp(n) {
        return '£' + Number(n).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function fmtDate(d) {
        return d ? new Date(d).toLocaleString('en-GB', {dateStyle: 'short', timeStyle: 'short'}) : '—';
    }

    function cap(s) {
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
    }

    function badge(status) {
        return `<span class="badge badge--${status}">${cap(status)}</span>`;
    }

    function debouncedTargetSearch() {
        clearTimeout(targetSearchTimer);
        targetSearchTimer = setTimeout(runTargetSearch, 250);
    }

    async function apiFetch(url, opts = {}) {
        return fetch(url, {
            ...opts,
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', ...(opts.headers || {})},
        });
    }

    function showAlert(id, type, msg, duration = 5000) {
        const el = document.getElementById(id);
        el.className = `alert alert--${type}`;
        el.textContent = msg;
        if (duration) setTimeout(() => el.className = 'alert alert--hidden', duration);
    }

    // ── Price preview ─────────────────────────────────────
    function updatePrice() {
        const type = document.getElementById('f-type').value;
        const ctx = document.getElementById('f-context').value;
        const start = document.getElementById('f-starts').value;
        const end = document.getElementById('f-ends').value;
        const preview = document.getElementById('price-preview');

        if (!type || !ctx || !start || !end) {
            preview.style.display = 'none';
            return;
        }

        const hours = (new Date(end) - new Date(start)) / 3600000;
        const days = Math.ceil(Math.abs(hours) / 24);
        if (days <= 0) {
            preview.style.display = 'none';
            return;
        }

        const rate = (RATES[ctx] || {})[type] || 0;
        document.getElementById('price-amount').textContent = fmtGbp(rate * days) + ' (est.)';
        preview.style.display = 'flex';
    }

    ['f-type', 'f-context', 'f-starts', 'f-ends'].forEach(id =>
        document.getElementById(id).addEventListener('change', updatePrice)
    );

    // ── Limits toggle ─────────────────────────────────────
    let limitsOpen = false;

    function toggleLimits() {
        limitsOpen = !limitsOpen;
        document.getElementById('limits-section').classList.toggle('open', limitsOpen);
        document.getElementById('limits-toggle-label').textContent = limitsOpen
            ? 'Hide limits'
            : 'Set spend / impression limits (optional)';
    }

    // ── Create boost ──────────────────────────────────────
    async function createBoost() {
        const btn = document.getElementById('create-btn');
        const body = {
            boostable_type: document.getElementById('f-type').value,
            target_id: parseInt(document.getElementById('f-target').value),
            merchant_id: MERCHANT,
            context: document.getElementById('f-context').value,
            starts_at: document.getElementById('f-starts').value,
            ends_at: document.getElementById('f-ends').value,
            multiplier: parseFloat(document.getElementById('f-multiplier').value),
        };

        const maxSpend = document.getElementById('f-max-spend').value;
        const maxClicks = document.getElementById('f-max-clicks').value;
        const maxImp = document.getElementById('f-max-impressions').value;

        if (maxSpend || maxClicks || maxImp) {
            body.limits = {};
            if (maxSpend) body.limits.max_spend = parseFloat(maxSpend);
            if (maxClicks) body.limits.max_clicks = parseInt(maxClicks);
            if (maxImp) body.limits.max_impressions = parseInt(maxImp);
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spin"></span> Creating...';

        try {
            const res = await apiFetch(API, {method: 'POST', body: JSON.stringify(body)});
            const data = await res.json();

            if (!res.ok) {
                showAlert('form-alert', 'error', data.error || 'Failed to create boost.');
                return;
            }

            showAlert('form-alert', 'success', `Boost #${data.data.id} created.`);
            resetForm();
            loadBoosts(1);
            loadMerchantStats();

        } catch {
            showAlert('form-alert', 'error', 'Network error. Please try again.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Create Boost';
        }
    }

    function resetForm() {
        ['f-type', 'f-target', 'f-context', 'f-starts', 'f-ends', 'f-multiplier', 'f-max-spend', 'f-max-clicks', 'f-max-impressions']
            .forEach(id => document.getElementById(id).value = '');
        document.getElementById('price-preview').style.display = 'none';
    }

    // ── Load boosts ───────────────────────────────────────
    async function loadBoosts(page = 1) {
        currentPage = page;

        const params = new URLSearchParams({page, per_page: 20, merchant_id: MERCHANT});
        ['filter-search', 'filter-type', 'filter-context', 'filter-status'].forEach(id => {
            const val = document.getElementById(id).value;
            if (val) params.set(id.replace('filter-', '').replace('search', 'search'), val);
        });
        // fix key names
        const search = document.getElementById('filter-search').value;
        if (search) params.set('search', search);

        const tbody = document.getElementById('boosts-tbody');
        tbody.innerHTML = `<tr><td colspan="8" class="table-placeholder"><span class="spin"></span></td></tr>`;

        try {
            const res = await apiFetch(`${API}?${params}`);
            const data = await res.json();
            if (!res.ok) {
                tbody.innerHTML = `<tr><td colspan="8" class="table-placeholder">Error loading boosts.</td></tr>`;
                return;
            }
            renderTable(data.data);
            renderMobileCards(data.data);
            renderPagination(data.pagination);
        } catch {
            tbody.innerHTML = `<tr><td colspan="8" class="table-placeholder">Network error.</td></tr>`;
        }
    }

    function renderTable(boosts) {
        const tbody = document.getElementById('boosts-tbody');
        if (!boosts.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="table-placeholder">No boosts found.</td></tr>`;
            return;
        }
        tbody.innerHTML = boosts.map(b => {
            const faded = ['expired', 'cancelled'].includes(b.status) ? 'faded' : '';
            const sponsored = b.status === 'active' ? '<span class="badge badge--sponsored">Sponsored</span> ' : '';
            const ranked = b.rank_score > 0 ? `<span class="badge badge--ranked">${Number(b.rank_score).toFixed(1)}</span>` : '—';
            const stats = b.stat
                ? `${fmt(b.stat.impressions)} imp · ${fmt(b.stat.clicks)} clk`
                : '<span style="color:var(--muted)">No data yet</span>';
            const period = `${fmtDate(b.starts_at)}<br><span style="color:var(--muted)">→ ${fmtDate(b.ends_at)}</span>`;

            return `
        <tr class="${faded}" data-id="${b.id}">
            <td>
                <div style="font-weight:600">${sponsored}${cap(b.boostable_type)} #${b.boostable_id}</div>
                <div style="font-size:0.75rem;color:var(--muted)">${Number(b.multiplier).toFixed(2)}× multiplier</div>
            </td>
            <td>${cap(b.context)}</td>
            <td>${ranked}</td>
            <td style="font-size:0.75rem">${stats}</td>
            <td style="font-size:0.75rem">${period}</td>
            <td>${badge(b.status)}</td>
            <td style="font-weight:600">${b.currency} ${Number(b.price_paid).toFixed(2)}</td>
            <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap">
                    <button class="btn btn--ghost btn--sm" onclick="openDetail(${b.id})">Details</button>
                    ${buildActionBtns(b)}
                </div>
            </td>
        </tr>`;
        }).join('');
    }

    function renderMobileCards(boosts) {
        const el = document.getElementById('mobile-cards');
        if (!boosts.length) {
            el.innerHTML = '';
            return;
        }
        el.innerHTML = boosts.map(b => {
            const faded = ['expired', 'cancelled'].includes(b.status) ? 'opacity:0.5' : '';
            return `
        <div class="mobile-card" style="${faded}">
            <div class="mobile-card-header">
                <div>
                    <strong>${cap(b.boostable_type)} #${b.boostable_id}</strong>
                    ${b.status === 'active' ? '<span class="badge badge--sponsored" style="margin-left:5px">Sponsored</span>' : ''}
                </div>
                ${badge(b.status)}
            </div>
            <div class="mobile-card-row">Context: <span>${cap(b.context)}</span></div>
            <div class="mobile-card-row">Multiplier: <span>${Number(b.multiplier).toFixed(2)}×</span></div>
            <div class="mobile-card-row">Rank Score: <span>${b.rank_score ? Number(b.rank_score).toFixed(1) : '—'}</span></div>
            <div class="mobile-card-row">Period: <span>${fmtDate(b.starts_at)} → ${fmtDate(b.ends_at)}</span></div>
            <div class="mobile-card-row">Price: <span>${b.currency} ${Number(b.price_paid).toFixed(2)}</span></div>
            ${b.stat ? `<div class="mobile-card-row">Stats: <span>${fmt(b.stat.impressions)} imp · ${fmt(b.stat.clicks)} clk · ${fmt(b.stat.conversions)} conv</span></div>` : ''}
            <div class="mobile-card-actions">
                <button class="btn btn--ghost btn--sm" onclick="openDetail(${b.id})">Details</button>
                ${buildActionBtns(b)}
            </div>
        </div>`;
        }).join('');
    }

    function buildActionBtns(b) {
        let html = '';
        if (b.status === 'active') html += `<button class="btn btn--warning btn--sm" onclick="boostAction(${b.id},'pause')">Pause</button>`;
        if (b.status === 'paused') html += `<button class="btn btn--success btn--sm" onclick="boostAction(${b.id},'resume')">Resume</button>`;
        if (b.status === 'active') html += `<button class="btn btn--ghost btn--sm" onclick="boostAction(${b.id},'expire')">Expire</button>`;
        if (['pending', 'active', 'paused'].includes(b.status))
            html += `<button class="btn btn--danger btn--sm" onclick="boostAction(${b.id},'cancel')">Cancel</button>`;
        return html;
    }

    // ── Actions ───────────────────────────────────────────
    async function boostAction(id, action) {
        const confirmMsg = {
            pause: 'Pause this boost?',
            resume: 'Resume this boost?',
            expire: 'Expire this boost now?',
            cancel: 'Cancel this boost? This cannot be undone.'
        };
        if (!confirm(confirmMsg[action] || `${cap(action)} this boost?`)) return;

        try {
            const res = await apiFetch(`${API}/${id}/${action}`, {method: 'POST'});
            const data = await res.json();
            if (!res.ok) {
                showAlert('table-alert', 'error', data.error || `Failed to ${action} boost.`);
                return;
            }
            showAlert('table-alert', 'success', `Boost #${id} ${action}d.`);
            loadBoosts(currentPage);
            loadMerchantStats();
            if (document.getElementById('detail-overlay').classList.contains('open')) openDetail(id);
        } catch {
            showAlert('table-alert', 'error', 'Network error.');
        }
    }

    // ── Detail panel ──────────────────────────────────────
    async function openDetail(id) {
        const overlay = document.getElementById('detail-overlay');
        const body = document.getElementById('detail-body');
        const title = document.getElementById('detail-title');

        overlay.classList.add('open');
        title.textContent = `Boost #${id}`;
        body.innerHTML = `<div style="text-align:center;padding:30px;color:var(--muted)"><span class="spin"></span></div>`;

        try {
            const [boostRes, statsRes] = await Promise.all([
                apiFetch(`${API}/${id}`),
                apiFetch(`${API}/${id}/stats`),
            ]);
            const boost = (await boostRes.json()).data;
            const stats = (await statsRes.json()).data;

            if (!boost) {
                body.innerHTML = `<p style="color:var(--red)">Boost not found.</p>`;
                return;
            }

            const rankScore = stats.rank_score ?? 0;
            const maxScore = Math.max(rankScore, 100);
            const pct = Math.min(rankScore / maxScore, 1);
            const circ = 2 * Math.PI * 22;
            const dash = circ * pct;

            body.innerHTML = `
            ${stats.limit_breached ? `
            <div class="breach-banner">
                <strong>⚠ Limit reached — boost paused</strong>
                ${stats.breach_message || ''}
            </div>` : ''}

            <div class="score-ring">
                <div class="ring-wrap">
                    <svg width="56" height="56" viewBox="0 0 56 56">
                        <circle class="ring-bg" cx="28" cy="28" r="22"/>
                        <circle class="ring-fill" cx="28" cy="28" r="22"
                            stroke-dasharray="${circ}"
                            stroke-dashoffset="${circ - dash}"/>
                    </svg>
                    <div class="ring-label">${Math.round(pct * 100)}%</div>
                </div>
                <div class="score-info">
                    <div class="score-title">Rank Score: ${Number(rankScore).toFixed(1)}</div>
                    <div class="score-sub">Boost Score × ${Number(boost.multiplier).toFixed(2)} multiplier<br>Higher score = better placement</div>
                </div>
            </div>

            <div class="stat-grid">
                <div class="mini-stat"><div class="mini-stat-label">Impressions</div><div class="mini-stat-value">${fmt(stats.impressions)}</div></div>
                <div class="mini-stat"><div class="mini-stat-label">Clicks</div><div class="mini-stat-value">${fmt(stats.clicks)}</div></div>
                <div class="mini-stat"><div class="mini-stat-label">Conversions</div><div class="mini-stat-value" style="color:var(--green)">${fmt(stats.conversions)}</div></div>
                <div class="mini-stat"><div class="mini-stat-label">CTR</div><div class="mini-stat-value">${stats.ctr}%</div></div>
                <div class="mini-stat"><div class="mini-stat-label">Conv. Rate</div><div class="mini-stat-value">${stats.conversion_rate}%</div></div>
                <div class="mini-stat"><div class="mini-stat-label">Spend Attributed</div><div class="mini-stat-value" style="color:var(--accent2)">${fmtGbp(stats.spend_attributed)}</div></div>
            </div>

            <hr class="divider">

            <div style="font-size:0.8125rem;display:flex;flex-direction:column;gap:6px">
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">Status</span>${badge(boost.status)}</div>
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">Context</span><span>${cap(boost.context)}</span></div>
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">Multiplier</span><span>${Number(boost.multiplier).toFixed(2)}×</span></div>
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">Price Paid</span><span>${boost.currency} ${Number(boost.price_paid).toFixed(2)}</span></div>
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">Starts</span><span>${fmtDate(boost.starts_at)}</span></div>
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">Ends</span><span>${fmtDate(boost.ends_at)}</span></div>
                ${stats.last_updated_at ? `<div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">Stats updated</span><span style="color:var(--muted);font-size:0.75rem">${fmtDate(stats.last_updated_at)}</span></div>` : ''}
            </div>

            <hr class="divider">

            <div class="detail-actions">
                ${boost.status === 'active' ? `<button class="btn btn--warning btn--sm" onclick="boostAction(${boost.id},'pause')">Pause</button>` : ''}
                ${boost.status === 'paused' ? `<button class="btn btn--success btn--sm" onclick="boostAction(${boost.id},'resume')">Resume</button>` : ''}
                ${boost.status === 'active' ? `<button class="btn btn--ghost btn--sm" onclick="boostAction(${boost.id},'expire')">Expire now</button>` : ''}
                ${['pending', 'active', 'paused'].includes(boost.status) ? `<button class="btn btn--danger btn--sm" onclick="boostAction(${boost.id},'cancel')">Cancel</button>` : ''}
            </div>

            ${stats.last_updated_at ? '' : `<p style="font-size:0.75rem;color:var(--muted)">Stats are aggregated every 5 minutes. Check back shortly for impressions and clicks.</p>`}
        `;

        } catch {
            body.innerHTML = `<p style="color:var(--red)">Failed to load boost details.</p>`;
        }
    }

    function closeDetailPanel() {
        document.getElementById('detail-overlay').classList.remove('open');
    }

    function closeDetail(e) {
        if (e.target === document.getElementById('detail-overlay')) closeDetailPanel();
    }

    // ── Merchant stats ────────────────────────────────────
    async function loadMerchantStats() {
        try {
            const res = await apiFetch(`/api/merchants/${MERCHANT}/boost-stats`);
            const data = (await res.json()).data;
            if (!data) return;

            document.getElementById('ms-impressions').textContent = fmt(data.total_impressions);
            document.getElementById('ms-clicks').textContent = fmt(data.total_clicks);
            document.getElementById('ms-conversions').textContent = fmt(data.total_conversions);
            document.getElementById('ms-spend').textContent = fmtGbp(data.total_spend_attributed);

            if (data.last_updated_at) {
                document.getElementById('topbar-updated').textContent
                    = 'Stats updated ' + fmtDate(data.last_updated_at);
            }
        } catch { /* non-critical */
        }
    }

    // ── Pagination ────────────────────────────────────────
    function renderPagination(pag) {
        const el = document.getElementById('pagination');
        if (pag.last_page <= 1) {
            el.innerHTML = '';
            return;
        }
        el.innerHTML = Array.from({length: pag.last_page}, (_, i) => i + 1)
            .map(p => `<button class="${p === pag.current_page ? 'active' : ''}" onclick="loadBoosts(${p})">${p}</button>`)
            .join('');
    }

    // ── Filters ───────────────────────────────────────────
    function debouncedLoad() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadBoosts(1), 300);
    }

    function clearFilters() {
        ['filter-search', 'filter-type', 'filter-context', 'filter-status']
            .forEach(id => document.getElementById(id).value = '');
        loadBoosts(1);
    }

    // ── Suggestions ───────────────────────────────────────────────
    async function loadSuggestions() {
        const goal = document.getElementById('suggestion-goal').value;
        const container = document.getElementById('suggestions-container');
        const countEl = document.getElementById('suggestions-count');

        container.innerHTML = `<div class="suggestions-loading"><span class="spin"></span> Analysing your catalogue...</div>`;

        try {
            const res = await apiFetch(`/api/merchants/${MERCHANT}/boost-suggestions?goal=${goal}`);
            const data = await res.json();

            if (!res.ok || !data.data) {
                container.innerHTML = `<div class="suggestions-empty">Unable to load suggestions right now.</div>`;
                return;
            }

            if (!data.data.length) {
                container.innerHTML = `<div class="suggestions-empty">No suggestions right now — your catalogue is well-optimised or all eligible products are already boosted.</div>`;
                countEl.style.display = 'none';
                return;
            }

            countEl.textContent = data.data.length;
            countEl.style.display = 'inline';
            container.innerHTML = `<div class="suggestion-cards">${data.data.map(renderSuggestionCard).join('')}</div>`;

        } catch {
            container.innerHTML = `<div class="suggestions-empty">Network error loading suggestions.</div>`;
        }
    }

    function renderSuggestionCard(s) {
        const typeLabels = {
            high_potential_low_visibility: '🔥 High Potential',
            strong_deal: '🏷 Strong Deal',
            slow_mover_inventory_risk: '📦 Clear Stock',
            top_rated: '⭐ Top Rated',
            boost_ending_soon: '⏰ Expiring Soon',
        };

        const scoreWidth = Math.round(s.opportunity_score);

        return `
    <div class="suggestion-card">
        <div class="suggestion-card-header">
            <div class="suggestion-card-title">${s.product_name}</div>
            <span class="suggestion-type-badge type--${s.type}">${typeLabels[s.type] || s.type}</span>
        </div>
        <div class="suggestion-reason">${s.reason}</div>
        <div class="suggestion-metrics">
            <div class="sug-metric">
                <div class="sug-metric-label">Impressions / 30d</div>
                <div class="sug-metric-value">${fmt(s.impressions_last_30d)}</div>
            </div>
            <div class="sug-metric">
                <div class="sug-metric-label">Conv. Rate</div>
                <div class="sug-metric-value">${Number(s.conversion_rate).toFixed(2)}%</div>
            </div>
            <div class="sug-metric">
                <div class="sug-metric-label">Stock</div>
                <div class="sug-metric-value">${fmt(s.stock_quantity)}</div>
            </div>
            <div class="sug-metric">
                <div class="sug-metric-label">Avg Rating</div>
                <div class="sug-metric-value">${s.average_rating > 0 ? Number(s.average_rating).toFixed(1) + ' ★' : '—'}</div>
            </div>
        </div>
        <div class="suggestion-footer">
            <div class="suggestion-cost">
                ${s.estimated_cost > 0 ? `Est. ${fmtGbp(s.estimated_cost)} · ${cap(s.suggested_context)}` : cap(s.suggested_context)}
            </div>
            <div class="suggestion-score">
                <div class="score-bar-wrap"><div class="score-bar-fill" style="width:${scoreWidth}%"></div></div>
                <span class="score-label">${scoreWidth}</span>
            </div>
        </div>
        <button class="btn btn--primary btn--sm btn--full" onclick="quickBoostFromSuggestion(${JSON.stringify(s).replace(/"/g, '&quot;')})">
            Boost Now
        </button>
    </div>`;
    }

    function quickBoostFromSuggestion(s) {
        // Pre-fill the create form from suggestion data
        document.getElementById('f-type').value = s.boostable_type;
        document.getElementById('f-target').value = s.offer_id ?? s.product_id;
        document.getElementById('f-context').value = s.suggested_context;
        document.getElementById('f-multiplier').value = s.suggested_multiplier;

        const now = new Date();
        const end = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
        const toLocal = d => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);

        document.getElementById('f-starts').value = toLocal(now);
        document.getElementById('f-ends').value = toLocal(end);

        updatePrice();

        // Scroll to form
        document.querySelector('.sidebar').scrollTo({top: 0, behavior: 'smooth'});
        document.getElementById('f-multiplier').focus();
    }

    // ── Auto Boost ────────────────────────────────────────────────
    let autoBoostGoal = 'maximise_revenue';
    let abPreviewTimer = null;

    function toggleAutoBoostPanel() {
        const body = document.getElementById('auto-boost-body');
        body.style.display = body.style.display === 'none' ? 'block' : 'none';
    }

    function selectGoal(goal, el) {
        autoBoostGoal = goal;
        document.querySelectorAll('.goal-option').forEach(g => g.classList.remove('selected'));
        el.classList.add('selected');
        debouncedPreview();
    }

    function debouncedPreview() {
        clearTimeout(abPreviewTimer);
        abPreviewTimer = setTimeout(loadAutoBoostPreview, 600);
    }

    async function loadAutoBoostPreview() {
        const wrap = document.getElementById('auto-boost-preview');
        const content = document.getElementById('auto-boost-preview-content');

        wrap.style.display = 'block';
        content.innerHTML = `<div style="text-align:center;padding:10px;color:var(--muted)"><span class="spin"></span></div>`;

        try {
            const res = await apiFetch(`/api/merchants/${MERCHANT}/auto-boost/preview`);
            const data = (await res.json()).data;

            if (!data || !data.allocations.length) {
                content.innerHTML = `<div style="font-size:0.8125rem;color:var(--muted);text-align:center;padding:10px">No boosts would be created with the current settings.</div>`;
                return;
            }

            content.innerHTML = `
            <div class="preview-row">
                <span class="preview-row-label">Budget available</span>
                <span class="preview-row-value">${fmtGbp(data.total_budget)}</span>
            </div>
            <div class="preview-row">
                <span class="preview-row-label">Would be allocated</span>
                <span class="preview-row-value" style="color:var(--accent2)">${fmtGbp(data.total_allocated)}</span>
            </div>
            <div class="preview-row">
                <span class="preview-row-label">Remaining</span>
                <span class="preview-row-value">${fmtGbp(data.remaining)}</span>
            </div>
            <div style="margin-top:10px;font-size:0.75rem;color:var(--muted);margin-bottom:6px">Would boost:</div>
            ${data.allocations.map(a => `
                <div class="preview-row">
                    <span class="preview-row-label">${a.product_name}</span>
                    <span class="preview-row-value">${fmtGbp(a.cost)} · ${cap(a.context)}</span>
                </div>
            `).join('')}
        `;
        } catch {
            content.innerHTML = `<div style="font-size:0.8125rem;color:var(--red);padding:10px">Failed to load preview.</div>`;
        }
    }

    async function saveAutoBoostSettings() {
        const enabled = document.getElementById('auto-boost-enabled').checked;
        const budget = parseFloat(document.getElementById('ab-budget').value) || 0;
        const label = document.getElementById('auto-boost-status-label');

        try {
            await apiFetch(`/api/merchants/${MERCHANT}/auto-boost/settings`, {
                method: 'POST',
                body: JSON.stringify({
                    is_enabled: enabled,
                    monthly_budget: budget,
                    goal: autoBoostGoal,
                    contexts_allowed: ['listing', 'deals', 'recommendations'],
                }),
            });

            label.textContent = enabled ? 'Enabled' : 'Disabled';
            label.style.color = enabled ? 'var(--green)' : '';

            const status = document.getElementById('ab-save-status');
            status.style.display = 'inline';
            setTimeout(() => status.style.display = 'none', 2000);

        } catch {
            showAlert('table-alert', 'error', 'Failed to save Auto Boost settings.');
        }
    }

    async function loadAutoBoostSettings() {
        try {
            const res = await apiFetch(`/api/merchants/${MERCHANT}/auto-boost/settings`);
            const data = await res.json();

            if (!res.ok || !data.data) return;

            const s = data.data;

            document.getElementById('auto-boost-enabled').checked = !!s.is_enabled;
            document.getElementById('ab-budget').value = s.monthly_budget ?? '';
            document.getElementById('auto-boost-status-label').textContent = s.is_enabled ? 'Enabled' : 'Disabled';
            document.getElementById('auto-boost-status-label').style.color = s.is_enabled ? 'var(--green)' : '';

            if (s.goal) {
                autoBoostGoal = s.goal;
                document.querySelectorAll('.goal-option').forEach(el => {
                    el.classList.toggle('selected', el.dataset.goal === s.goal);
                });
            }

        } catch { /* non-critical */
        }
    }

    async function runTargetSearch() {
        const type = document.getElementById('f-type').value;
        const query = document.getElementById('f-target-search').value.trim();
        const dd = document.getElementById('target-dropdown');

        if (!type) {
            console.log('a')
            dd.innerHTML = `<div class="ta-empty">Select a target type first.</div>`;
            dd.style.display = 'block';
            return;
        }

        if (query.length < 1) {
            console.log('b')
            dd.style.display = 'none';
            return;
        }

        dd.innerHTML = `<div class="ta-loading"><span class="spin"></span></div>`;
        dd.style.display = 'block';
        targetActiveIndex = -1;

        try {
            const endpoint = type === 'product'
                ? `/api/merchants/${MERCHANT}/products/search?q=${encodeURIComponent(query)}`
                : `/api/merchants/${MERCHANT}/offers/search?q=${encodeURIComponent(query)}`;

            const res = await apiFetch(endpoint);
            const data = await res.json();

            targetResults = data.data ?? [];

            if (!targetResults.length) {
                dd.innerHTML = `<div class="ta-empty">No ${type}s found for "${query}"</div>`;
                return;
            }

            dd.innerHTML = targetResults.map((item, i) => `
            <div class="ta-item" data-index="${i}" onmousedown="selectTarget(${i})">
                <div class="ta-item-name">${escHtml(item.name)}</div>
                <div class="ta-item-meta">
                    ID: ${item.id}
                    ${item.sku ? ' · SKU: ' + escHtml(item.sku) : ''}
                    ${item.price ? ' · ' + fmtGbp(item.price) : ''}
                    ${item.stock_quantity !== undefined ? ' · Stock: ' + fmt(item.stock_quantity) : ''}
                    ${item.discount_percent ? ' · ' + Math.round(item.discount_percent) + '% off' : ''}
                </div>
            </div>
        `).join('');

            // Keyboard navigation
            document.getElementById('f-target-search').onkeydown = handleTargetKey;

        } catch {
            dd.innerHTML = `<div class="ta-empty">Error loading results.</div>`;
        }
    }

    function handleTargetKey(e) {
        const dd = document.getElementById('target-dropdown');
        const items = dd.querySelectorAll('.ta-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            targetActiveIndex = Math.min(targetActiveIndex + 1, items.length - 1);
            items.forEach((el, i) => el.classList.toggle('active', i === targetActiveIndex));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            targetActiveIndex = Math.max(targetActiveIndex - 1, 0);
            items.forEach((el, i) => el.classList.toggle('active', i === targetActiveIndex));
        } else if (e.key === 'Enter' && targetActiveIndex >= 0) {
            e.preventDefault();
            selectTarget(targetActiveIndex);
        } else if (e.key === 'Escape') {
            closeTargetDropdown();
        }
    }

    function selectTarget(index) {
        const item = targetResults[index];
        if (!item) return;

        document.getElementById('f-target').value = item.id;
        document.getElementById('f-target-search').value = '';
        document.getElementById('f-target-selected-label').textContent = `${item.name} (ID: ${item.id})`;

        const sel = document.getElementById('f-target-selected');
        sel.style.display = 'flex';

        closeTargetDropdown();
        updatePrice();
    }

    function clearTargetSelection() {
        document.getElementById('f-target').value = '';
        document.getElementById('f-target-selected').style.display = 'none';
        document.getElementById('f-target-selected-label').textContent = '';
        document.getElementById('f-target-search').value = '';
        document.getElementById('price-preview').style.display = 'none';
    }

    function closeTargetDropdown() {
        document.getElementById('target-dropdown').style.display = 'none';
        targetActiveIndex = -1;
    }

    function escHtml(str) {
        return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#f-target-search') && !e.target.closest('#target-dropdown')) {
            closeTargetDropdown();
        }
    });

    // Clear selection when type changes
    document.getElementById('f-type').addEventListener('change', clearTargetSelection);

    // ── Init ──────────────────────────────────────────────
    loadBoosts(1);
    loadMerchantStats();
    loadSuggestions();
    setInterval(loadMerchantStats, 60000); // refresh merchant stats every minute
    loadAutoBoostSettings();
</script>
</body>
</html>