<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page->title ?? 'Article') ?> — OpenCollab</title>
    <link rel="stylesheet" href="/css/open-collab.css">
    <meta name="description"
          content="<?= htmlspecialchars($page->meta_description ?? substr(strip_tags($page->content ?? ''), 0, 155)) ?>">
    <?php if ($page->is_paid && !$accessGranted): ?>
        <meta name="robots" content="noindex">
    <?php endif; ?>
</head>
<body style="background:var(--cream);">

<!-- Minimal public nav -->
<header style="background:#fff;border-bottom:1px solid var(--border);padding:0 32px;height:60px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:50;">
    <a href="/" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
        <div style="width:28px;height:28px;background:var(--navy);border-radius:6px;display:grid;place-items:center;">
            <span style="font-family:var(--font-display);font-weight:700;font-size:14px;color:var(--amber);">O</span>
        </div>
        <span style="font-family:var(--font-display);font-size:.95rem;font-weight:600;color:var(--navy);">OpenCollab</span>
    </a>
    <div style="flex:1;"></div>
    <a href="/login" style="font-size:.85rem;color:var(--slate);text-decoration:none;">Sign in</a>
</header>

<main style="max-width:720px;margin:0 auto;padding:48px 24px 80px;">

    <!-- Article meta -->
    <div style="margin-bottom:32px;">
        <?php if ($page->is_paid): ?>
            <span class="oc-badge oc-badge--paid" style="margin-bottom:12px;display:inline-flex;">PREMIUM</span>
        <?php endif; ?>
        <h1 style="font-family:var(--font-display);font-size:2.5rem;font-weight:700;color:var(--navy);line-height:1.2;margin-bottom:16px;">
            <?= htmlspecialchars($page->title ?? '') ?>
        </h1>
        <div style="display:flex;align-items:center;gap:16px;padding-bottom:24px;border-bottom:1px solid var(--border);">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--navy);display:grid;place-items:center;font-family:var(--font-display);font-weight:700;color:var(--amber);font-size:.85rem;">
                <?= strtoupper(substr($authorName ?? 'A', 0, 1)) ?>
            </div>
            <div>
                <div style="font-weight:500;font-size:.875rem;color:var(--navy);"><?= htmlspecialchars($authorName ?? 'Anonymous') ?></div>
                <div style="font-size:.75rem;color:var(--slate-light);">
                    <?= $page->published_at ? date('d M Y', strtotime($page->published_at)) : '' ?>
                </div>
            </div>
            <?php if ($page->is_paid): ?>
                <div style="margin-left:auto;font-size:.8rem;color:var(--slate);">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="13"
                         style="vertical-align:middle;margin-right:2px;">
                        <path fill-rule="evenodd"
                              d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                              clip-rule="evenodd"/>
                    </svg>
                    Paid article
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Free article OR access granted ── -->
    <?php if (!$page->is_paid || $accessGranted): ?>

        <div style="font-size:1.05rem;line-height:1.85;color:var(--ink);">
            <?= $content ?? '' ?>
        </div>

    <?php else: ?>
        <!-- ── Paid article, no access: show preview + paywall ── -->

        <!-- Preview content (fades out) -->
        <div style="font-size:1.05rem;line-height:1.85;color:var(--ink);">
            <?= $content /* already truncated by controller */ ?>
        </div>

        <!-- Paywall component -->
        <div class="oc-paywall" data-article-id="<?= (int)$page->id ?>" style="margin-top:0;">
            <div class="oc-paywall__gate">
                <div class="oc-paywall__lock">
                    <svg viewBox="0 0 20 20" fill="var(--navy)" width="22">
                        <path fill-rule="evenodd"
                              d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="oc-paywall__headline">Continue reading</div>
                <div class="oc-paywall__sub">
                    Unlock the full article — <?= htmlspecialchars($page->title ?? '') ?>
                </div>
                <div class="oc-paywall__price">
                    <sup>£</sup><?= number_format($page->price / 100, 2) ?>
                </div>

                <div id="paywall-errors" class="oc-form-errors"
                     style="display:none;max-width:360px;margin:0 auto 16px;text-align:left;"></div>

                <!-- Email for guest purchase -->
                <div style="max-width:320px;margin:0 auto 12px;">
                    <input
                            class="oc-input"
                            type="email"
                            id="reader-email"
                            placeholder="Your email address"
                            value="<?= htmlspecialchars($readerEmail ?? '') ?>"
                            style="text-align:center;margin-bottom:10px;"
                    >
                </div>

                <button
                        class="oc-btn oc-btn--amber unlock-btn"
                        style="font-size:1rem;padding:14px 32px;min-width:240px;"
                        id="unlock-btn"
                        onclick="initiateCheckout()"
                >
                    <svg viewBox="0 0 20 20" fill="currentColor" width="18">
                        <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                        <path fill-rule="evenodd"
                              d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"
                              clip-rule="evenodd"/>
                    </svg>
                    Unlock for £<?= number_format($page->price / 100, 2) ?>
                </button>

                <p style="font-size:.75rem;color:var(--slate-light);margin-top:12px;">
                    Secure payment via Stripe · One-time purchase · Instant access
                </p>
            </div>
        </div>

    <?php endif; ?>

</main>

<?php if ($page->is_paid && !$accessGranted): ?>
    <script>
        const PAGE_ID = <?= (int)$page->id ?>;
        const SITE = '<?= htmlspecialchars($site ?? '') ?>';

        async function initiateCheckout() {
            const email = document.getElementById('reader-email').value.trim();
            const errBox = document.getElementById('paywall-errors');
            const btn = document.getElementById('unlock-btn');

            errBox.style.display = 'none';

            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errBox.textContent = 'Please enter a valid email address to complete your purchase.';
                errBox.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div> Connecting to Stripe…';

            try {
                const res = await fetch(`/api/${SITE}/open-collab/pages/${PAGE_ID}/purchase`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify({email})
                });

                const data = await res.json();

                if (res.ok && data.client_secret) {
                    // Use Stripe.js to confirm the payment
                    const stripe = Stripe('<?= htmlspecialchars($stripePublicKey ?? '') ?>');
                    const {error} = await stripe.confirmCardPayment(data.client_secret);

                    if (error) {
                        errBox.textContent = error.message;
                        errBox.style.display = 'block';
                        btn.disabled = false;
                        btn.innerHTML = 'Unlock for £<?= number_format($page->price / 100, 2) ?>';
                    } else {
                        // Payment succeeded — webhook will grant access, reload to check
                        btn.innerHTML = '✓ Payment received — loading…';
                        setTimeout(() => window.location.reload(), 1500);
                    }
                } else if (res.status === 409) {
                    // Already purchased — just reload
                    window.location.reload();
                } else {
                    errBox.textContent = data.message || 'Could not initiate payment. Please try again.';
                    errBox.style.display = 'block';
                    btn.disabled = false;
                    btn.innerHTML = 'Unlock for £<?= number_format($page->price / 100, 2) ?>';
                }
            } catch {
                errBox.textContent = 'Network error. Please check your connection and try again.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = 'Unlock for £<?= number_format($page->price / 100, 2) ?>';
            }
        }
    </script>
    <script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

</body>
</html>