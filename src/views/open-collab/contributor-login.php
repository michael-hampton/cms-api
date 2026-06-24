<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — OpenCollab</title>
    @css('open-collab.css')
</head>
<body style="background:var(--navy);">

<div class="oc-auth">

    <!-- Brand Panel -->
    <div class="oc-auth__panel oc-auth__panel--brand">
        <a href="/" class="oc-auth__logo">
            <div class="oc-auth__logo-mark">O</div>
            <span class="oc-auth__logo-name">OpenCollab</span>
        </a>

        <div>
            <h1 class="oc-auth__brand-heading">
                Write.<br>
                Publish.<br>
                <em>Earn.</em>
            </h1>
            <p class="oc-auth__tagline">
                Your contributor dashboard — track earnings, manage articles,
                and connect with your audience, all in one place.
            </p>
        </div>

        <div style="display:flex;gap:24px;">
            <div>
                <div style="font-family:var(--font-display);font-size:1.75rem;font-weight:700;color:var(--amber);">
                    2,400+
                </div>
                <div style="font-size:.78rem;color:rgba(255,255,255,.45);margin-top:2px;">Active contributors</div>
            </div>
            <div>
                <div style="font-family:var(--font-display);font-size:1.75rem;font-weight:700;color:var(--amber);">
                    £180k
                </div>
                <div style="font-size:.78rem;color:rgba(255,255,255,.45);margin-top:2px;">Paid out this year</div>
            </div>
        </div>
    </div>

    <!-- Form Panel -->
    <div class="oc-auth__panel oc-auth__panel--form">

        <p class="oc-auth__form-sub" style="margin-bottom:8px;">Welcome back</p>
        <h2 class="oc-auth__form-title">Sign in to your account</h2>

        <div id="form-errors" class="oc-form-errors" style="display:none;" role="alert"></div>

        <form id="login-form" novalidate>

            <div class="oc-form-group">
                <label class="oc-label" for="email">Email address</label>
                <input
                        class="oc-input"
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        required
                        autocomplete="email"
                        autofocus
                >
                <div class="oc-error-msg" id="email-error"></div>
            </div>

            <div class="oc-form-group">
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;">
                    <label class="oc-label" for="password" style="margin-bottom:0;">Password</label>
                </div>
                <div style="position:relative;">
                    <input
                            class="oc-input"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Your password"
                            required
                            autocomplete="current-password"
                            style="padding-right:44px;"
                    >
                    <button
                            type="button"
                            onclick="togglePassword()"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--slate);"
                            aria-label="Toggle password visibility"
                    >
                        <svg id="eye-icon" viewBox="0 0 20 20" fill="currentColor" width="18">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                            <path fill-rule="evenodd"
                                  d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                <div class="oc-error-msg" id="password-error"></div>
            </div>

            <button
                    type="submit"
                    class="oc-btn oc-btn--amber oc-btn--block"
                    id="submit-btn"
                    style="margin-top:8px;"
            >
                Sign in
            </button>

            <p style="font-size:.8rem;color:var(--slate);text-align:center;margin-top:20px;">
                Don't have an account? You need an invitation to join.<br>
                Contact your site administrator.
            </p>

        </form>

    </div>
</div>

<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    function togglePassword() {
        const pw = document.getElementById('password');
        const icon = document.getElementById('eye-icon');
        const isText = pw.type === 'text';
        pw.type = isText ? 'password' : 'text';
        icon.innerHTML = isText
            ? '<path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>'
            : '<path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074L3.707 2.293zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.515a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>';
    }

    document.getElementById('login-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        const errBox = document.getElementById('form-errors');
        const btn = document.getElementById('submit-btn');
        const emailEl = document.getElementById('email');
        const passwordEl = document.getElementById('password');

        // Clear previous errors
        errBox.style.display = 'none';
        document.getElementById('email-error').textContent = '';
        document.getElementById('password-error').textContent = '';

        const email = emailEl.value.trim();
        const password = passwordEl.value;

        // Basic client-side validation
        if (!email) {
            document.getElementById('email-error').textContent = 'Email is required.';
            document.getElementById('email-error').classList.add('visible');
            emailEl.focus();
            return;
        }

        if (!password) {
            document.getElementById('password-error').textContent = 'Password is required.';
            document.getElementById('password-error').classList.add('visible');
            passwordEl.focus();
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Signing in…';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({email, password}),
            });

            const data = await res.json();
            const payload = data.data || data;
            const token = payload.access_token || payload.token;

            if (res.ok && token) {
                // Store token for all subsequent API requests
                localStorage.setItem('oc_token', token);
                window.location.href = `/${SITE}/open-collab/contributor/dashboard`;
            } else {
                const message = data.message || data.error || 'Invalid email or password.';
                errBox.textContent = message;
                errBox.style.display = 'block';
                passwordEl.value = '';
                passwordEl.focus();
            }
        } catch {
            errBox.textContent = 'Network error. Please check your connection and try again.';
            errBox.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Sign in';
        }
    });

    // Clear field-level errors on input
    document.getElementById('email').addEventListener('input', function () {
        document.getElementById('email-error').textContent = '';
        document.getElementById('email-error').classList.remove('visible');
    });

    document.getElementById('password').addEventListener('input', function () {
        document.getElementById('password-error').textContent = '';
        document.getElementById('password-error').classList.remove('visible');
    });
</script>

</body>
</html>
