<?php
/*
Template : resources/views/competitions/index.blade.php
Route    : GET /competitions  →  CompetitionController::index()

Variables injected by controller:
array        $competitions   Decorated competition data from CompetitionService
array|null   $featured       Single featured competition (or null)
Member|null  $member         Authenticated member (or null)
bool         $hasActive      True when at least one active competition exists
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Club Prizes – {{ config('app.site_name', 'FourFourTwo') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        :root {
            --red: #d4001e;
            --red-light: #ffeef1;
            --red-mid: #f5c2ca;
            --text: #1a1d24;
            --muted: #6b7280;
            --subtle: #9ca3af;
            --bg: #f5f6f8;
            --surface: #ffffff;
            --border: #e2e5eb;
            --border-mid: #d1d5db;
            --gold: #b45309;
            --gold-bg: #fef3c7;
            --green: #166534;
            --green-bg: #dcfce7;
            --green-dot: #16a34a;
            --blue: #1d4ed8;
            --blue-bg: #dbeafe;
            --pink: #9d174d;
            --pink-bg: #fce7f3;
            --font-d: 'Barlow Condensed', sans-serif;
            --font-b: 'Barlow', sans-serif;
            --radius: 4px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .08);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, .08);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-b);
            font-size: 14px;
            line-height: 1.5;
        }

        /* TOPBAR */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 48px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .logo {
            font-family: var(--font-d);
            font-size: 22px;
            font-weight: 900;
            color: var(--text);
            letter-spacing: -.02em;
            text-transform: uppercase;
        }

        .logo span {
            color: var(--red);
        }

        .topbar-nav {
            display: flex;
            gap: 4px;
        }

        .topbar-nav a {
            padding: 6px 12px;
            font-family: var(--font-d);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            text-decoration: none;
            border-radius: var(--radius);
            transition: background .15s, color .15s;
        }

        .topbar-nav a:hover {
            background: var(--bg);
            color: var(--text);
        }

        .topbar-nav a.active {
            color: var(--red);
            background: var(--red-light);
        }

        .topbar-auth {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-login {
            background: none;
            border: 1.5px solid var(--border-mid);
            color: var(--text);
            padding: 6px 14px;
            border-radius: var(--radius);
            font-family: var(--font-d);
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .04em;
            cursor: pointer;
            transition: border-color .15s;
        }

        .btn-login:hover {
            border-color: var(--text);
        }

        .btn-join {
            background: var(--red);
            border: none;
            color: #fff;
            padding: 7px 16px;
            border-radius: var(--radius);
            font-family: var(--font-d);
            font-weight: 800;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .04em;
            cursor: pointer;
            transition: background .15s;
        }

        .btn-join:hover {
            background: #b3001a;
        }

        .member-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-d);
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
        }

        .member-avatar {
            width: 30px;
            height: 30px;
            background: var(--red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 900;
            color: #fff;
        }

        /* HERO */
        .hero {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 36px 24px 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 70% 100% at 50% 110%, rgba(212, 0, 30, .06) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--red-light);
            border: 1px solid var(--red-mid);
            color: var(--red);
            font-family: var(--font-d);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 14px;
        }

        .hero h1 {
            font-family: var(--font-d);
            font-size: clamp(40px, 5vw, 64px);
            font-weight: 900;
            text-transform: uppercase;
            line-height: .95;
            letter-spacing: -.02em;
            color: var(--text);
            margin-bottom: 10px;
        }

        .hero h1 span {
            color: var(--red);
        }

        .hero p {
            color: var(--muted);
            font-size: 15px;
            max-width: 460px;
            margin: 0 auto;
        }

        /* PARTNER STRIP */
        .partner-strip {
            background: var(--text);
            padding: 8px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: var(--font-d);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .5);
        }

        .partner-strip strong {
            color: rgba(255, 255, 255, .9);
        }

        .partner-strip .dot {
            color: rgba(255, 255, 255, .2);
        }

        /* PROGRESS BANNER */
        .progress-banner {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .progress-banner .label {
            font-family: var(--font-d);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            white-space: nowrap;
        }

        .progress-banner .label strong {
            color: var(--text);
        }

        .progress-track {
            flex: 1;
            min-width: 140px;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--red), #f97316);
            border-radius: 3px;
        }

        .progress-pct {
            font-family: var(--font-d);
            font-weight: 800;
            font-size: 14px;
            color: var(--red);
            white-space: nowrap;
        }

        /* LAYOUT */
        .page-layout {
            display: grid;
            grid-template-columns: 1fr 284px;
            max-width: 1200px;
            margin: 0 auto;
            align-items: start;
        }

        @media (max-width: 860px) {
            .page-layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }
        }

        .main-panel {
            border-right: 1px solid var(--border);
        }

        /* TABS */
        .tabs {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            padding: 0 20px;
        }

        .tab {
            padding: 14px 16px 13px;
            font-family: var(--font-d);
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: color .15s, border-color .15s;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .tab:hover {
            color: var(--text);
        }

        .tab.active {
            color: var(--red);
            border-bottom-color: var(--red);
        }

        .tab .pill {
            background: var(--red);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 10px;
            line-height: 16px;
        }

        /* FILTER BAR */
        .filter-bar {
            background: var(--bg);
            border-bottom: 1px solid var(--border);
            padding: 10px 20px;
            display: flex;
            gap: 6px;
        }

        .filter-btn {
            padding: 5px 14px;
            font-family: var(--font-d);
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            border-radius: 20px;
            cursor: pointer;
            transition: all .15s;
            border: 1.5px solid transparent;
        }

        .filter-btn.active {
            background: var(--red);
            color: #fff;
            border-color: var(--red);
        }

        .filter-btn:not(.active) {
            background: var(--surface);
            color: var(--muted);
            border-color: var(--border-mid);
        }

        .filter-btn:not(.active):hover {
            border-color: var(--text);
            color: var(--text);
        }

        /* COMP GRID */
        .comp-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            padding: 16px;
        }

        @media (max-width: 560px) {
            .comp-grid {
                grid-template-columns: 1fr;
            }
        }

        .empty-state {
            grid-column: 1/-1;
            padding: 48px 24px;
            text-align: center;
            color: var(--muted);
            font-family: var(--font-d);
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        /* COMP CARD */
        .comp-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            display: flex;
            flex-direction: column;
            transition: box-shadow .15s, border-color .15s;
            animation: fadeUp .3s ease both;
        }

        .comp-card:hover {
            box-shadow: var(--shadow-md);
            border-color: rgba(212, 0, 30, .25);
        }

        .comp-card.featured {
            grid-column: 1/-1;
            flex-direction: row;
            min-height: 210px;
        }

        .card-thumb {
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 56px;
            flex-shrink: 0;
        }

        .comp-card:not(.featured) .card-thumb {
            width: 100%;
            height: 140px;
            border-radius: var(--radius) var(--radius) 0 0;
            overflow: hidden;
        }

        .comp-card.featured .card-thumb {
            width: 38%;
            height: auto;
            border-right: 1px solid var(--border);
            border-radius: var(--radius) 0 0 var(--radius);
        }

        .card-body {
            padding: 16px 18px 18px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        /* STATUS */
        .card-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: var(--font-d);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .card-status::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .s-active {
            color: var(--green);
        }

        .s-active::before {
            background: var(--green-dot);
        }

        .s-coming {
            color: var(--blue);
        }

        .s-coming::before {
            background: var(--blue);
        }

        .s-ended {
            color: var(--subtle);
        }

        .s-ended::before {
            background: var(--subtle);
        }

        .card-sponsor {
            font-family: var(--font-d);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--subtle);
            margin-bottom: 3px;
        }

        .card-title {
            font-family: var(--font-d);
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.1;
            color: var(--text);
            letter-spacing: -.01em;
            margin-bottom: 4px;
        }

        .comp-card.featured .card-title {
            font-size: 26px;
        }

        .card-value {
            font-family: var(--font-d);
            font-size: 13px;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 8px;
        }

        .card-desc {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.45;
            flex: 1;
            margin-bottom: 12px;
        }

        /* ENTRY TAG */
        .entry-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: var(--font-d);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 3px 9px;
            border-radius: 3px;
            margin-bottom: 12px;
            width: fit-content;
        }

        .tag-open {
            background: var(--green-bg);
            color: var(--green);
        }

        .tag-badge {
            background: var(--gold-bg);
            color: var(--gold);
        }

        .tag-activity {
            background: var(--blue-bg);
            color: var(--blue);
        }

        .tag-referral {
            background: var(--pink-bg);
            color: var(--pink);
        }

        .tag-raffle {
            background: var(--red-light);
            color: var(--red);
        }

        .tag-sponsored {
            background: var(--bg);
            color: var(--muted);
            border: 1px solid var(--border-mid);
        }

        /* PROGRESS BAR */
        .card-progress {
            margin-bottom: 12px;
        }

        .cp-label {
            display: flex;
            justify-content: space-between;
            font-family: var(--font-d);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .cp-label span {
            color: var(--gold);
        }

        .cp-track {
            height: 5px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }

        .cp-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--red), #f97316);
            border-radius: 3px;
        }

        /* BUTTONS */
        .card-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: auto;
        }

        .btn-enter {
            flex: 1;
            background: var(--red);
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: var(--radius);
            font-family: var(--font-d);
            font-weight: 800;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .05em;
            cursor: pointer;
            transition: background .15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-enter:hover {
            background: #b3001a;
        }

        .btn-enter:disabled {
            background: var(--border);
            color: var(--subtle);
            cursor: default;
        }

        .btn-enter.entered {
            background: var(--green-dot);
            cursor: default;
        }

        .btn-notify {
            background: var(--surface);
            border: 1.5px solid var(--border-mid);
            color: var(--muted);
            padding: 10px 14px;
            border-radius: var(--radius);
            font-family: var(--font-d);
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            cursor: pointer;
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .btn-notify:hover, .btn-notify.notified {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-bg);
        }

        .btn-external {
            flex: 1;
            background: var(--text);
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: var(--radius);
            font-family: var(--font-d);
            font-weight: 800;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .05em;
            cursor: pointer;
            transition: background .15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-external:hover {
            background: #2d3142;
        }

        /* HOW IT WORKS */
        .how-section {
            background: var(--surface);
            padding: 36px 24px;
        }

        .how-title {
            font-family: var(--font-d);
            font-size: 26px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--text);
            margin-bottom: 4px;
        }

        .how-sub {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 24px;
            max-width: 560px;
        }

        .how-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 16px;
        }

        .how-step {
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 16px;
        }

        .how-num {
            width: 30px;
            height: 30px;
            background: var(--red);
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-d);
            font-size: 15px;
            font-weight: 900;
            color: #fff;
            margin-bottom: 10px;
        }

        .how-step h3 {
            font-family: var(--font-d);
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text);
            margin-bottom: 5px;
        }

        .how-step p {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }

        /* TAB PANELS */
        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        /* WINNERS / MY ENTRIES */
        .winners-list, .mine-list {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .winner-row {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .winner-trophy {
            font-size: 28px;
            flex-shrink: 0;
        }

        .winner-info {
            flex: 1;
            min-width: 0;
        }

        .winner-comp {
            font-family: var(--font-d);
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text);
            margin-bottom: 2px;
        }

        .winner-meta {
            font-size: 12px;
            color: var(--muted);
        }

        .winner-badge {
            font-family: var(--font-d);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 3px 9px;
            border-radius: 3px;
            background: var(--gold-bg);
            color: var(--gold);
            flex-shrink: 0;
        }

        .mine-row {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .mine-emoji {
            font-size: 28px;
            flex-shrink: 0;
        }

        .mine-info {
            flex: 1;
            min-width: 0;
        }

        .mine-title {
            font-family: var(--font-d);
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text);
            margin-bottom: 2px;
        }

        .mine-detail {
            font-size: 12px;
            color: var(--muted);
        }

        .mine-status-badge {
            font-family: var(--font-d);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 3px 9px;
            border-radius: 3px;
            flex-shrink: 0;
        }

        .msb-active {
            background: var(--green-bg);
            color: var(--green);
        }

        .msb-ended {
            background: var(--bg);
            color: var(--subtle);
        }

        .panel-empty {
            padding: 56px 24px;
            text-align: center;
        }

        .panel-empty .pe-icon {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .panel-empty .pe-title {
            font-family: var(--font-d);
            font-size: 18px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--text);
            margin-bottom: 6px;
        }

        .panel-empty .pe-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 18px;
        }

        .panel-empty .pe-cta {
            display: inline-block;
            background: var(--red);
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: var(--radius);
            font-family: var(--font-d);
            font-weight: 800;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .05em;
            cursor: pointer;
        }

        /* SIDEBAR */
        .sidebar {
            background: var(--surface);
            border-left: 1px solid var(--border);
            position: sticky;
            top: 49px;
            max-height: calc(100vh - 49px);
            overflow-y: auto;
        }

        .sb-section {
            border-bottom: 1px solid var(--border);
            padding: 18px 16px;
        }

        .sb-title {
            font-family: var(--font-d);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--subtle);
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sb-title a {
            color: var(--red);
            text-decoration: none;
            font-size: 11px;
        }

        .ad-card {
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .ad-inner {
            background: linear-gradient(145deg, #fff5f6, #fff);
            border-top: 3px solid var(--red);
            padding: 16px 14px;
            position: relative;
        }

        .ad-sponsored {
            position: absolute;
            top: 6px;
            right: 8px;
            font-family: var(--font-d);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--subtle);
        }

        .ad-eyebrow {
            font-family: var(--font-d);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--red);
            margin-bottom: 4px;
        }

        .ad-headline {
            font-family: var(--font-d);
            font-size: 20px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--text);
            line-height: 1;
            margin-bottom: 4px;
        }

        .ad-sub {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 12px;
        }

        .ad-cta {
            width: 100%;
            background: var(--red);
            color: #fff;
            border: none;
            padding: 9px;
            font-family: var(--font-d);
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            border-radius: var(--radius);
            cursor: pointer;
        }

        .mini-card {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 10px;
        }

        .mini-card:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .mini-thumb {
            width: 54px;
            height: 54px;
            background: var(--bg);
            border-radius: 3px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .mini-info {
            flex: 1;
            min-width: 0;
        }

        .mini-status {
            font-family: var(--font-d);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 2px;
        }

        .mini-title {
            font-family: var(--font-d);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text);
            margin-bottom: 2px;
            line-height: 1.1;
        }

        .mini-val {
            font-size: 11px;
            color: var(--muted);
        }

        .mini-btn {
            width: 100%;
            margin-top: 6px;
            background: none;
            border: 1.5px solid var(--border-mid);
            color: var(--muted);
            padding: 5px;
            font-family: var(--font-d);
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-radius: 3px;
            cursor: pointer;
            transition: all .15s;
        }

        .mini-btn.notified {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-bg);
        }

        /* TOAST */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 4px solid var(--green-dot);
            padding: 13px 18px;
            border-radius: var(--radius);
            font-family: var(--font-d);
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            z-index: 9999;
            transform: translateY(60px);
            opacity: 0;
            transition: transform .25s, opacity .25s;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 320px;
            box-shadow: var(--shadow-md);
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* MODAL */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 19, 24, .5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s;
            backdrop-filter: blur(3px);
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        .modal {
            background: var(--surface);
            border: 1px solid var(--border);
            border-top: 3px solid var(--red);
            width: 100%;
            max-width: 400px;
            padding: 28px 24px;
            border-radius: 6px;
            transform: translateY(16px);
            transition: transform .25s;
            position: relative;
            box-shadow: var(--shadow-md);
        }

        .modal-overlay.open .modal {
            transform: translateY(0);
        }

        .modal-close {
            position: absolute;
            top: 12px;
            right: 14px;
            background: none;
            border: none;
            color: var(--subtle);
            font-size: 18px;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
            border-radius: 3px;
        }

        .modal-close:hover {
            background: var(--bg);
            color: var(--text);
        }

        .modal-eyebrow {
            font-family: var(--font-d);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--red);
            margin-bottom: 6px;
        }

        .modal h2 {
            font-family: var(--font-d);
            font-size: 26px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--text);
            line-height: 1;
            margin-bottom: 6px;
        }

        .modal p {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .modal-field {
            margin-bottom: 12px;
        }

        .modal-field input {
            width: 100%;
            background: var(--bg);
            border: 1.5px solid var(--border-mid);
            color: var(--text);
            padding: 10px 13px;
            border-radius: var(--radius);
            font-family: var(--font-b);
            font-size: 14px;
            outline: none;
            transition: border-color .15s;
        }

        .modal-field input:focus {
            border-color: var(--red);
            background: #fff;
        }

        .modal-field input::placeholder {
            color: var(--subtle);
        }

        .modal-submit {
            width: 100%;
            background: var(--red);
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: var(--radius);
            font-family: var(--font-d);
            font-weight: 900;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: .07em;
            cursor: pointer;
            margin-top: 4px;
        }

        .modal-submit:hover {
            background: #b3001a;
        }

        .modal-alt {
            text-align: center;
            margin-top: 12px;
            font-size: 13px;
            color: var(--muted);
        }

        .modal-alt a {
            color: var(--red);
            text-decoration: none;
            font-weight: 600;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .comp-card:nth-child(1) {
            animation-delay: .04s
        }

        .comp-card:nth-child(2) {
            animation-delay: .08s
        }

        .comp-card:nth-child(3) {
            animation-delay: .12s
        }

        .comp-card:nth-child(4) {
            animation-delay: .16s
        }

        .comp-card:nth-child(5) {
            animation-delay: .20s
        }

        .comp-card:nth-child(6) {
            animation-delay: .24s
        }

        .comp-card:nth-child(7) {
            animation-delay: .28s
        }

        .comp-card:nth-child(8) {
            animation-delay: .32s
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="logo">Four<span>Four</span>Two</div>
    <nav class="topbar-nav">
        <a href="">Football</a>
        <a href="">News</a>
        <a href="">Quizzes</a>
        <a href="" class="active">Club Prizes</a>
        <a href="">Video</a>
    </nav>
    <div class="topbar-auth">
        @if($member)
        <div class="member-chip">
            Hi, {{ $member->first_name }}
            <div class="member-avatar">{{ strtoupper(substr($member->first_name, 0, 1)) }}</div>
        </div>
        @else
        <button class="btn-login" onclick="openModal('login')">Sign In</button>
        <button class="btn-join" onclick="openModal('join')">Join Free</button>
        @endif
    </div>
</header>

<section class="hero">
    <div class="hero-eyebrow">🏆 Members Only</div>
    <h1>Club <span>Prizes</span></h1>
    <p>Exclusive prize draws and competitions included with FourFourTwo Club membership.</p>
</section>

<div class="partner-strip">
    In partnership with
    <strong>Rakuten</strong><span class="dot"> · </span>
    <strong>nacon</strong><span class="dot"> · </span>
    <strong>Polo Ralph Lauren</strong>
</div>

<?php if ($member):
    $inProgress = collect($competitions)->filter(fn($c) => $c['progress'] && !$c['has_entered'] && $c['progress']['percentage'] > 0 && $c['progress']['percentage'] < 100);
    $overallPct = $inProgress->count() ? (int)$inProgress->avg(fn($c) => $c['progress']['percentage']) : 0;
    ?>

    <?php if ($inProgress->count()): ?>
    <div class="progress-banner">
        <div class="label">
            <strong>Your Progress</strong> — <?= $inProgress->count() ?>
            competition<?= $inProgress->count() > 1 ? 's' : '' ?> unlocking soon
        </div>
        <div class="progress-track">
            <div class="progress-fill" style="width: <?= $overallPct ?>%"></div>
        </div>
        <div class="progress-pct"><?= $overallPct ?>%</div>
    </div>
<?php endif; ?>
<?php endif; ?>

<div class="page-layout">

    <main class="main-panel">

        <div class="tabs">
            <div class="tab active" data-tab="competitions" onclick="setTab(this,'competitions')">
                Competitions
                <?php $activeCount = collect($competitions)->where('status', 'active')->count(); ?>
                <?php if ($activeCount): ?>
                    <span class="pill"><?= $activeCount ?></span>
                <?php endif; ?>
            </div>
            <div class="tab" onclick="setTab(this,'winners')">Past Winners</div>
            <div class="tab" onclick="setTab(this,'mine')">
                My Entries
                @if($member)
                <?php $myEntryCount = collect($competitions)->where('has_entered', true)->count(); ?>
                <?php if ($myEntryCount): ?>
                    <span class="pill"><?= $myEntryCount ?></span>
                <?php endif; ?>
                @endif
            </div>
            <div class="tab" onclick="setTab(this,'how')">How It Works</div>
        </div>

        <div class="filter-bar" id="filterBar">
            <button class="filter-btn active" onclick="setFilter(this,'all')">All</button>
            <button class="filter-btn" onclick="setFilter(this,'active')">Active</button>
            <button class="filter-btn" onclick="setFilter(this,'coming_soon')">Coming Soon</button>
            <button class="filter-btn" onclick="setFilter(this,'ended')">Ended</button>
        </div>

        <div class="tab-panel active" id="panel-competitions">
            <div class="comp-grid" id="compGrid">

                <?php if (!empty($competitions)): ?>
                    <?php foreach ($competitions as $i => $comp): ?>

                        <?php
                        $isFeatured = $i === 0 && $comp['status'] === 'active';
                        $isEntered = $comp['has_entered'];
                        $isNotified = $comp['has_notification'];
                        $hasProgress = $comp['progress'] && !$isEntered && $comp['entry_type'] !== 'open';
                        $progress = $comp['progress'] ?? null;
                        $sponsor = $comp['settings']['sponsor'] ?? null;

                        $statusCls = match ($comp['status']) {
                            'active' => 's-active',
                            'coming_soon' => 's-coming',
                            default => 's-ended'
                        };

                        $statusLabel = match ($comp['status']) {
                            'active' => ($comp['ends_at'] ? 'Closes ' . $comp['ends_at'] : 'Active'),
                            'coming_soon' => 'Coming Soon',
                            default => 'Ended'
                        };

                        $tagCls = match ($comp['entry_type']) {
                            'open' => 'tag-open',
                            'badge' => 'tag-badge',
                            'activity' => 'tag-activity',
                            'referral' => 'tag-referral',
                            'raffle' => 'tag-raffle',
                            default => 'tag-sponsored'
                        };

                        $tagLabel = match ($comp['entry_type']) {
                            'open' => '🔓 Open Entry',
                            'badge' => '🏅 Badge Unlock',
                            'activity' => '📊 Activity Required',
                            'referral' => '👥 Refer a Friend',
                            'raffle' => '🎰 Raffle Draw',
                            default => '🔗 Sponsored'
                        };

                        $titleLower = strtolower($comp['title']);

                        $emoji = match (true) {
                            str_contains($titleLower, 'controller') || str_contains($titleLower, 'ps5') => '🎮',
                            str_contains($titleLower, 'shirt') || str_contains($titleLower, 'signed') => '⚽',
                            str_contains($titleLower, 'ticket') => '🎟️',
                            str_contains($titleLower, 'headset') || str_contains($titleLower, 'rig') => '🎧',
                            str_contains($titleLower, 'tv') || str_contains($titleLower, 'samsung') => '📺',
                            str_contains($titleLower, 'scholarship') => '🏫',
                            default => '🏆'
                        };
                        ?>

                        <div class="comp-card <?= $isFeatured ? 'featured' : '' ?>"
                             data-status="<?= $comp['status'] ?>">
                            <div class="card-thumb"><?= $emoji ?></div>

                            <div class="card-body">
                                <div class="card-status <?= $statusCls ?>"><?= $statusLabel ?></div>

                                <?php if ($sponsor): ?>
                                    <div class="card-sponsor"><?= $sponsor ?></div>
                                <?php endif; ?>

                                <div class="card-title"><?= $comp['title'] ?></div>

                                <?php if ($comp['prize']): ?>
                                    <div class="card-value"><?= $comp['prize'] ?></div>
                                <?php endif; ?>

                                <div class="card-desc"><?= $comp['description'] ?></div>

                                <div class="entry-tag <?= $tagCls ?>"><?= $tagLabel ?></div>

                                <?php if ($hasProgress && $progress): ?>
                                    <div class="card-progress">
                                        <div class="cp-label">
                                            <?php if (($progress['details'][0]['type'] ?? '') === 'return_visits'): ?>
                                                Site visits
                                            <?php else: ?>
                                                Progress
                                            <?php endif; ?>
                                            <span><?= $progress['met'] ?> / <?= $progress['total'] ?></span>
                                        </div>
                                        <div class="cp-track">
                                            <div class="cp-fill" style="width: <?= $progress['percentage'] ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="card-actions">

                                    <?php if ($comp['status'] === 'ended'): ?>
                                        <button class="btn-enter" disabled>Competition Now Closed</button>

                                    <?php elseif ($comp['status'] === 'coming_soon'): ?>
                                        <button
                                                class="btn-notify <?= $isNotified ? 'notified' : '' ?>"
                                                onclick="handleNotify(event, <?= $comp['id'] ?>)"
                                        >
                                            🔔 <?= $isNotified ? 'Notified' : 'Notify Me' ?>
                                        </button>

                                    <?php elseif ($comp['entry_type'] === 'sponsored'): ?>
                                        <button
                                                class="btn-external"
                                                onclick="handleExternal(event, <?= $comp['id'] ?>, '<?= $comp['external_url'] ?>')"
                                        >
                                            ↗ Visit <?= $sponsor ?? 'Sponsor' ?>
                                        </button>

                                    <?php elseif ($comp['entry_type'] === 'badge' && $progress && !$progress['unlocked'] && !$isEntered): ?>
                                        <button class="btn-enter" disabled>Locked — Earn Badges</button>

                                    <?php elseif ($isEntered): ?>
                                        <button class="btn-enter entered">✓ Entered</button>

                                    <?php else: ?>
                                        <button
                                                class="btn-enter"
                                                onclick="handleEnter(event, <?= $comp['id'] ?>)"
                                        >
                                            <?= $comp['entry_type'] === 'referral' ? 'Share Referral Link' : 'Enter Prize Draw' ?>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($comp['status'] === 'active' && !$isEntered && $comp['entry_type'] !== 'sponsored'): ?>
                                        <button
                                                class="btn-notify <?= $isNotified ? 'notified' : '' ?>"
                                                onclick="handleNotify(event, <?= $comp['id'] ?>)"
                                                title="<?= $isNotified ? 'Notifications on' : 'Notify me' ?>"
                                        >🔔
                                        </button>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No competitions available right now. Check back soon.</div>
                <?php endif; ?>

            </div><!-- /comp-grid -->
        </div><!-- /panel-competitions -->

        <div class="tab-panel" id="panel-winners">
            <?php
            $entered = collect($competitions)->filter(fn($c) => $c['has_entered'] ?? false);
            $winners = collect($competitions)->filter(fn($c) => ($c['status'] === 'ended') && ($c['winner_announced'] ?? false));
            ?>
            <?php if ($winners->count()): ?>
                <div class="winners-list">
                    <?php foreach ($winners as $w): ?>
                        <div class="winner-row">
                            <div class="winner-trophy">🏆</div>
                            <div class="winner-info">
                                <div class="winner-comp"><?= $w['title'] ?></div>
                                <div class="winner-meta"><?= $w['winner_name'] ?? 'Winner announced' ?>
                                    · <?= $w['ended_at'] ?? '' ?></div>
                            </div>
                            <div class="winner-badge">Closed</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="panel-empty">
                    <div class="pe-icon">🏅</div>
                    <div class="pe-title">No winners announced yet</div>
                    <div class="pe-sub">Check back after competitions close to see who took home the prizes.</div>
                </div>
            <?php endif; ?>
        </div><!-- /panel-winners -->

        <div class="tab-panel" id="panel-mine">
            <?php if (!$member): ?>
                <div class="panel-empty">
                    <div class="pe-icon">🔒</div>
                    <div class="pe-title">Sign in to see your entries</div>
                    <div class="pe-sub">Create a free account to enter competitions and track your draws here.</div>
                    <button class="pe-cta" onclick="openModal('join')">Join Free →</button>
                </div>
            <?php else: ?>
                <?php $myEntries = collect($competitions)->filter(fn($c) => $c['has_entered'] ?? false); ?>
                <?php if ($myEntries->count()): ?>
                    <div class="mine-list">
                        <?php foreach ($myEntries as $entry): ?>
                            <?php
                            $titleLower2 = strtolower($entry['title']);
                            $entryEmoji = match (true) {
                                str_contains($titleLower2, 'controller') || str_contains($titleLower2, 'ps5') => '🎮',
                                str_contains($titleLower2, 'shirt') || str_contains($titleLower2, 'signed') => '⚽',
                                str_contains($titleLower2, 'ticket') => '🎟️',
                                str_contains($titleLower2, 'headset') => '🎧',
                                str_contains($titleLower2, 'tv') => '📺',
                                default => '🏆'
                            };
                            $statusBadgeCls = $entry['status'] === 'active' ? 'msb-active' : 'msb-ended';
                            $statusBadgeTxt = $entry['status'] === 'active' ? 'Draw Pending' : 'Closed';
                            ?>
                            <div class="mine-row">
                                <div class="mine-emoji"><?= $entryEmoji ?></div>
                                <div class="mine-info">
                                    <div class="mine-title"><?= $entry['title'] ?></div>
                                    <div class="mine-detail">
                                        <?= $entry['prize'] ?? '' ?>
                                        <?php if ($entry['entry_count'] ?? null): ?>
                                            · <?= $entry['entry_count'] ?> entr<?= $entry['entry_count'] === 1 ? 'y' : 'ies' ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mine-status-badge <?= $statusBadgeCls ?>"><?= $statusBadgeTxt ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="panel-empty">
                        <div class="pe-icon">🎯</div>
                        <div class="pe-title">No entries yet</div>
                        <div class="pe-sub">Enter an active competition and your draws will appear here.</div>
                        <button class="pe-cta"
                                onclick="setTab(document.querySelector('[data-tab=competitions]'), 'competitions')">
                            Browse Competitions →
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div><!-- /panel-mine -->

        <div class="tab-panel" id="panel-how">
            <section class="how-section" id="howSection">
                <div class="how-title">How Club Prizes Works</div>
                <div class="how-sub">Club Prizes are our way of rewarding FourFourTwo Club members, with exclusive
                    competitions and prize draws added throughout the season.
                </div>
                <div class="how-steps">
                    <div class="how-step">
                        <div class="how-num">1</div>
                        <h3>Join Free</h3>
                        <p>Create a FourFourTwo Club account to unlock member-only prize draws and competitions.</p>
                    </div>
                    <div class="how-step">
                        <div class="how-num">2</div>
                        <h3>Earn Badges</h3>
                        <p>Read articles, play quizzes, comment and return to the site to unlock entry to
                            competitions.</p></div>
                    <div class="how-step">
                        <div class="how-num">3</div>
                        <h3>Enter &amp; Win</h3>
                        <p>Once your entry is unlocked, enter with one click. Winners are drawn and notified by
                            email.</p></div>
                    <div class="how-step">
                        <div class="how-num">4</div>
                        <h3>Stay Notified</h3>
                        <p>Register for notifications on upcoming competitions and we'll email you when they go
                            live.</p></div>
                </div>
            </section>
        </div><!-- /panel-how -->

    </main>

    <aside class="sidebar">

        <?php if ($hasActive && $featured): ?>
            <div class="sb-section">
                <div class="sb-title">Featured Giveaway</div>
                <div class="ad-card">
                    <div class="ad-inner">
                        <div class="ad-sponsored">Sponsored</div>
                        <div class="ad-eyebrow">Live Now</div>
                        <div class="ad-headline"><?= \App\Framework\Support\Str::limit($featured['title'], 40) ?></div>
                        <div class="ad-sub">
                            <?= implode(' · ', array_filter([$featured['settings']['sponsor'] ?? null, $featured['prize']])) ?>
                        </div>
                        <button class="ad-cta" onclick="handleEnter(event, <?= $featured['id'] ?>)">
                            Enter Free Draw →
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php $comingSoon = collect($competitions)->where('status', 'coming_soon'); ?>
        <?php if ($comingSoon->count()): ?>
            <div class="sb-section">
                <div class="sb-title">Coming Soon <a href="#">View All</a></div>

                <?php foreach ($comingSoon as $comp): ?>
                    <?php $isNotified = $comp['has_notification']; ?>
                    <div class="mini-card">
                        <div class="mini-thumb">🎮</div>
                        <div class="mini-info">
                            <div class="mini-status s-coming">Coming Soon</div>
                            <div class="mini-title"><?= $comp['title'] ?></div>

                            <?php if ($comp['prize']): ?>
                                <div class="mini-val"><?= $comp['prize'] ?></div>
                            <?php endif; ?>

                            <button
                                    class="mini-btn <?= $isNotified ? 'notified' : '' ?>"
                                    onclick="handleNotify(event, <?= $comp['id'] ?>)"
                            >
                                🔔 <?= $isNotified ? 'Notified' : 'Notify Me' ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>

        <div class="sb-section">
            <div class="sb-title">My Progress</div>

            <?php if ($member): ?>

                <?php
                $inProgress = collect($competitions)->filter(fn($c) => $c['progress'] &&
                        !$c['has_entered'] &&
                        $c['progress']['percentage'] > 0 &&
                        $c['progress']['percentage'] < 100
                );

                $overallPct = $inProgress->count()
                        ? (int)$inProgress->avg(fn($c) => $c['progress']['percentage'])
                        : 0;
                ?>

                <?php if ($inProgress->count()): ?>
                    <div style="color:var(--muted);font-size:13px;margin-bottom:8px;">
                        <?= $inProgress->count() ?>
                        competition<?= $inProgress->count() > 1 ? 's' : '' ?>
                        unlocking soon
                    </div>

                    <div class="card-progress">
                        <div class="cp-label">
                            Overall <span><?= $overallPct ?>%</span>
                        </div>
                        <div class="cp-track">
                            <div class="cp-fill" style="width: <?= $overallPct ?>%"></div>
                        </div>
                    </div>

                <?php else: ?>
                    <div style="color:var(--muted);font-size:13px;">
                        No competitions in progress.
                    </div>
                <?php endif; ?>

            <?php else: ?>

                <div style="color:var(--muted);font-size:13px;margin-bottom:12px;">
                    Sign in to track your competition unlock progress.
                </div>
                <button class="btn-join" style="width:100%" onclick="openModal('join')">
                    Join Free
                </button>

            <?php endif; ?>

        </div>

    </aside>
</div>

<div class="toast" id="toast">
    <span id="toastIcon">✅</span>
    <span id="toastMsg"></span>
</div>

<div class="modal-overlay" id="modalOverlay" onclick="closeOutside(event)">
    <div class="modal">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <div class="modal-eyebrow">Club Prizes</div>
        <h2 id="modalTitle">Create account</h2>
        <p id="modalDesc">Join free to enter prize draws and unlock exclusive competitions.</p>

        <div class="modal-field" id="nameField">
            <input type="text" placeholder="First name"/>
        </div>

        <div class="modal-field">
            <input type="email" placeholder="Email address"/>
        </div>

        <div class="modal-field">
            <input type="password" placeholder="Password"/>
        </div>

        <button class="modal-submit" id="modalSubmit">
            Create Free Account →
        </button>

        <div class="modal-alt" id="modalAlt">
            Already a member?
            <a>Sign in</a>
        </div>

    </div>
</div>

<script>
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── FILTER ─────────────────────────────────────────────────────────────────
    function setFilter(btn, filter) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.comp-card').forEach(card => {
            card.style.display = (filter === 'all' || card.dataset.status === filter) ? '' : 'none';
        });
    }

    // ── TABS ───────────────────────────────────────────────────────────────────
    function setTab(el, tab) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        el.classList.add('active');

        // Show/hide all panels
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        const panel = document.getElementById('panel-' + tab);
        if (panel) panel.classList.add('active');

        // Only show the filter bar on the main competitions tab
        const filterBar = document.getElementById('filterBar');
        if (filterBar) filterBar.style.display = tab === 'competitions' ? '' : 'none';
    }

    // ── ENTER ──────────────────────────────────────────────────────────────────
    function handleEnter(e, id) {
        if (e) e.stopPropagation();

    @
        if (!$member)
            openModal('join', 'Sign up to enter this competition and unlock exclusive prize draws.');
        return;
    @endif

        fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/competitions/${id}/enter`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
        })
            .then(r => r.json())
            .then(data => {
                if (data.error === 'unauthenticated') {
                    openModal('join');
                    return;
                }
                if (data.success) {
                    showToast('✅', data.message);
                    // Update button in place without a full reload
                    const btn = e?.target?.closest('.btn-enter');
                    if (btn) {
                        btn.textContent = '✓ Entered';
                        btn.classList.add('entered');
                        btn.disabled = true;
                    }
                } else {
                    showToast('⚠️', data.message ?? 'Something went wrong.');
                }
            })
            .catch(() => showToast('⚠️', 'Could not connect. Please try again.'));
    }

    // ── NOTIFY ─────────────────────────────────────────────────────────────────
    function handleNotify(e, id) {
        if (e) e.stopPropagation();

    @
        if (!$member)
            openModal('join', 'Create a free account to be notified when this competition opens.');
        return;
    @endif

        fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/competitions/${id}/notify`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const btns = document.querySelectorAll(`[onclick="handleNotify(event, ${id})"]`);
                    btns.forEach(btn => {
                        btn.classList.add('notified');
                        btn.textContent = '🔔 Notified';
                    });
                    showToast('🔔', data.message);
                } else {
                    showToast('⚠️', data.message ?? 'Something went wrong.');
                }
            })
            .catch(() => showToast('⚠️', 'Could not connect. Please try again.'));
    }

    // ── EXTERNAL ───────────────────────────────────────────────────────────────
    function handleExternal(e, id, url) {
        if (e) e.stopPropagation();

    @
        if (!$member)
            openModal('join', 'Create a free account, then visit the sponsor page to qualify for entry.');
        return;
    @endif

        showToast('↗', 'Opening sponsor page — your entry will be tracked automatically.');
        if (url) window.open(url, '_blank');
    }

    // ── TOAST ──────────────────────────────────────────────────────────────────
    let toastTimer;

    function showToast(icon, msg) {
        clearTimeout(toastTimer);
        const t = document.getElementById('toast');
        document.getElementById('toastIcon').textContent = icon;
        document.getElementById('toastMsg').textContent = msg;
        t.classList.add('show');
        toastTimer = setTimeout(() => t.classList.remove('show'), 4000);
    }

    // ── MODAL ──────────────────────────────────────────────────────────────────
    function openModal(type, desc) {
        const title = document.getElementById('modalTitle');
        const body = document.getElementById('modalDesc');
        const submit = document.getElementById('modalSubmit');
        const alt = document.getElementById('modalAlt');
        const nameF = document.getElementById('nameField');

        if (type === 'login') {
            title.textContent = 'Welcome back';
            body.textContent = desc ?? 'Sign in to your FourFourTwo Club account.';
            submit.textContent = 'Sign In →';
            submit.onclick = () => window.location = '';
            nameF.style.display = 'none';
            alt.innerHTML = 'No account? <a href="#" onclick="openModal(\'join\')">Join free</a>';
        } else {
            title.textContent = 'Create account';
            body.textContent = desc ?? 'Join free to enter prize draws and unlock exclusive competitions.';
            submit.textContent = 'Create Free Account →';
            submit.onclick = () => window.location = '';
            nameF.style.display = '';
            alt.innerHTML = 'Already a member? <a href="">Sign in</a>';
        }
        document.getElementById('modalOverlay').classList.add('open');
    }

    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('open');
    }

    function closeOutside(e) {
        if (e.target.id === 'modalOverlay') closeModal();
    }
</script>
</body>
</html>