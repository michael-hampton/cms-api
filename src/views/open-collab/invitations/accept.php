<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Your Invitation — OpenCollab</title>
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
                Join a platform where your words have real value. Create premium content,
                build an audience, and receive direct payouts for every article sold.
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

        <?php if ($tokenState === 'pending'): ?>

            <p class="oc-auth__form-sub" style="margin-bottom:8px;">You've been invited to contribute</p>
            <h2 class="oc-auth__form-title">Create your account</h2>

            <!-- Pre-filled email pill -->
            <div class="oc-auth__email-prefill">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                </svg>
                <?= htmlspecialchars($invitation->email) ?>
                <span style="margin-left:auto;font-size:.7rem;color:var(--green);font-weight:600;">✓ Verified</span>
            </div>

            <div id="form-errors" class="oc-form-errors" style="display:none;" role="alert"></div>

            <form id="accept-form" novalidate>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="oc-form-group">
                    <label class="oc-label" for="name">Full name</label>
                    <input class="oc-input" type="text" id="name" name="name"
                           placeholder="Your display name" required autocomplete="name">
                    <div class="oc-error-msg" id="name-error"></div>
                </div>

                <div class="oc-form-group">
                    <label class="oc-label" for="password">Password</label>
                    <div style="position:relative;">
                        <input class="oc-input" type="password" id="password" name="password"
                               placeholder="At least 8 characters" required autocomplete="new-password"
                               minlength="8" style="padding-right:44px;">
                        <button type="button"
                                onclick="togglePass()"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--slate);"
                                aria-label="Toggle password visibility">
                            <svg id="eye-icon" viewBox="0 0 20 20" fill="currentColor" width="18">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd"
                                      d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                    <!-- Password strength bar -->
                    <div style="margin-top:8px;display:flex;gap:4px;" id="strength-bars">
                        <div style="flex:1;height:3px;background:var(--border);border-radius:2px;transition:.2s;"
                             id="sb1"></div>
                        <div style="flex:1;height:3px;background:var(--border);border-radius:2px;transition:.2s;"
                             id="sb2"></div>
                        <div style="flex:1;height:3px;background:var(--border);border-radius:2px;transition:.2s;"
                             id="sb3"></div>
                        <div style="flex:1;height:3px;background:var(--border);border-radius:2px;transition:.2s;"
                             id="sb4"></div>
                    </div>
                    <div class="oc-error-msg" id="pass-error"></div>
                </div>

                <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">
                    Create account & continue
                </button>

                <p style="font-size:.75rem;color:var(--slate);margin-top:16px;text-align:center;">
                    By continuing, you agree to our
                    <a href="/terms" style="color:var(--navy);">Terms</a> and
                    <a href="/privacy" style="color:var(--navy);">Privacy Policy</a>.
                </p>
            </form>

        <?php elseif ($tokenState === 'expired'): ?>
            <div style="text-align:center;padding-top:40px;">
                <div style="width:56px;height:56px;background:var(--amber-pale);border-radius:50%;display:grid;place-items:center;margin:0 auto 20px;">
                    <svg viewBox="0 0 20 20" fill="var(--amber-dark)" width="24">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <h2 class="oc-auth__form-title">Invitation expired</h2>
                <p class="oc-auth__form-sub">This invitation link has expired. Please contact your administrator to
                    request a new one.</p>
            </div>

        <?php elseif ($tokenState === 'revoked'): ?>
            <div style="text-align:center;padding-top:40px;">
                <div style="width:56px;height:56px;background:var(--red-pale);border-radius:50%;display:grid;place-items:center;margin:0 auto 20px;">
                    <svg viewBox="0 0 20 20" fill="var(--red)" width="24">
                        <path fill-rule="evenodd"
                              d="M13.477 14.89A6 6 0 015.11 6.524L13.476 14.89zm1.414-1.414L6.524 5.11A6 6 0 0114.89 13.476zM18 10a8 8 0 11-16 0 8 8 0 0116 0z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <h2 class="oc-auth__form-title">Invitation revoked</h2>
                <p class="oc-auth__form-sub">This invitation has been revoked. Please reach out if you believe this is a
                    mistake.</p>
            </div>

        <?php elseif ($tokenState === 'used'): ?>
            <div style="text-align:center;padding-top:40px;">
                <div style="width:56px;height:56px;background:var(--green-pale);border-radius:50%;display:grid;place-items:center;margin:0 auto 20px;">
                    <svg viewBox="0 0 20 20" fill="var(--green)" width="24">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <h2 class="oc-auth__form-title">Already accepted</h2>
                <p class="oc-auth__form-sub">This invitation has already been used. <a href="/login"
                                                                                       style="color:var(--navy);font-weight:500;">Sign
                        in to your account.</a></p>
            </div>

        <?php else: ?>
            <div style="text-align:center;padding-top:40px;">
                <h2 class="oc-auth__form-title">Invalid invitation</h2>
                <p class="oc-auth__form-sub">This invitation link is not valid. Please check the link or contact your
                    administrator.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
    function togglePass() {
        const pw = document.getElementById('password');
        const icon = document.getElementById('eye-icon');
        const isText = pw.type === 'text';
        pw.type = isText ? 'password' : 'text';
        icon.innerHTML = isText
            ? '<path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>'
            : '<path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074L3.707 2.293zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.515a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>';
    }

    // Password strength indicator
    document.getElementById('password')?.addEventListener('input', function () {
        const val = this.value;
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
        for (let i = 1; i <= 4; i++) {
            document.getElementById('sb' + i).style.background = i <= score ? colors[score] : 'var(--border)';
        }
    });

    // Form submission
    document.getElementById('accept-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const btn = document.getElementById('submit-btn');
        const errBox = document.getElementById('form-errors');
        errBox.style.display = 'none';

        const name = document.getElementById('name').value.trim();
        const password = document.getElementById('password').value;
        const token = this.querySelector('[name="token"]').value;

        if (!name) {
            document.getElementById('name-error').textContent = 'Name is required.';
            document.getElementById('name-error').classList.add('visible');
            return;
        }

        if (password.length < 8) {
            document.getElementById('pass-error').textContent = 'Password must be at least 8 characters.';
            document.getElementById('pass-error').classList.add('visible');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Creating account…';

        try {
            const site = '<?= htmlspecialchars($site ?? '') ?>';
            const res = await fetch(`/api/${site}/open-collab/invitations/${encodeURIComponent(token)}/accept`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({name, password})
            });

            const data = await res.json();

            if (res.ok) {
                const payload = data.data || data;

                // Store token for subsequent requests
                if (payload.token) {
                    localStorage.setItem('oc_token', payload.token);
                }

                window.location.href = `/${site}/open-collab/onboarding`;
            } else {
                errBox.innerHTML = '<strong>Could not create account:</strong>';
                if (data.errors) {
                    const ul = document.createElement('ul');
                    Object.values(data.errors).flat().forEach(msg => {
                        const li = document.createElement('li');
                        li.textContent = msg;
                        ul.appendChild(li);
                    });
                    errBox.appendChild(ul);
                } else {
                    errBox.innerHTML += ' ' + (data.message || 'Please try again.');
                }
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Create account & continue';
            }
        } catch {
            errBox.innerHTML = 'Network error. Please check your connection and try again.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Create account & continue';
        }
    });
</script>
</body>
</html>
