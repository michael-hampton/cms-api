<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenCollab — Guest Contributor Pages</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink:          #0F0F12;
            --ink-60:       #0F0F129A;
            --paper:        #F8F7F3;
            --paper-dark:   #EFEDE7;
            --accent:       #5B4FE8;
            --accent-dim:   #5B4FE820;
            --accent-warm:  #E85B4F;
            --muted:        #9896A4;
            --card-bg:      #FFFFFF;
            --shadow:       0 4px 24px 0 rgba(15,15,18,0.10);
            --shadow-lg:    0 16px 56px 0 rgba(15,15,18,0.18);

            --font-display: 'DM Serif Display', Georgia, serif;
            --font-body:    'Inter', system-ui, sans-serif;
            --font-mono:    'JetBrains Mono', 'Courier New', monospace;
        }

        html { font-size: 16px; }
        body {
            background: var(--paper);
            color: var(--ink);
            font-family: var(--font-body);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── Page shell ─────────────────────────────────────────────── */
        .oc-section {
            padding: 80px 0 96px;
            position: relative;
        }

        .oc-section__eyebrow {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 48px;
            margin-bottom: 28px;
        }

        .oc-section__eyebrow-line {
            width: 32px;
            height: 1px;
            background: var(--accent);
        }

        .oc-section__eyebrow-label {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--accent);
        }

        .oc-section__heading {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 44px);
            font-weight: 400;
            line-height: 1.15;
            letter-spacing: -0.01em;
            color: var(--ink);
            padding: 0 48px;
            margin-bottom: 8px;
            max-width: 580px;
        }

        .oc-section__heading em {
            font-style: italic;
            color: var(--accent);
        }

        .oc-section__sub {
            font-size: 15px;
            color: var(--muted);
            padding: 0 48px;
            margin-bottom: 52px;
            max-width: 420px;
            line-height: 1.6;
        }

        /* ── Carousel track ─────────────────────────────────────────── */
        .oc-carousel {
            position: relative;
            padding: 0 48px;
            user-select: none;
        }

        .oc-carousel__track-outer {
            overflow: hidden;
            cursor: grab;
        }
        .oc-carousel__track-outer:active { cursor: grabbing; }

        .oc-carousel__track {
            display: flex;
            gap: 20px;
            transition: transform 0.55s cubic-bezier(0.65, 0, 0.25, 1);
            will-change: transform;
        }

        /* ── Card ───────────────────────────────────────────────────── */
        .oc-card {
            flex: 0 0 auto;
            width: 320px;
            background: var(--card-bg);
            border-radius: 4px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            transition: box-shadow 0.35s ease, transform 0.45s cubic-bezier(0.65, 0, 0.25, 1), opacity 0.45s ease;
            opacity: 0.46;
            transform: scale(0.96) translateY(6px);
        }

        .oc-card.is-active {
            width: 420px;
            box-shadow: var(--shadow-lg);
            opacity: 1;
            transform: scale(1) translateY(0);
        }

        .oc-card.is-adjacent {
            opacity: 0.72;
            transform: scale(0.975) translateY(3px);
        }

        /* Folded corner — the signature element */
        .oc-card__corner {
            position: absolute;
            top: 0;
            right: 0;
            width: 54px;
            height: 54px;
            z-index: 3;
            pointer-events: none;
        }

        .oc-card__corner::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 54px 54px 0;
            border-color: transparent var(--paper) transparent transparent;
            z-index: 2;
        }

        .oc-card__corner::after {
            content: attr(data-initial);
            position: absolute;
            top: 4px;
            right: 6px;
            font-family: var(--font-display);
            font-size: 15px;
            font-style: italic;
            color: var(--accent);
            z-index: 4;
            line-height: 1;
        }

        /* Colour band — unique per card via class */
        .oc-card__band {
            height: 5px;
            width: 100%;
        }

        .band--violet  { background: linear-gradient(90deg, #5B4FE8, #8B7FFF); }
        .band--coral   { background: linear-gradient(90deg, #E85B4F, #FF8B7F); }
        .band--amber   { background: linear-gradient(90deg, #D4860A, #F0B040); }
        .band--teal    { background: linear-gradient(90deg, #0D9E8A, #3DCFB8); }
        .band--rose    { background: linear-gradient(90deg, #C2406E, #E87DA0); }
        .band--slate   { background: linear-gradient(90deg, #3C5A80, #6A90BE); }

        /* Card image / visual block */
        .oc-card__visual {
            height: 188px;
            background: var(--paper-dark);
            position: relative;
            overflow: hidden;
        }

        .oc-card.is-active .oc-card__visual { height: 228px; }

        .oc-card__visual-bg {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Placeholder pattern for cards without real images */
        .oc-card__visual-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: clamp(36px, 8vw, 60px);
            font-style: italic;
            opacity: 0.13;
            letter-spacing: -0.02em;
        }

        .oc-card__tag {
            position: absolute;
            bottom: 12px;
            left: 14px;
            background: var(--ink);
            color: var(--paper);
            font-family: var(--font-mono);
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 2px;
        }

        /* Card body */
        .oc-card__body {
            padding: 20px 22px 24px;
        }

        .oc-card__contributor {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 14px;
        }

        .oc-card__avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--accent-dim);
            border: 1.5px solid var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 12px;
            font-style: italic;
            color: var(--accent);
            flex-shrink: 0;
        }

        .oc-card__handle {
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: var(--muted);
            letter-spacing: 0.04em;
        }

        .oc-card__title {
            font-family: var(--font-display);
            font-size: 19px;
            font-weight: 400;
            line-height: 1.25;
            letter-spacing: -0.01em;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .oc-card.is-active .oc-card__title {
            font-size: 22px;
        }

        .oc-card__excerpt {
            font-size: 13.5px;
            color: var(--ink-60);
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 18px;
        }

        .oc-card__meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .oc-card__stat {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .oc-card__stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        .oc-card__stat-icon {
            width: 14px;
            height: 14px;
            opacity: 0.6;
        }

        .oc-card__cta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.08em;
            color: var(--accent);
            text-decoration: none;
            text-transform: uppercase;
            border-bottom: 1px solid var(--accent-dim);
            padding-bottom: 1px;
            transition: border-color 0.2s, gap 0.2s;
            cursor: pointer;
            background: none;
            border-top: none;
            border-left: none;
            border-right: none;
        }
        .oc-card__cta:hover { border-color: var(--accent); gap: 10px; }

        .oc-card__cta-arrow {
            display: inline-block;
            transition: transform 0.2s;
        }
        .oc-card__cta:hover .oc-card__cta-arrow { transform: translateX(3px); }

        /* ── Controls ───────────────────────────────────────────────── */
        .oc-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 32px 48px 0;
        }

        .oc-controls__dots {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .oc-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--muted);
            opacity: 0.35;
            transition: opacity 0.25s, transform 0.25s, background 0.25s;
            cursor: pointer;
            border: none;
            padding: 0;
        }

        .oc-dot.is-active {
            background: var(--accent);
            opacity: 1;
            transform: scale(1.5);
        }

        .oc-controls__arrows {
            display: flex;
            gap: 10px;
        }

        .oc-arrow {
            width: 42px;
            height: 42px;
            border: 1px solid var(--paper-dark);
            border-radius: 50%;
            background: var(--card-bg);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, border-color 0.2s, transform 0.15s;
            box-shadow: 0 2px 8px rgba(15,15,18,0.07);
            color: var(--ink);
        }
        .oc-arrow:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
            transform: scale(1.06);
        }
        .oc-arrow:active { transform: scale(0.96); }
        .oc-arrow:disabled {
            opacity: 0.3;
            cursor: not-allowed;
            transform: none;
        }
        .oc-arrow svg { display: block; }

        /* ── Progress bar ───────────────────────────────────────────── */
        .oc-progress {
            height: 2px;
            background: var(--paper-dark);
            margin: 28px 48px 0;
            border-radius: 2px;
            overflow: hidden;
        }

        .oc-progress__fill {
            height: 100%;
            background: var(--accent);
            border-radius: 2px;
            transition: width 0.55s cubic-bezier(0.65, 0, 0.25, 1);
        }

        /* ── Count label ────────────────────────────────────────────── */
        .oc-count {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--muted);
            letter-spacing: 0.1em;
            padding: 10px 48px 0;
        }

        /* ── Responsive ─────────────────────────────────────────────── */
        @media (max-width: 680px) {
            .oc-section { padding: 56px 0 72px; }
            .oc-section__eyebrow,
            .oc-section__heading,
            .oc-section__sub,
            .oc-carousel,
            .oc-controls,
            .oc-progress,
            .oc-count { padding-left: 24px; padding-right: 24px; }

            .oc-card { width: 260px; }
            .oc-card.is-active { width: 300px; }
            .oc-card__visual { height: 160px; }
            .oc-card.is-active .oc-card__visual { height: 185px; }
            .oc-card__title { font-size: 17px; }
            .oc-card.is-active .oc-card__title { font-size: 19px; }
        }

        /* ── Reduced motion ─────────────────────────────────────────── */
        @media (prefers-reduced-motion: reduce) {
            .oc-carousel__track,
            .oc-card,
            .oc-progress__fill { transition: none; }
        }

        /* ── Demo page wrapper ──────────────────────────────────────── */
        .demo-page {
            max-width: 100%;
            background: var(--paper);
            padding-top: 48px;
        }

        .demo-page__logo {
            padding: 0 48px;
            margin-bottom: 64px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .demo-page__logo-mark {
            width: 32px;
            height: 32px;
            background: var(--accent);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .demo-page__logo-mark svg { display: block; }

        .demo-page__logo-name {
            font-family: var(--font-display);
            font-size: 18px;
            letter-spacing: -0.02em;
            color: var(--ink);
        }
        .demo-page__logo-name span {
            color: var(--accent);
            font-style: italic;
        }

        @media (max-width: 680px) {
            .demo-page__logo { padding: 0 24px; }
        }
    </style>
</head>
<body>

<div class="demo-page">

    <!-- Logo -->
    <div class="demo-page__logo">
        <div class="demo-page__logo-mark">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path d="M3 9C3 5.686 5.686 3 9 3s6 2.686 6 6-2.686 6-6 6" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M9 6v6M6 9h6" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="demo-page__logo-name">Open<span>Collab</span></div>
    </div>

    <!-- Carousel section -->
    <section class="oc-section" aria-label="Guest contributor pages">

        <div class="oc-section__eyebrow">
            <div class="oc-section__eyebrow-line"></div>
            <span class="oc-section__eyebrow-label">Guest Contributors</span>
        </div>

        <h2 class="oc-section__heading">Pages by the <em>community,</em> for the world</h2>
        <p class="oc-section__sub">Explore pages crafted by independent contributors — each one a distinct perspective, live on the platform.</p>

        <div class="oc-carousel" role="region" aria-label="Contributor page carousel">
            <div class="oc-carousel__track-outer" id="trackOuter">
                <div class="oc-carousel__track" id="track" role="list">

                    <!-- Card 1 -->
                    <article class="oc-card is-active" role="listitem" data-index="0">
                        <div class="oc-card__corner" data-initial="M"></div>
                        <div class="oc-card__band band--violet"></div>
                        <div class="oc-card__visual">
                            <div class="oc-card__visual-placeholder">M</div>
                            <span class="oc-card__tag">Design</span>
                        </div>
                        <div class="oc-card__body">
                            <div class="oc-card__contributor">
                                <div class="oc-card__avatar">M</div>
                                <span class="oc-card__handle">@maya.writes</span>
                            </div>
                            <h3 class="oc-card__title">The Grammar of Visual Silence</h3>
                            <p class="oc-card__excerpt">How negative space stopped being emptiness and became the most articulate part of the page.</p>
                            <div class="oc-card__meta">
                                <div class="oc-card__stat">
                  <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M1 7C1 7 3 3 7 3s6 4 6 4-2 4-6 4-6-4-6-4z"/><circle cx="7" cy="7" r="1.5"/>
                    </svg>
                    2.4k
                  </span>
                                    <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M7 1l1.5 3 3.5.5-2.5 2.5.6 3.5L7 9l-3.1 1.5.6-3.5L2 4.5 5.5 4z"/>
                    </svg>
                    186
                  </span>
                                </div>
                                <button class="oc-card__cta" onclick="void(0)">
                                    Visit <span class="oc-card__cta-arrow">→</span>
                                </button>
                            </div>
                        </div>
                    </article>

                    <!-- Card 2 -->
                    <article class="oc-card is-adjacent" role="listitem" data-index="1">
                        <div class="oc-card__corner" data-initial="T"></div>
                        <div class="oc-card__band band--teal"></div>
                        <div class="oc-card__visual">
                            <div class="oc-card__visual-placeholder">T</div>
                            <span class="oc-card__tag">Engineering</span>
                        </div>
                        <div class="oc-card__body">
                            <div class="oc-card__contributor">
                                <div class="oc-card__avatar">T</div>
                                <span class="oc-card__handle">@t.nakamura</span>
                            </div>
                            <h3 class="oc-card__title">State Machines You'll Actually Finish</h3>
                            <p class="oc-card__excerpt">A practical primer on modeling complex transitions without losing track of what's possible.</p>
                            <div class="oc-card__meta">
                                <div class="oc-card__stat">
                  <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M1 7C1 7 3 3 7 3s6 4 6 4-2 4-6 4-6-4-6-4z"/><circle cx="7" cy="7" r="1.5"/>
                    </svg>
                    1.8k
                  </span>
                                    <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M7 1l1.5 3 3.5.5-2.5 2.5.6 3.5L7 9l-3.1 1.5.6-3.5L2 4.5 5.5 4z"/>
                    </svg>
                    94
                  </span>
                                </div>
                                <button class="oc-card__cta">Visit <span class="oc-card__cta-arrow">→</span></button>
                            </div>
                        </div>
                    </article>

                    <!-- Card 3 -->
                    <article class="oc-card" role="listitem" data-index="2">
                        <div class="oc-card__corner" data-initial="P"></div>
                        <div class="oc-card__band band--amber"></div>
                        <div class="oc-card__visual">
                            <div class="oc-card__visual-placeholder">P</div>
                            <span class="oc-card__tag">Writing</span>
                        </div>
                        <div class="oc-card__body">
                            <div class="oc-card__contributor">
                                <div class="oc-card__avatar">P</div>
                                <span class="oc-card__handle">@priya.rao</span>
                            </div>
                            <h3 class="oc-card__title">On Not Explaining Your Metaphors</h3>
                            <p class="oc-card__excerpt">The strange discipline of trusting readers with images that are still half-formed in your own mind.</p>
                            <div class="oc-card__meta">
                                <div class="oc-card__stat">
                  <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M1 7C1 7 3 3 7 3s6 4 6 4-2 4-6 4-6-4-6-4z"/><circle cx="7" cy="7" r="1.5"/>
                    </svg>
                    3.1k
                  </span>
                                    <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M7 1l1.5 3 3.5.5-2.5 2.5.6 3.5L7 9l-3.1 1.5.6-3.5L2 4.5 5.5 4z"/>
                    </svg>
                    241
                  </span>
                                </div>
                                <button class="oc-card__cta">Visit <span class="oc-card__cta-arrow">→</span></button>
                            </div>
                        </div>
                    </article>

                    <!-- Card 4 -->
                    <article class="oc-card" role="listitem" data-index="3">
                        <div class="oc-card__corner" data-initial="S"></div>
                        <div class="oc-card__band band--rose"></div>
                        <div class="oc-card__visual">
                            <div class="oc-card__visual-placeholder">S</div>
                            <span class="oc-card__tag">Photography</span>
                        </div>
                        <div class="oc-card__body">
                            <div class="oc-card__contributor">
                                <div class="oc-card__avatar">S</div>
                                <span class="oc-card__handle">@sol.bernard</span>
                            </div>
                            <h3 class="oc-card__title">Street Portraits Without Permission</h3>
                            <p class="oc-card__excerpt">Ethics, intimacy and the split second that decides whether a photograph has a right to exist.</p>
                            <div class="oc-card__meta">
                                <div class="oc-card__stat">
                  <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M1 7C1 7 3 3 7 3s6 4 6 4-2 4-6 4-6-4-6-4z"/><circle cx="7" cy="7" r="1.5"/>
                    </svg>
                    4.2k
                  </span>
                                    <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M7 1l1.5 3 3.5.5-2.5 2.5.6 3.5L7 9l-3.1 1.5.6-3.5L2 4.5 5.5 4z"/>
                    </svg>
                    312
                  </span>
                                </div>
                                <button class="oc-card__cta">Visit <span class="oc-card__cta-arrow">→</span></button>
                            </div>
                        </div>
                    </article>

                    <!-- Card 5 -->
                    <article class="oc-card" role="listitem" data-index="4">
                        <div class="oc-card__corner" data-initial="L"></div>
                        <div class="oc-card__band band--slate"></div>
                        <div class="oc-card__visual">
                            <div class="oc-card__visual-placeholder">L</div>
                            <span class="oc-card__tag">Research</span>
                        </div>
                        <div class="oc-card__body">
                            <div class="oc-card__contributor">
                                <div class="oc-card__avatar">L</div>
                                <span class="oc-card__handle">@l.ochieng</span>
                            </div>
                            <h3 class="oc-card__title">Why Maps Lie (And Why We Need Them To)</h3>
                            <p class="oc-card__excerpt">Every projection distorts reality in a different direction. That's not a flaw — it's the whole point.</p>
                            <div class="oc-card__meta">
                                <div class="oc-card__stat">
                  <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M1 7C1 7 3 3 7 3s6 4 6 4-2 4-6 4-6-4-6-4z"/><circle cx="7" cy="7" r="1.5"/>
                    </svg>
                    5.6k
                  </span>
                                    <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M7 1l1.5 3 3.5.5-2.5 2.5.6 3.5L7 9l-3.1 1.5.6-3.5L2 4.5 5.5 4z"/>
                    </svg>
                    489
                  </span>
                                </div>
                                <button class="oc-card__cta">Visit <span class="oc-card__cta-arrow">→</span></button>
                            </div>
                        </div>
                    </article>

                    <!-- Card 6 -->
                    <article class="oc-card" role="listitem" data-index="5">
                        <div class="oc-card__corner" data-initial="A"></div>
                        <div class="oc-card__band band--coral"></div>
                        <div class="oc-card__visual">
                            <div class="oc-card__visual-placeholder">A</div>
                            <span class="oc-card__tag">Music</span>
                        </div>
                        <div class="oc-card__body">
                            <div class="oc-card__contributor">
                                <div class="oc-card__avatar">A</div>
                                <span class="oc-card__handle">@a.petrov</span>
                            </div>
                            <h3 class="oc-card__title">The Silence Between Notes</h3>
                            <p class="oc-card__excerpt">What jazz composers understood about rests that most digital producers still haven't learned.</p>
                            <div class="oc-card__meta">
                                <div class="oc-card__stat">
                  <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M1 7C1 7 3 3 7 3s6 4 6 4-2 4-6 4-6-4-6-4z"/><circle cx="7" cy="7" r="1.5"/>
                    </svg>
                    2.9k
                  </span>
                                    <span class="oc-card__stat-item">
                    <svg class="oc-card__stat-icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4">
                      <path d="M7 1l1.5 3 3.5.5-2.5 2.5.6 3.5L7 9l-3.1 1.5.6-3.5L2 4.5 5.5 4z"/>
                    </svg>
                    203
                  </span>
                                </div>
                                <button class="oc-card__cta">Visit <span class="oc-card__cta-arrow">→</span></button>
                            </div>
                        </div>
                    </article>

                </div><!-- /track -->
            </div><!-- /track-outer -->
        </div><!-- /carousel -->

        <!-- Controls -->
        <div class="oc-controls">
            <div class="oc-controls__dots" role="tablist" aria-label="Carousel position" id="dots"></div>
            <div class="oc-controls__arrows">
                <button class="oc-arrow" id="prevBtn" aria-label="Previous">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 3L5 8l5 5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <button class="oc-arrow" id="nextBtn" aria-label="Next">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M6 3l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="oc-progress"><div class="oc-progress__fill" id="progressFill"></div></div>
        <div class="oc-count" id="countLabel" aria-live="polite"></div>

    </section>
</div>

<script>
    (function () {
        const track      = document.getElementById('track');
        const outer      = document.getElementById('trackOuter');
        const prevBtn    = document.getElementById('prevBtn');
        const nextBtn    = document.getElementById('nextBtn');
        const dotsEl     = document.getElementById('dots');
        const progress   = document.getElementById('progressFill');
        const countLabel = document.getElementById('countLabel');

        const cards      = Array.from(track.querySelectorAll('.oc-card'));
        const total      = cards.length;
        let   current    = 0;
        let   autoTimer  = null;

        // ── Build dots ────────────────────────────────────────────────
        const dots = cards.map((_, i) => {
            const d = document.createElement('button');
            d.className = 'oc-dot';
            d.setAttribute('role', 'tab');
            d.setAttribute('aria-label', `Go to slide ${i + 1}`);
            d.addEventListener('click', () => goTo(i));
            dotsEl.appendChild(d);
            return d;
        });

        // ── Compute offset so active card is left-aligned with padding ─
        function getOffset(index) {
            const padding = parseInt(getComputedStyle(document.querySelector('.oc-carousel')).paddingLeft) || 48;
            let offset = 0;
            for (let i = 0; i < index; i++) {
                const gap = 20;
                // Use the card's current rendered width
                const card = cards[i];
                offset += card.offsetWidth + gap;
            }
            return offset;
        }

        function goTo(index) {
            current = Math.max(0, Math.min(index, total - 1));
            renderState();
            resetAuto();
        }

        function renderState() {
            // Card classes
            cards.forEach((c, i) => {
                c.classList.remove('is-active', 'is-adjacent');
                if (i === current) c.classList.add('is-active');
                else if (i === current - 1 || i === current + 1) c.classList.add('is-adjacent');
            });

            // Recalculate after class changes (size shifts)
            requestAnimationFrame(() => {
                const offset = getOffset(current);
                track.style.transform = `translateX(-${offset}px)`;
            });

            // Dots
            dots.forEach((d, i) => {
                d.classList.toggle('is-active', i === current);
                d.setAttribute('aria-selected', i === current);
            });

            // Buttons
            prevBtn.disabled = current === 0;
            nextBtn.disabled = current === total - 1;

            // Progress
            progress.style.width = `${((current + 1) / total) * 100}%`;

            // Count
            countLabel.textContent = `${String(current + 1).padStart(2, '0')} / ${String(total).padStart(2, '0')}`;
        }

        prevBtn.addEventListener('click', () => goTo(current - 1));
        nextBtn.addEventListener('click', () => goTo(current + 1));

        // ── Keyboard ──────────────────────────────────────────────────
        document.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft')  goTo(current - 1);
            if (e.key === 'ArrowRight') goTo(current + 1);
        });

        // ── Drag / swipe ─────────────────────────────────────────────
        let dragStart = null;
        let dragging  = false;

        outer.addEventListener('pointerdown', e => {
            dragStart = e.clientX;
            dragging  = false;
            outer.setPointerCapture(e.pointerId);
        });

        outer.addEventListener('pointermove', e => {
            if (dragStart === null) return;
            if (Math.abs(e.clientX - dragStart) > 5) dragging = true;
        });

        outer.addEventListener('pointerup', e => {
            if (dragStart === null) return;
            const delta = dragStart - e.clientX;
            if (Math.abs(delta) > 50) {
                if (delta > 0) goTo(current + 1);
                else           goTo(current - 1);
            }
            dragStart = null;
            dragging  = false;
        });

        // Prevent click-through on drag
        outer.addEventListener('click', e => { if (dragging) e.stopPropagation(); }, true);

        // ── Auto-advance ─────────────────────────────────────────────
        function resetAuto() {
            clearInterval(autoTimer);
            autoTimer = setInterval(() => {
                const next = current < total - 1 ? current + 1 : 0;
                goTo(next);
            }, 5200);
        }

        // Pause on hover
        outer.addEventListener('mouseenter', () => clearInterval(autoTimer));
        outer.addEventListener('mouseleave', () => resetAuto());

        // ── Resize: re-render offsets ─────────────────────────────────
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => renderState(), 120);
        });

        // ── Init ──────────────────────────────────────────────────────
        renderState();
        resetAuto();
    })();
</script>
</body>
</html>