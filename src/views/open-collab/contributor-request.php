<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Contributor — OpenCollab</title>
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
                Write.<br>Publish.<br><em>Earn.</em>
            </h1>
            <p class="oc-auth__tagline">
                Join our contributor network. Share your expertise, build an audience,
                and earn money from every article you publish.
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

        <?php if (($submitted ?? false)): ?>

            <!-- Success state -->
            <div style="text-align:center;padding-top:40px;">
                <div style="width:60px;height:60px;background:var(--green-pale,#dcfce7);border-radius:50%;
                    display:grid;place-items:center;margin:0 auto 20px;">
                    <svg viewBox="0 0 20 20" fill="var(--green,#16a34a)" width="26">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <h2 class="oc-auth__form-title">Request received</h2>
                <?php if (($requiresApproval ?? true)): ?>
                    <p class="oc-auth__form-sub" style="margin-top:8px;">
                        Your request is pending admin review. We'll send your invitation to
                        <strong><?= htmlspecialchars($submittedEmail ?? '') ?></strong> once approved.
                    </p>
                <?php else: ?>
                    <p class="oc-auth__form-sub" style="margin-top:8px;">
                        Check your inbox — we've sent an invitation link to
                        <strong><?= htmlspecialchars($submittedEmail ?? '') ?></strong>.
                    </p>
                <?php endif; ?>
                <a href="/" class="oc-btn oc-btn--ghost" style="margin-top:24px;">Back to site</a>
            </div>

        <?php else: ?>

            <p class="oc-auth__form-sub" style="margin-bottom:8px;">Start contributing today</p>
            <h2 class="oc-auth__form-title">Request contributor access</h2>

            <div id="form-errors" class="oc-form-errors" style="display:none;" role="alert"></div>

            <form id="request-form" novalidate>
                <?php foreach (($requestForm?->fields ?? []) as $field): ?>
                    @include('open-collab/onboarding/partials/profile-field', ['field' => $field])
                <?php endforeach; ?>

                <button type="submit" class="oc-btn oc-btn--amber oc-btn--block" id="submit-btn">
                    Request access
                </button>

                <p style="font-size:.75rem;color:var(--slate);text-align:center;margin-top:16px;">
                    Already have an invitation?
                    <a href="/<?= htmlspecialchars($site ?? '') ?>/open-collab/login"
                       style="color:var(--navy);font-weight:500;">Sign in here.</a>
                </p>
            </form>

        <?php endif; ?>

    </div>
</div>

<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    document.getElementById('request-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const form = e.currentTarget;
        const errBox = document.getElementById('form-errors');
        const btn = document.getElementById('submit-btn');

        errBox.style.display = 'none';
        errBox.textContent = '';

        form.querySelectorAll('.oc-error-msg').forEach(el => {
            el.textContent = '';
            el.classList.remove('visible');
        });

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        Object.keys(payload).forEach(key => {
            if (typeof payload[key] === 'string') {
                payload[key] = payload[key].trim();
            }
        });

        if (payload.email) {
            payload.email = payload.email.toLowerCase();
        }

        let valid = true;

        if (!payload.name) {
            setFieldError('name', 'Name is required.');
            valid = false;
        }

        if (!payload.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) {
            setFieldError('email', 'A valid email is required.');
            valid = false;
        }

        if (!payload.bio || payload.bio.length < 20) {
            setFieldError('bio', 'Please write at least 20 characters.');
            valid = false;
        }

        if (!valid) {
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Submitting…';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/contributor-requests`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();

            if (res.ok) {
                window.location.reload();
                return;
            }

            let msg = data.error || data.message || 'Submission failed.';

            if (data.errors) {
                msg = Object.values(data.errors).flat().join(' ');

                Object.entries(data.errors).forEach(([field, messages]) => {
                    setFieldError(field, Array.isArray(messages) ? messages[0] : messages);
                });
            }

            errBox.textContent = msg;
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Request access';
        } catch {
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Request access';
        }
    });

    function setFieldError(field, message) {
        const el = document.getElementById(`${field}-error`);

        if (!el) {
            return;
        }

        el.textContent = message;
        el.classList.add('visible');
    }
</script>

</body>
</html>