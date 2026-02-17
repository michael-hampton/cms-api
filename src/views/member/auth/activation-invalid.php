<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link expired – <?= htmlspecialchars($site->name ?? '') ?></title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #212529;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
            text-align: center;
        }

        .icon {
            color: #fd7e14;
            margin-bottom: 1.25rem;
        }

        .icon svg {
            width: 48px;
            height: 48px;
        }

        h1 {
            font-size: 1.375rem;
            margin-bottom: .625rem;
        }

        p {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-block;
            padding: .7rem 1.5rem;
            background: #0d6efd;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: .9375rem;
        }

        .btn:hover {
            background: #0b5ed7;
        }
    </style>
</head>
<body>
<main class="card">
    <div class="icon" role="presentation">
        <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
    </div>
    <h1>This link has expired</h1>
    <p>
        Your password setup link is no longer valid. This usually happens if
        the link was already used, or if it has been more than 48&nbsp;hours
        since it was sent.
    </p>
    <a href="/member/forgot-password" class="btn">Request a new link</a>
</main>
</body>
</html>