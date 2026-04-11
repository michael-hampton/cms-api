<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Invitation — OpenCollab</title>
    @css('open-collab.css')
</head>
<body style="background:var(--navy);">

<div class="oc-auth">

    <div class="oc-auth__panel oc-auth__panel--brand">
        <a href="/" class="oc-auth__logo">
            <div class="oc-auth__logo-mark">O</div>
            <span class="oc-auth__logo-name">OpenCollab</span>
        </a>
        <div>
            <h1 class="oc-auth__brand-heading">
                Can't find<br>your invite?
            </h1>
            <p class="oc-auth__tagline">
                No problem — enter your email address and we'll send a fresh invitation
                link if one exists for your account.
            </p>
        </div>
    </div>

    <div class="oc-auth__panel oc-auth__panel--form">

        <p class="oc-auth__form-sub" style="margin-bottom:8px;">Invitation expired or lost</p>
        <h2 class="oc-auth__form-title">Resend my invitation</h2>

        <!-- We always show the same message on submit to avoid email enumeration -->
        <div id="success-state" style="display:none;text-align:center;padding-top:20px;">
            <div style="width:56px;height:56px;background:var(--green-pale,#dcfce7);border-radius:50%;
                  display:grid;place-items:center;margin:0 auto 16px;">
                <svg viewBox="0 0 20 20" fill="var(--green,#16a34a)" width="24">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                </svg>
            </div>
            <div style="font-weight:600;font-size:1rem;color:var(--navy);margin-bottom:8px;">Check your inbox</div>
            <p style="font-size:.875rem;color:var(--slate);line-height:1.6;">
                If an invitation exists for that email address, we've sent a fresh link.
                Check your spam folder if you don't see it within a few minutes.
            </p>
            <a href="/<?= htmlspecialchars($site ?? '') ?>/open-collab/login"
               class="oc-btn oc-btn--ghost" style="margin-top:24px;display:inline-flex;">
                Back to sign in
            </a>
        </div>

        <div id="form-state">
            <div id="form-errors" class="oc-form-errors" style="display:none;" role="alert"></div>

            <form id="resend-form" novalidate>
                <div class="oc-form-group">
                    <label class="oc-label" for="resend-email">Email address</label>
                    <input class="oc-input" type="email" id="resend-email" name="email"
                           placeholder="The email your invitation was sent to"
                           required autocomplete="email" autofocus>
                    <div class="oc-help">
                        Enter the email address you were invited with. If we have a pending or expired invitation
                        for that address, we'll send a fresh link.
                    </div>
                    <div class="oc-error-msg" id="email-error"></div>
                </div>

                <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="resend-btn">
                    Send my invitation link
                </button>

                <p style="font-size:.78rem;color:var(--slate);text-align:center;margin-top:20px;">
                    Don't have an invitation yet?
                    <a href="/<?= htmlspecialchars($site ?? '') ?>/open-collab/request-access"
                       style="color:var(--navy);font-weight:500;">Request access.</a>
                </p>
            </form>
        </div>

    </div>
</div>

<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    document.getElementById('resend-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const errBox = document.getElementById('form-errors');
        const btn = document.getElementById('resend-btn');
        const emailEl = document.getElementById('resend-email');
        const emailErr = document.getElementById('email-error');

        errBox.style.display = 'none';
        emailErr.textContent = '';
        emailErr.classList.remove('visible');

        const email = emailEl.value.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            emailErr.textContent = 'Please enter a valid email address.';
            emailErr.classList.add('visible');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Sending…';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/invitations/resend`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({email}),
            });

            // Always show success to prevent email enumeration
            document.getElementById('form-state').style.display = 'none';
            document.getElementById('success-state').style.display = 'block';
        } catch {
            // Still show success state to avoid leaking enumeration info
            document.getElementById('form-state').style.display = 'none';
            document.getElementById('success-state').style.display = 'block';
        }
    });
</script>

</body>
</html>