<?php
// archive-restricted.php
$page_title = "Access to This Archive is Restricted";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Source+Sans+3:wght@400;500;600&display=swap"
          rel="stylesheet"/>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --blue: #1a5dc8;
            --blue-hover: #144db0;
            --blue-active: #0e3d96;
            --text-dark: #1a1a1a;
            --text-muted: #555;
            --border: #e2e2e2;
            --bg: #f5f5f5;
            --card-bg: #ffffff;
            --shadow: 0 2px 12px rgba(0, 0, 0, 0.07);
            --radius: 8px;
        }

        body {
            font-family: 'Source Sans 3', sans-serif;
            background: var(--bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 60px 20px 80px;
        }

        /* Lock Icon */
        .lock-wrap {
            width: 72px;
            height: 72px;
            background: #ffe4e4;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            animation: fadeDown 0.5s ease both;
        }

        .lock-wrap svg {
            width: 32px;
            height: 32px;
            color: #c0392b;
        }

        /* Hero text */
        .hero {
            text-align: center;
            max-width: 640px;
            animation: fadeDown 0.55s ease 0.05s both;
        }

        .hero h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(1.6rem, 4vw, 2.2rem);
            color: var(--text-dark);
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .hero p {
            font-size: 1rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Section title */
        .section-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.55rem;
            margin: 48px 0 28px;
            text-align: center;
            animation: fadeDown 0.55s ease 0.1s both;
        }

        /* Cards */
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            width: 100%;
            max-width: 1100px;
            animation: fadeUp 0.6s ease 0.15s both;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 32px 28px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: var(--shadow);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.11);
            transform: translateY(-3px);
        }

        .card h2 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.15rem;
            color: var(--text-dark);
        }

        .card p {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.55;
            flex: 1;
        }

        /* Blue Buttons */
        .btn {
            display: inline-block;
            width: 100%;
            padding: 14px 20px;
            background: var(--blue);
            color: #fff;
            font-family: 'Source Sans 3', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-align: center;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.18s ease, transform 0.12s ease, box-shadow 0.18s ease;
            box-shadow: 0 2px 8px rgba(26, 93, 200, 0.25);
            margin-top: 6px;
        }

        .btn:hover {
            background: var(--blue-hover);
            box-shadow: 0 4px 16px rgba(26, 93, 200, 0.35);
            transform: translateY(-1px);
        }

        .btn:active {
            background: var(--blue-active);
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(26, 93, 200, 0.2);
        }

        /* Trouble section */
        .trouble {
            margin-top: 56px;
            text-align: center;
            animation: fadeUp 0.6s ease 0.2s both;
            width: 100%;
            max-width: 740px;
        }

        .trouble h3 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }

        .trouble p {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .contact-cards {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 16px;
        }

        .contact-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px 30px;
            min-width: 220px;
            box-shadow: var(--shadow);
            transition: box-shadow 0.2s ease;
        }

        .contact-card:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.10);
        }

        .contact-card strong {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-dark);
        }

        .contact-card a {
            color: var(--blue);
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 500;
        }

        .contact-card a:hover {
            text-decoration: underline;
        }

        .contact-card .phone {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .contact-card .hours {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Animations */
        @keyframes fadeDown {
            from {
                opacity: 0;
                transform: translateY(-18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Divider */
        .divider {
            width: 48px;
            height: 3px;
            background: var(--blue);
            border-radius: 2px;
            margin: 20px auto 0;
        }

        @media (max-width: 600px) {
            .cards {
                grid-template-columns: 1fr;
            }

            body {
                padding: 40px 16px 60px;
            }
        }
    </style>
</head>
<body>

<!-- Lock icon -->
<div class="lock-wrap">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    </svg>
</div>

<!-- Hero -->
<div class="hero">
    <h1><?= htmlspecialchars($page_title) ?></h1>
    <p>This content is available for Kiplinger Letter Digital and Print + Digital Subscribers Only.</p>
    <div class="divider"></div>
</div>

<!-- What you need to do -->
<h2 class="section-title">What you need to do</h2>

<div class="cards">

    <div class="card">
        <h2>Already subscribed?</h2>
        <p>If you have a subscription but no account, create one now to access the archive. Please use your subscription
            email.</p>
        <a href="#create-account" class="btn" id="btn-create"
           onclick="handleAction('create'); return false;">Create Account</a>
    </div>

    <div class="card">
        <h2>Already have an account?</h2>
        <p>If you've already created your free website account, simply log in to continue.</p>
        <a href="#sign-in" class="btn" id="btn-signin"
           onclick="handleAction('signin'); return false;">Sign In</a>
    </div>

    <div class="card">
        <h2>Need to purchase a subscription?</h2>
        <p>To view the archive and get other exclusive benefits, you'll need to purchase a subscription.</p>
        <a href="#purchase" class="btn" id="btn-purchase"
           onclick="handleAction('purchase'); return false;">Purchase Subscription</a>
    </div>

</div>

<!-- Still having trouble -->
<div class="trouble">
    <h3>Still having trouble?</h3>
    <p>If you're unsure which email to use or have any other questions, our support team is ready to help.</p>

    <div class="contact-cards">
        <div class="contact-card">
            <strong>Email Customer Service</strong>
            <a href="mailto:kipcustserv@cdsfulfillment.com">kipcustserv@cdsfulfillment.com</a>
        </div>
        <div class="contact-card">
            <strong>Call Customer Service</strong>
            <span class="phone">1-800-544-0155</span>
            <div class="hours">Mon–Fri, 6am–8:30pm &amp; Sat, 7am–5pm ET</div>
        </div>
    </div>
</div>

<script>
    function handleAction(action) {
        const labels = {
            create: 'Redirecting to account creation…',
            signin: 'Redirecting to sign in…',
            purchase: 'Redirecting to subscription purchase…'
        };

        const btnIds = {
            create: 'btn-create',
            signin: 'btn-signin',
            purchase: 'btn-purchase'
        };

        const btn = document.getElementById(btnIds[action]);
        if (!btn) return;

        btn.textContent = labels[action];
        btn.style.opacity = '0.75';
        btn.style.pointerEvents = 'none';

        // Simulate redirect delay — replace with real URLs
        setTimeout(() => {
            // window.location.href = '/your-real-url';
            btn.textContent = btn.id === 'btn-create' ? 'Create Account' :
                btn.id === 'btn-signin' ? 'Sign In' : 'Purchase Subscription';
            btn.style.opacity = '1';
            btn.style.pointerEvents = 'auto';
        }, 2000);
    }
</script>

</body>
</html>