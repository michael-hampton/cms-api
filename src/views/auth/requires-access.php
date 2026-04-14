<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Required</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
        }

        /* Top Content Section (The Sneak Peek) */
        .content-header {
            width: 100%;
            max-width: 800px;
            padding: 4rem 2rem 2rem 2rem;
            text-align: center;
            /* Creates a fade-out effect toward the lock card */
            mask-image: linear-gradient(to bottom, black 50%, transparent 100%);
            -webkit-mask-image: linear-gradient(to bottom, black 50%, transparent 100%);
        }

        .content-header h2 {
            font-size: 2.5rem;
            margin: 0 0 1rem 0;
            color: #0f172a;
        }

        .content-header p {
            font-size: 1.25rem;
            color: #64748b;
            margin: 0;
        }

        /* The Lock Card */
        .lock-card {
            text-align: center;
            padding: 2.5rem;
            max-width: 420px;
            width: 90%;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            margin-top: -2rem; /* Pulls it up into the fade */
            position: relative;
            z-index: 10;
            border: 1px solid #e2e8f0;
        }

        .lock-icon {
            background: #fef2f2;
            color: #ef4444;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 1.5rem auto;
            font-size: 1.5rem;
        }

        h1 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #0f172a;
        }

        .message {
            font-size: 1rem;
            color: #475569;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .button-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            display: block;
            padding: 0.8rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
            font-size: 1rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background-color: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: #f8fafc;
            color: #0f172a;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background-color: #f1f5f9;
        }
    </style>
</head>
<body>

<header class="content-header">
    <h2 id="display-title"><?= $page->title ?></h2>
    <p id="display-subtitle"><?= $page->subtitle ?></p>
</header>

<div class="lock-card">
    <div class="lock-icon">🔒</div>
    <h1>Ready to keep reading?</h1>
    <p class="message">To view this page, please <strong>log in</strong> to your account or <strong>subscribe</strong>
        to one of our plans for full access.</p>

    <div class="button-group">
        <a class="btn btn-primary" onclick="showSubscriptionModal()">View Subscription Plans</a>
        <a href="/member/login" class="btn btn-secondary">Log In to Account</a>
    </div>
</div>

<?php if (isset($subscriptionModalData)): ?>
    @include('components/subscription-modal', ['subscriptionModalData' => $subscriptionModalData])
<?php endif; ?>

</body>
</html>