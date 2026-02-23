<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Prizes – FourFourTwo</title>
    <!--
      Template: competitions/index
      Data provided by CompetitionController::index():
        $competitions  — array of decorated competition data
        $featured      — ?array single featured competition (or null)
        $member        — ?Member current authenticated member (or null)
        $hasActive     — bool  whether any active competitions exist (controls ad slot)
    -->
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        :root {
            --red: #d4001e;
            --red-light: #ffeef1;
            --red-mid: #f5c2ca;
            --dark: #111318;
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
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .08), 0 1px 2px rgba(0, 0, 0, .04);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, .08), 0 2px 4px rgba(0, 0, 0, .04);
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

        /* ── TOPBAR ─────────────────────────────────────────────────── */
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

        /* ── HERO ────────────────────────────────────────────────────── */
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
            margin: 0 auto 20px;
        }

        /* ── PARTNER STRIP ───────────────────────────────────────────── */
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

        /* ── PROGRESS BANNER ─────────────────────────────────────────── */
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
            transition: width .5s ease;
        }

        .progress-pct {
            font-family: var(--font-d);
            font-weight: 800;
            font-size: 14px;
            color: var(--red);
            white-space: nowrap;
        }

        /* ── LAYOUT ──────────────────────────────────────────────────── */
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

        /* ── MAIN PANEL ──────────────────────────────────────────────── */
        .main-panel {
            border-right: 1px solid var(--border);
        }

        /* ── TABS ────────────────────────────────────────────────────── */
        .tabs {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            padding: 0 20px;
            gap: 0;
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

        .tab .badge-pill {
            background: var(--red);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 10px;
            line-height: 16px;
        }

        /* ── FILTER BAR ──────────────────────────────────────────────── */
        .filter-bar {
            background: var(--bg);
            border-bottom: 1px solid var(--border);
            padding: 10px 20px;
            display: flex;
            gap: 6px;
            align-items: center;
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

        /* ── COMP GRID ───────────────────────────────────────────────── */
        .comp-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
        }

        @media (max-width: 560px) {
            .comp-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── COMP CARD ───────────────────────────────────────────────── */
        .comp-card {
            background: var(--surface);
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            cursor: pointer;
            transition: box-shadow .15s;
            animation: fadeUp .3s ease both;
        }

        .comp-card:hover {
            box-shadow: inset 0 0 0 2px rgba(212, 0, 30, .12);
        }

        .comp-card.featured {
            grid-column: 1 / -1;
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
        }

        .comp-card.featured .card-thumb {
            width: 38%;
            height: auto;
            border-right: 1px solid var(--border);
        }

        .card-body {
            padding: 16px 18px 18px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        /* status pill */
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

        /* entry type badge */
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

        /* progress bar */
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
            transition: width .4s ease;
        }

        /* action buttons */
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
            text-align: center;
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

        .btn-notify:hover,
        .btn-notify.notified {
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

        /* ── HOW IT WORKS ────────────────────────────────────────────── */
        .how-section {
            border-top: 1px solid var(--border);
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
            flex-shrink: 0;
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

        /* ── SIDEBAR ─────────────────────────────────────────────────── */
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

        /* ad card in sidebar */
        .ad-card {
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .ad-card.hidden {
            display: none;
        }

        .ad-card-inner {
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

        /* mini list card */
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

        /* member avatar chip */
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

        /* ── TOAST ───────────────────────────────────────────────────── */
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
            transition: transform .25s ease, opacity .25s ease;
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

        /* ── MODAL ───────────────────────────────────────────────────── */
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
            transition: background .15s;
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
            transition: background .15s;
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

        /* ── ANIMATIONS ──────────────────────────────────────────────── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .comp-card:nth-child(1) {
            animation-delay: .04s;
        }

        .comp-card:nth-child(2) {
            animation-delay: .08s;
        }

        .comp-card:nth-child(3) {
            animation-delay: .12s;
        }

        .comp-card:nth-child(4) {
            animation-delay: .16s;
        }

        .comp-card:nth-child(5) {
            animation-delay: .20s;
        }

        .comp-card:nth-child(6) {
            animation-delay: .24s;
        }

        .comp-card:nth-child(7) {
            animation-delay: .28s;
        }
    </style>
</head>
<body>

<!-- ================================================================
  TOPBAR
  member: {{ $member ? $member->display_name : null }}
================================================================ -->
<header class="topbar">
    <div class="logo">Four<span>Four</span>Two</div>
    <nav class="topbar-nav">
        <a href="#">Football</a>
        <a href="#">News</a>
        <a href="#">Quizzes</a>
        <a href="/competitions" class="active">Club Prizes</a>
        <a href="#">Video</a>
    </nav>
    <div class="topbar-auth" id="authArea">
        <button class="btn-login" onclick="openModal('login')">Sign In</button>
        <button class="btn-join" onclick="openModal('join')">Join Free</button>
    </div>
</header>

<!-- ================================================================
  HERO
================================================================ -->
<section class="hero">
    <div class="hero-eyebrow">🏆 Members Only</div>
    <h1>Club <span>Prizes</span></h1>
    <p>Exclusive prize draws and competitions included with FourFourTwo Club membership.</p>
</section>

<!-- ================================================================
  PARTNER STRIP
================================================================ -->
<div class="partner-strip">
    In partnership with
    <strong>Rakuten</strong> <span class="dot">·</span>
    <strong>nacon</strong> <span class="dot">·</span>
    <strong>Polo Ralph Lauren</strong>
</div>

<!-- ================================================================
  MEMBER PROGRESS BANNER
  Shown when $member is present and has progress data.
  progress: {{ $member->overallProgress }} (injected from CompetitionService)
================================================================ -->
<div class="progress-banner" id="progressBanner" style="display:none">
    <div class="label"><strong>Your Progress</strong> — competitions unlocking soon</div>
    <div class="progress-track">
        <div class="progress-fill" id="progressFill" style="width:0%"></div>
    </div>
    <div class="progress-pct" id="progressPct">0%</div>
</div>

<div class="page-layout">

    <!-- ==============================================================
      MAIN CONTENT
    ============================================================== -->
    <main class="main-panel">

        <!-- TABS -->
        <div class="tabs">
            <div class="tab active" onclick="setTab(this,'competitions')">
                Competitions <span class="badge-pill" id="activeCount">0</span>
            </div>
            <div class="tab" onclick="setTab(this,'winners')">Past Winners</div>
            <div class="tab" onclick="setTab(this,'mine')">My Entries</div>
            <div class="tab" onclick="setTab(this,'how')" id="howTab">How It Works</div>
        </div>

        <!-- FILTERS -->
        <div class="filter-bar">
            <button class="filter-btn active" onclick="setFilter(this,'all')">All</button>
            <button class="filter-btn" onclick="setFilter(this,'active')">Active</button>
            <button class="filter-btn" onclick="setFilter(this,'coming_soon')">Coming Soon</button>
            <button class="filter-btn" onclick="setFilter(this,'ended')">Ended</button>
        </div>

        <!-- COMPETITIONS GRID
             In production this grid is rendered server-side from $competitions.
             The JS below mirrors that rendering for the demo, reading from window.COMPETITIONS.
        -->
        <div class="comp-grid" id="compGrid"></div>

        <!-- HOW IT WORKS -->
        <section class="how-section" id="howSection" style="display:none">
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
                    <p>Read articles, play quizzes, comment and return to the site to unlock entry to competitions.</p>
                </div>
                <div class="how-step">
                    <div class="how-num">3</div>
                    <h3>Enter &amp; Win</h3>
                    <p>Once your entry is unlocked, enter with one click. Winners are drawn and notified by email.</p>
                </div>
                <div class="how-step">
                    <div class="how-num">4</div>
                    <h3>Stay Notified</h3>
                    <p>Register for notifications on upcoming competitions and we'll email you when they go live.</p>
                </div>
            </div>
        </section>

    </main>

    <!-- ==============================================================
      SIDEBAR
      $featured is passed from CompetitionController::index()
      $hasActive controls the ad slot visibility
    ============================================================== -->
    <aside class="sidebar">

        <!-- AD SLOT: hidden when $hasActive === false -->
        <div class="sb-section">
            <div class="sb-title">Featured Giveaway</div>
            <div class="ad-card" id="adSlot">
                <div class="ad-card-inner">
                    <div class="ad-sponsored">Sponsored</div>
                    <div class="ad-eyebrow">Live Now</div>
                    <div class="ad-headline" id="adHeadline">Win a PS5 Controller</div>
                    <div class="ad-sub" id="adSub">nacon Revolution 5 Pro · worth £179.99</div>
                    <button class="ad-cta" id="adCta">Enter Free Draw →</button>
                </div>
            </div>
        </div>

        <!-- COMING SOON LIST -->
        <div class="sb-section" id="sbComingSoon">
            <div class="sb-title">Coming Soon <a href="#">View All</a></div>
            <div id="sbComingList"></div>
        </div>

        <!-- MEMBER PROGRESS / SIGN UP CTA -->
        <div class="sb-section" id="sbProgress">
            <div class="sb-title">My Progress</div>
            <div id="sbProgressInner">
                <div style="color:var(--muted);font-size:13px;margin-bottom:12px;">Sign in to track your competition
                    unlock progress.
                </div>
                <button class="btn-join" style="width:100%" onclick="openModal('join')">Join Free</button>
            </div>
        </div>

    </aside>
</div>

<!-- ================================================================
  TOAST
================================================================ -->
<div class="toast" id="toast">
    <span id="toastIcon">✅</span>
    <span id="toastMsg">Done!</span>
</div>

<!-- ================================================================
  MODAL (sign in / join)
================================================================ -->
<div class="modal-overlay" id="modalOverlay" onclick="closeOutside(event)">
    <div class="modal">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <div class="modal-eyebrow" id="modalEyebrow">Club Prizes</div>
        <h2 id="modalTitle">Create account</h2>
        <p id="modalDesc">Join free to enter prize draws and unlock exclusive competitions.</p>
        <div class="modal-field"><input type="text" placeholder="First name" id="mFirst"/></div>
        <div class="modal-field"><input type="email" placeholder="Email address" id="mEmail"/></div>
        <div class="modal-field" id="mPwWrap"><input type="password" placeholder="Password" id="mPw"/></div>
        <button class="modal-submit" id="modalSubmit" onclick="submitModal()">Create Free Account →</button>
        <div class="modal-alt" id="modalAlt">
            Already a member? <a href="#" onclick="openModal('login')">Sign in</a>
        </div>
    </div>
</div>

<!-- ================================================================
  DATA BOOTSTRAP
  In production this block is rendered by the controller/view layer:
    <script>window.COMPETITIONS = {!! json_encode($competitions) !!};</script>
    <script>window.MEMBER = {!! json_encode($member ? ['name' => $member->display_name, 'initials' => $member->initials] : null) !!};</script>
    <script>window.HAS_ACTIVE = {!! json_encode($hasActive) !!};</script>
================================================================ -->
<script>
    window.COMPETITIONS = [
        {
            id: 1, title: "Win a PS5™ Controller Revolution 5 Pro Forest Camo",
            description: "Exclusive prize draw for FourFourTwo members. The ultimate pro controller with 3 back buttons and fully remappable controls.",
            slug: "ps5-controller-forest-camo",
            status: "active", entry_type: "open",
            starts_at: null, ends_at: "Nov 19",
            prize: "worth £179.99", sponsor: "nacon",
            external_url: null, has_entered: false, has_notification: true,
            entry_count: 1842, progress: null,
            featured: true, emoji: "🎮"
        },
        {
            id: 2, title: "Revolution X Unlimited Controller",
            description: "Next prize draw for FourFourTwo members.",
            slug: "revolution-x-unlimited",
            status: "coming_soon", entry_type: "activity",
            starts_at: "Dec 1", ends_at: null,
            prize: "worth £279.99", sponsor: "nacon",
            external_url: null, has_entered: false, has_notification: false,
            entry_count: 0,
            progress: {
                percentage: 57,
                met: 4,
                total: 7,
                details: [{type: "return_visits", current: 4, target: 7, met: false}]
            },
            featured: false, emoji: "🎮"
        },
        {
            id: 3, title: "Brazil 1970 World Cup Shirt Signed by Pelé",
            description: "A rare piece of football history for FourFourTwo Club members.",
            slug: "brazil-1970-pele-shirt",
            status: "active", entry_type: "badge",
            starts_at: null, ends_at: null,
            prize: null, sponsor: "Polo Ralph Lauren",
            external_url: null, has_entered: false, has_notification: false,
            entry_count: 312,
            progress: {
                percentage: 33,
                met: 1,
                total: 3,
                details: [{type: "badge_ids", current: 1, target: 3, met: false}]
            },
            featured: false, emoji: "⚽"
        },
        {
            id: 4, title: "Barcelona Home Shirt & Gillette Bundle",
            description: "Refer a friend who creates a FourFourTwo account to unlock entry.",
            slug: "barcelona-shirt-gillette",
            status: "active", entry_type: "referral",
            starts_at: null, ends_at: null,
            prize: null, sponsor: "Rakuten",
            external_url: null, has_entered: false, has_notification: false,
            entry_count: 540, progress: null,
            featured: false, emoji: "👕"
        },
        {
            id: 5, title: "3× Tickets – Chelsea vs Liverpool",
            description: "A prize draw raffle. Win prize codes instantly.",
            slug: "chelsea-vs-liverpool-tickets",
            status: "active", entry_type: "raffle",
            starts_at: null, ends_at: null,
            prize: null, sponsor: null,
            external_url: null, has_entered: false, has_notification: false,
            entry_count: 2201, progress: null,
            featured: false, emoji: "🎟️"
        },
        {
            id: 6, title: "Win a 55″ Samsung QLED TV",
            description: "Visit the Samsung store to qualify for entry. Samsung is gifting this prize to one lucky FourFourTwo Club member.",
            slug: "samsung-qled-tv",
            status: "active", entry_type: "sponsored",
            starts_at: null, ends_at: null,
            prize: "worth £1,299", sponsor: "Samsung",
            external_url: "https://samsung.com/uk/promo/fourfour",
            has_entered: false, has_notification: false,
            entry_count: 4897, progress: null,
            featured: false, emoji: "📺"
        },
        {
            id: 7, title: "Full-Time Football Scholarships",
            description: "A previous FourFourTwo member competition.",
            slug: "full-time-scholarships",
            status: "ended", entry_type: "open",
            starts_at: null, ends_at: null,
            prize: null, sponsor: null,
            external_url: null, has_entered: false, has_notification: false,
            entry_count: 8012, progress: null,
            featured: false, emoji: "🏫"
        }
    ];

    window.MEMBER = null; // replaced server-side with actual member data
    window.HAS_ACTIVE = true;
</script>

<script>
    // ── CONSTANTS ─────────────────────────────────────────────────
    const ENTRY_TAGS = {
        open: {label: '🔓 Open Entry', cls: 'tag-open'},
        badge: {label: '🏅 Badge Unlock', cls: 'tag-badge'},
        activity: {label: '📊 Activity Required', cls: 'tag-activity'},
        referral: {label: '👥 Refer a Friend', cls: 'tag-referral'},
        raffle: {label: '🎰 Raffle Draw', cls: 'tag-raffle'},
        sponsored: {label: '🔗 Sponsored', cls: 'tag-sponsored'},
    };

    const STATUS_CLS = {active: 's-active', coming_soon: 's-coming', ended: 's-ended'};
    const STATUS_LABEL = {active: 'Active', coming_soon: 'Coming Soon', ended: 'Ended'};

    // ── STATE ──────────────────────────────────────────────────────
    let currentFilter = 'all';
    const notified = new Set(window.COMPETITIONS.filter(c => c.has_notification).map(c => c.id));
    const entered = new Set(window.COMPETITIONS.filter(c => c.has_entered).map(c => c.id));

    // ── BOOT ───────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        renderGrid();
        renderSidebar();
        updateAdSlot();
        updateActiveCount();
        if (window.MEMBER) bootMember(window.MEMBER);
    });

    // ── RENDER GRID ────────────────────────────────────────────────
    function renderGrid() {
        const grid = document.getElementById('compGrid');
        grid.innerHTML = '';

        const visible = window.COMPETITIONS.filter(c => {
            if (currentFilter === 'all') return true;
            return c.status === currentFilter;
        });

        if (!visible.length) {
            grid.innerHTML = `<div style="padding:40px;text-align:center;color:var(--muted);font-family:var(--font-d);font-size:16px;font-weight:700;grid-column:1/-1;">No competitions in this category.</div>`;
            return;
        }

        visible.forEach((c, i) => {
            const card = buildCard(c, i === 0 && currentFilter === 'all' && c.featured);
            grid.appendChild(card);
        });
    }

    function buildCard(c, featured) {
        const tag = ENTRY_TAGS[c.entry_type] || ENTRY_TAGS.open;
        const isEntered = entered.has(c.id);
        const isNotified = notified.has(c.id);
        const hasProgress = c.progress && !isEntered && c.entry_type !== 'open';

        const div = document.createElement('div');
        div.className = 'comp-card' + (featured ? ' featured' : '');
        div.dataset.status = c.status;

        // Closes label
        const closesLabel = c.ends_at ? `Closes ${c.ends_at}` : STATUS_LABEL[c.status];

        // Progress bar HTML
        const progressHtml = hasProgress ? `
      <div class="card-progress">
        <div class="cp-label">
          ${c.progress.details[0]?.type === 'return_visits' ? 'Site visits' : 'Progress'}
          <span>${c.progress.met} / ${c.progress.total}</span>
        </div>
        <div class="cp-track"><div class="cp-fill" style="width:${c.progress.percentage}%"></div></div>
      </div>` : '';

        // CTA
        let ctaHtml = '';
        if (c.status === 'ended') {
            ctaHtml = `<button class="btn-enter" disabled>Competition Now Closed</button>`;
        } else if (c.status === 'coming_soon') {
            ctaHtml = `<button class="btn-notify ${isNotified ? 'notified' : ''}" onclick="handleNotify(event,${c.id})">
        🔔 ${isNotified ? 'Notified' : 'Notify Me'}
      </button>`;
        } else if (c.entry_type === 'sponsored') {
            ctaHtml = `<button class="btn-external" onclick="handleExternal(event,${c.id},'${c.external_url}')">↗ Visit ${c.sponsor || 'Sponsor'}</button>`;
        } else if (c.entry_type === 'badge' && c.progress && !c.progress.unlocked && !isEntered) {
            ctaHtml = `<button class="btn-enter" disabled>Locked — Earn Badges</button>`;
        } else if (c.entry_type === 'referral') {
            ctaHtml = `<button class="btn-enter" onclick="handleEnter(event,${c.id})">Share Referral Link</button>`;
        } else if (isEntered) {
            ctaHtml = `<button class="btn-enter entered">✓ Entered</button>`;
        } else {
            ctaHtml = `<button class="btn-enter" onclick="handleEnter(event,${c.id})">Enter Prize Draw</button>`;
        }

        // Notify alongside CTA for active non-entered
        const notifyBtn = c.status === 'active' && !isEntered && c.entry_type !== 'sponsored'
            ? `<button class="btn-notify ${isNotified ? 'notified' : ''}" onclick="handleNotify(event,${c.id})" title="Notify me">🔔</button>` : '';

        div.innerHTML = `
      <div class="card-thumb">${c.emoji}</div>
      <div class="card-body">
        <div class="card-status ${STATUS_CLS[c.status] || 's-active'}">${closesLabel}</div>
        ${c.sponsor ? `<div class="card-sponsor">${c.sponsor}</div>` : ''}
        <div class="card-title">${c.title}</div>
        ${c.prize ? `<div class="card-value">${c.prize}</div>` : ''}
        <div class="card-desc">${c.description}</div>
        <div class="entry-tag ${tag.cls}">${tag.label}</div>
        ${progressHtml}
        <div class="card-actions">
          ${ctaHtml}
          ${notifyBtn}
        </div>
      </div>`;

        return div;
    }

    // ── SIDEBAR ────────────────────────────────────────────────────
    function renderSidebar() {
        const coming = window.COMPETITIONS.filter(c => c.status === 'coming_soon');
        const list = document.getElementById('sbComingList');

        if (!coming.length) {
            document.getElementById('sbComingSoon').style.display = 'none';
            return;
        }

        list.innerHTML = coming.map(c => `
      <div class="mini-card">
        <div class="mini-thumb">${c.emoji}</div>
        <div class="mini-info">
          <div class="mini-status s-coming">Coming Soon</div>
          <div class="mini-title">${c.title}</div>
          ${c.prize ? `<div class="mini-val">${c.prize}</div>` : ''}
          <button class="mini-btn ${notified.has(c.id) ? 'notified' : ''}" onclick="handleNotify(event,${c.id})">
            🔔 ${notified.has(c.id) ? 'Notified' : 'Notify Me'}
          </button>
        </div>
      </div>`).join('');
    }

    function updateAdSlot() {
        const slot = document.getElementById('adSlot');
        if (!window.HAS_ACTIVE) {
            slot.classList.add('hidden');
            return;
        }

        const featured = window.COMPETITIONS.find(c => c.featured && c.status === 'active')
            || window.COMPETITIONS.find(c => c.status === 'active');
        if (!featured) {
            slot.classList.add('hidden');
            return;
        }

        document.getElementById('adHeadline').textContent = featured.title;
        document.getElementById('adSub').textContent = [featured.sponsor, featured.prize].filter(Boolean).join(' · ');
        document.getElementById('adCta').onclick = () => handleEnter(null, featured.id);
    }

    function updateActiveCount() {
        const count = window.COMPETITIONS.filter(c => c.status === 'active').length;
        document.getElementById('activeCount').textContent = count;
    }

    // ── ENTER ──────────────────────────────────────────────────────
    function handleEnter(e, id) {
        if (e) e.stopPropagation();

        if (!window.MEMBER) {
            openModal('join', 'Sign up to enter this competition and unlock exclusive prize draws.');
            return;
        }

        if (entered.has(id)) return;

        // POST /competitions/{id}/enter
        fetch(`/competitions/${id}/enter`, {method: 'POST', headers: {'X-CSRF-TOKEN': csrfToken()}})
            .then(r => r.json())
            .then(data => {
                if (data.error === 'unauthenticated') {
                    openModal('join');
                    return;
                }
                if (data.success) {
                    entered.add(id);
                    renderGrid();
                    showToast('✅', data.message || "You're entered! Good luck 🤞");
                } else {
                    showToast('⚠️', data.message || 'Something went wrong.');
                }
            })
            .catch(() => {
                // Demo fallback when no server
                entered.add(id);
                renderGrid();
                showToast('✅', "You're entered into the draw! Good luck 🤞");
            });
    }

    // ── NOTIFY ─────────────────────────────────────────────────────
    function handleNotify(e, id) {
        if (e) e.stopPropagation();

        if (!window.MEMBER) {
            openModal('join', 'Create a free account to be notified when this competition opens.');
            return;
        }

        // POST /competitions/{id}/notify
        fetch(`/competitions/${id}/notify`, {method: 'POST', headers: {'X-CSRF-TOKEN': csrfToken()}})
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    notified.add(id);
                    renderGrid();
                    renderSidebar();
                    showToast('🔔', "We'll notify you when this competition opens.");
                }
            })
            .catch(() => {
                // Demo fallback
                if (notified.has(id)) {
                    notified.delete(id);
                    showToast('🔕', 'Notification removed.');
                } else {
                    notified.add(id);
                    showToast('🔔', "We'll notify you when this competition opens.");
                }
                renderGrid();
                renderSidebar();
            });
    }

    // ── EXTERNAL ──────────────────────────────────────────────────
    function handleExternal(e, id, url) {
        if (e) e.stopPropagation();
        if (!window.MEMBER) {
            openModal('join', 'Create a free account, then visit the sponsor page to qualify for entry.');
            return;
        }
        showToast('↗', 'Opening sponsor page — your entry will be tracked automatically.');
        if (url) window.open(url, '_blank');
    }

    // ── FILTER ────────────────────────────────────────────────────
    function setFilter(btn, filter) {
        currentFilter = filter;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderGrid();
    }

    // ── TABS ──────────────────────────────────────────────────────
    function setTab(el, tab) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('howSection').style.display = tab === 'how' ? 'block' : 'none';
        if (tab === 'how') document.getElementById('howSection').scrollIntoView({behavior: 'smooth'});
    }

    // ── TOAST ─────────────────────────────────────────────────────
    let toastTimer;

    function showToast(icon, msg) {
        clearTimeout(toastTimer);
        const t = document.getElementById('toast');
        document.getElementById('toastIcon').textContent = icon;
        document.getElementById('toastMsg').textContent = msg;
        t.classList.add('show');
        toastTimer = setTimeout(() => t.classList.remove('show'), 4000);
    }

    // ── MODAL ─────────────────────────────────────────────────────
    function openModal(type, customDesc) {
        const o = document.getElementById('modalOverlay');
        if (type === 'login') {
            document.getElementById('modalTitle').textContent = 'Welcome back';
            document.getElementById('modalDesc').textContent = customDesc || 'Sign in to your FourFourTwo Club account.';
            document.getElementById('modalSubmit').textContent = 'Sign In →';
            document.getElementById('mFirst').closest('.modal-field').style.display = 'none';
            document.getElementById('modalAlt').innerHTML = 'No account? <a href="#" onclick="openModal(\'join\')">Join free</a>';
        } else {
            document.getElementById('modalTitle').textContent = 'Create account';
            document.getElementById('modalDesc').textContent = customDesc || 'Join free to enter prize draws and unlock exclusive competitions.';
            document.getElementById('modalSubmit').textContent = 'Create Free Account →';
            document.getElementById('mFirst').closest('.modal-field').style.display = '';
            document.getElementById('modalAlt').innerHTML = 'Already a member? <a href="#" onclick="openModal(\'login\')">Sign in</a>';
        }
        o.classList.add('open');
    }

    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('open');
    }

    function closeOutside(e) {
        if (e.target === document.getElementById('modalOverlay')) closeModal();
    }

    function submitModal() {
        // POST /auth/register or /auth/login — in production
        window.MEMBER = {name: 'Jamie', initials: 'J'};
        closeModal();
        bootMember(window.MEMBER);
        showToast('👋', 'Welcome to FourFourTwo Club! You can now enter competitions.');
    }

    // ── BOOT MEMBER STATE ─────────────────────────────────────────
    function bootMember(member) {
        // Replace auth buttons with member chip
        document.getElementById('authArea').innerHTML = `
      <div class="member-chip">
        Hi, ${member.name}
        <div class="member-avatar">${(member.initials || member.name[0]).toUpperCase()}</div>
      </div>`;

        // Show progress banner
        const overallPct = 65; // in production: max of progress.percentage across competitions
        document.getElementById('progressBanner').style.display = 'flex';
        document.getElementById('progressFill').style.width = overallPct + '%';
        document.getElementById('progressPct').textContent = overallPct + '%';

        // Update sidebar progress section
        document.getElementById('sbProgressInner').innerHTML = `
      <div style="color:var(--muted);font-size:13px;margin-bottom:8px;">2 competitions unlocking soon</div>
      <div class="card-progress" style="margin-bottom:0">
        <div class="cp-label">Overall <span>${overallPct}%</span></div>
        <div class="cp-track"><div class="cp-fill" style="width:${overallPct}%"></div></div>
      </div>`;

        renderGrid();
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }
</script>
</body>
</html>