<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <meta name="description" content="@yield('meta_description')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:ital,wght@0,400;0,600;1,400&display=swap"
          rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --ink: #111827;
            --ink-soft: #374151;
            --ink-muted: #6b7280;
            --surface: #f9fafb;
            --white: #ffffff;
            --border: #e5e7eb;
            --accent: #1d4ed8;
            --accent-bg: #eff6ff;
            --warn-bg: #fffbeb;
            --warn-border: #fde68a;
            --radius: 8px;
            --font-body: 'Inter', system-ui, sans-serif;
            --font-serif: 'Lora', Georgia, serif;
        }

        body {
            font-family: var(--font-body);
            font-size: 15px;
            line-height: 1.7;
            color: var(--ink);
            background: var(--surface);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Site header ────────────────────────── */
        .legal-site-header {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .legal-site-header__brand {
            font-size: 15px;
            font-weight: 600;
            color: var(--ink);
            text-decoration: none;
        }

        .legal-site-header__back {
            font-size: 13px;
            color: var(--ink-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .legal-site-header__back:hover {
            color: var(--ink);
        }

        /* ── Page shell ─────────────────────────── */
        .legal-shell {
            max-width: 760px;
            margin: 0 auto;
            padding: 48px 24px 96px;
        }

        /* ── Article ────────────────────────────── */
        .legal-page__section {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .legal-page__title {
            font-family: var(--font-serif);
            font-size: clamp(28px, 4vw, 40px);
            font-weight: 600;
            line-height: 1.15;
            letter-spacing: -.01em;
            color: var(--ink);
            margin-bottom: 10px;
        }

        .legal-page__meta {
            font-size: 13px;
            color: var(--ink-muted);
            margin-bottom: 40px;
            padding-bottom: 32px;
            border-bottom: 1px solid var(--border);
        }

        /* ── Body typography ────────────────────── */
        .legal-page__body section {
            margin-bottom: 40px;
        }

        .legal-page__body h2 {
            font-family: var(--font-serif);
            font-size: 20px;
            font-weight: 600;
            color: var(--ink);
            margin: 0 0 14px;
            padding-top: 8px;
        }

        .legal-page__body h3 {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            margin: 20px 0 8px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .legal-page__body p {
            color: var(--ink-soft);
            margin-bottom: 14px;
        }

        .legal-page__body p:last-child {
            margin-bottom: 0;
        }

        .legal-page__body ul,
        .legal-page__body ol {
            padding-left: 22px;
            margin-bottom: 14px;
            color: var(--ink-soft);
        }

        .legal-page__body li {
            margin-bottom: 6px;
        }

        .legal-page__body a {
            color: var(--accent);
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .legal-page__body strong {
            color: var(--ink);
            font-weight: 600;
        }

        /* ── Table ──────────────────────────────── */
        .legal-table-wrap {
            overflow-x: auto;
            margin-bottom: 14px;
        }

        .legal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        .legal-table th {
            background: var(--surface);
            font-weight: 600;
            font-size: 11.5px;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--ink-muted);
            padding: 10px 14px;
            border: 1px solid var(--border);
            text-align: left;
        }

        .legal-table td {
            padding: 10px 14px;
            border: 1px solid var(--border);
            color: var(--ink-soft);
            vertical-align: top;
        }

        .legal-table tr:nth-child(even) td {
            background: var(--surface);
        }

        /* ── Callout boxes ──────────────────────── */
        .legal-callout {
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .legal-callout--info {
            background: var(--accent-bg);
            border-left: 4px solid var(--accent);
            color: #1e3a5f;
        }

        .legal-callout--warn {
            background: var(--warn-bg);
            border-left: 4px solid #f59e0b;
            color: #78350f;
        }

        .legal-callout p {
            color: inherit;
            margin-bottom: 0;
        }

        /* ── Model cancellation form ────────────── */
        .legal-page__model-form {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 24px;
            font-size: 13.5px;
            color: var(--ink-soft);
            line-height: 1.7;
            margin: 16px 0;
        }

        .legal-page__model-form p {
            color: var(--ink-soft);
            margin-bottom: 12px;
        }

        .legal-page__model-form p:last-child {
            margin-bottom: 0;
        }

        /* ── Placeholder marker ─────────────────── */
        .legal-placeholder {
            background: #fef9c3;
            border: 1px dashed #ca8a04;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: 600;
            color: #854d0e;
            font-family: var(--font-body);
        }

        /* ── Related links footer ───────────────── */
        .legal-related {
            margin-top: 64px;
            padding-top: 32px;
            border-top: 1px solid var(--border);
        }

        .legal-related__title {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink-muted);
            margin-bottom: 14px;
        }

        .legal-related__links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .legal-related__link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 100px;
            font-size: 13px;
            color: var(--ink-soft);
            text-decoration: none;
            background: var(--white);
            transition: border-color .15s, color .15s;
        }

        .legal-related__link:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        @media (max-width: 640px) {
            .legal-shell {
                padding: 32px 16px 64px;
            }
        }
    </style>
</head>
<body>

<header class="legal-site-header">
    <a href="/" class="legal-site-header__brand">{{ config('app.name') }}</a>
    <a href="javascript:history.back()" class="legal-site-header__back">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back
    </a>
</header>

<div class="legal-shell">
    <article class="legal-page">
        <header class="legal-page__header">
            <p class="legal-page__section">Legal</p>
            <h1 class="legal-page__title">@yield('title')</h1>
            <p class="legal-page__meta">Last updated: @yield('last_updated', config('legal.updated_default', 'January
                2025'))</p>
        </header>
        <div class="legal-page__body">
            @yield('content')
        </div>
    </article>

    <nav class="legal-related">
        <div class="legal-related__title">Related policies</div>
        <div class="legal-related__links">
            <a href="{{ url('/legal/privacy-policy') }}" class="legal-related__link">Privacy Policy</a>
            <a href="{{ url('/legal/cookie-policy') }}" class="legal-related__link">Cookie Policy</a>
            <a href="{{ url('/legal/returns-policy') }}" class="legal-related__link">Returns Policy</a>
            <a href="{{ url('/legal/cancellation-rights') }}" class="legal-related__link">Cancellation Rights</a>
            <a href="{{ url('/legal/data-subject-rights') }}" class="legal-related__link">Your Data Rights</a>
            <a href="{{ url('/legal/data-retention') }}" class="legal-related__link">Data Retention</a>
            <a href="{{ url('/legal/reviews-policy') }}" class="legal-related__link">Reviews Policy</a>
        </div>
    </nav>
</div>

</body>
</html>