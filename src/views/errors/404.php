<?php
$siteSlug = \App\Framework\Support\SiteContext::slug();
$homeUrl = '/' . rawurlencode($siteSlug);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page not found</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            color: #0f172a;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .not-found {
            width: min(680px, 100%);
            padding: clamp(2rem, 6vw, 4rem);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: rgba(255,255,255,.94);
            box-shadow: 0 24px 70px rgba(15,23,42,.12);
            text-align: center;
        }
        .not-found__code {
            display: inline-block;
            margin-bottom: 1rem;
            color: #4f46e5;
            font-size: .8rem;
            font-weight: 800;
            letter-spacing: .18em;
            text-transform: uppercase;
        }
        h1 {
            margin: 0;
            font-size: clamp(2.25rem, 7vw, 4.5rem);
            line-height: 1;
            letter-spacing: -.04em;
        }
        p {
            max-width: 34rem;
            margin: 1.25rem auto 0;
            color: #64748b;
            font-size: 1.05rem;
            line-height: 1.7;
        }
        a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 2rem;
            padding: .85rem 1.25rem;
            border-radius: 999px;
            background: #111827;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
        }
        a:hover { background: #1f2937; }
    </style>
</head>
<body>
    <main class="not-found">
        <span class="not-found__code">404 error</span>
        <h1>Page not found</h1>
        <p>The page may have moved, been removed, or the address may be incorrect.</p>
        <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Return to homepage</a>
    </main>
</body>
</html>
