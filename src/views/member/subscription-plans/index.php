<?php
/**
 * @var \App\Models\Site $site
 * @var \App\Models\Member|null $member
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plans — <?= htmlspecialchars($site->name) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Lora:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap"
          rel="stylesheet">
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Updated to match your Light Theme palette */
            --paper: #fcfaf7; /* Main page background */
            --paper-2: #ffffff; /* Pure white for cards */
            --paper-3: #f5f2ee; /* Soft grey-beige for inputs/headers */
            --ink: #1a1c20; /* Deep charcoal for text */
            --ink-2: #2e3136;
            --ink-3: #626770; /* Muted text */
            --accent: #9b6f3b; /* Deep gold/bronze */
            --accent-2: #c1440e; /* Terracotta accent */
            --gold: #b89a4a;
            --text: #1a1c20;
            --text-dim: #626770;
            --border: rgba(0, 0, 0, 0.08);
            --radius: 10px;
            --display: 'Bebas Neue', Impact, sans-serif;
            --serif: 'Lora', Georgia, serif;
            --sans: 'DM Sans', system-ui, sans-serif;
            --shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 8px 40px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: var(--sans);
            background: var(--paper);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── Hero ── */
        .hero {
            background: var(--ink);
            padding: 80px 24px 88px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(
                    -45deg,
                    transparent,
                    transparent 40px,
                    rgba(255, 255, 255, .015) 40px,
                    rgba(255, 255, 255, .015) 41px
            );
        }

        .hero-eyebrow {
            font-family: var(--sans);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 16px;
        }

        .hero-title {
            font-family: var(--display);
            font-size: clamp(56px, 10vw, 110px);
            letter-spacing: .02em;
            color: #ffffff;
            line-height: .95;
            position: relative;
        }

        .hero-title em {
            font-family: var(--serif);
            font-style: italic;
            color: var(--accent);
            font-size: .75em;
        }

        .hero-sub {
            font-family: var(--serif);
            font-style: italic;
            font-size: 20px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 20px;
        }

        /* ── Current plan banner ── */
        .current-banner {
            background: var(--gold);
            color: white;
            text-align: center;
            padding: 13px 24px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .04em;
            display: none;
        }

        .current-banner.visible {
            display: block;
        }

        /* ── Main ── */
        .shell {
            max-width: 1160px;
            margin: 0 auto;
            padding: 56px 24px 80px;
        }

        /* ── Plans grid ── */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        /* ── Plan card ── */
        .plan-card {
            background: var(--paper-2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 40px 36px 36px;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all .3s cubic-bezier(.34, 1.56, .64, 1);
            opacity: 0;
            transform: translateY(16px);
            box-shadow: var(--shadow);
        }

        .plan-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .plan-card:hover {
            border-color: var(--accent);
            box-shadow: var(--shadow-lg);
            transform: translateY(-6px) !important;
        }

        /* Featured Plan Card */
        .plan-card.featured {
            border-color: var(--ink);
            background: var(--ink);
            color: #ffffff;
        }

        .plan-card.featured:hover {
            border-color: var(--accent);
        }

        .featured-tag {
            position: absolute;
            top: -1px;
            right: 24px;
            background: var(--accent);
            color: white;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            padding: 5px 14px;
            border-radius: 0 0 6px 6px;
        }

        .current-tag {
            display: inline-block;
            border: 1px solid var(--gold);
            color: var(--gold);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 4px;
            margin-bottom: 14px;
        }

        .plan-name {
            font-family: var(--display);
            font-size: 42px;
            letter-spacing: .04em;
            line-height: 1;
            margin-bottom: 4px;
            color: var(--text);
        }

        .plan-card.featured .plan-name {
            color: #ffffff;
        }

        .plan-trial {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 14px;
        }

        .plan-desc {
            font-family: var(--serif);
            font-style: italic;
            font-size: 16px;
            color: var(--text-dim);
            margin-bottom: 32px;
            line-height: 1.6;
            flex-shrink: 0;
        }

        .plan-card.featured .plan-desc {
            color: rgba(255, 255, 255, 0.6);
        }

        .plan-price-wrap {
            display: flex;
            align-items: baseline;
            gap: 4px;
            margin-bottom: 4px;
        }

        .plan-currency {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-dim);
        }

        .plan-amount {
            font-family: var(--display);
            font-size: 64px;
            letter-spacing: -.01em;
            line-height: 1;
            color: var(--ink);
        }

        .plan-card.featured .plan-amount {
            color: #ffffff;
        }

        .plan-card.featured .plan-currency {
            color: rgba(255, 255, 255, 0.5);
        }

        .plan-period {
            font-size: 13px;
            color: var(--text-dim);
            margin-bottom: 32px;
        }

        .plan-card.featured .plan-period {
            color: rgba(255, 255, 255, 0.5);
        }

        /* Features */
        .plan-features {
            list-style: none;
            border-top: 1px solid var(--border);
            padding-top: 24px;
            margin-bottom: 32px;
            flex: 1;
        }

        .plan-card.featured .plan-features {
            border-color: rgba(255, 255, 255, .1);
        }
        .plan-features li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 8px 0;
            font-size: 14px;
            color: var(--ink);
        }

        .plan-card.featured .plan-features li {
            color: rgba(255, 255, 255, 0.85);
        }

        .plan-features li::before {
            content: '→';
            color: var(--accent);
            flex-shrink: 0;
            font-weight: 700;
        }

        /* ── Voucher form ── */
        .voucher-form {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .voucher-input {
            flex: 1;
            background: var(--paper-3);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 12px 14px;
            font-size: 13px;
            font-family: var(--sans);
            color: var(--ink);
            outline: none;
        }

        .plan-card.featured .voucher-input {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.1);
        }

        .voucher-input:focus {
            border-color: var(--accent);
        }

        .btn-voucher {
            background: var(--paper-3);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 10px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-dim);
        }

        .plan-card.featured .btn-voucher {
            background: rgba(255, 255, 255, 0.1);
            border-color: transparent;
            color: #ffffff;
        }

        .voucher-msg {
            font-size: 12px;
            min-height: 18px;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .voucher-msg.ok {
            color: var(--ok);
        }

        .voucher-msg.fail {
            color: var(--fail);
        }

        /* ── Subscribe button ── */
        .btn-subscribe {
            width: 100%;
            padding: 18px 24px;
            border: none;
            border-radius: var(--radius);
            font-family: var(--display);
            font-size: 24px;
            letter-spacing: .06em;
            cursor: pointer;
            background: var(--accent);
            color: white;
            transition: all .2s;
        }

        .btn-subscribe:hover:not(:disabled) {
            background: var(--accent-2);
            transform: translateY(-1px);
        }

        .plan-card.featured .btn-subscribe {
            background: #ffffff;
            color: var(--ink);
        }

        .plan-card.featured .btn-subscribe:hover:not(:disabled) {
            background: var(--paper-3);
        }

        .btn-disabled-label {
            width: 100%;
            padding: 18px 24px;
            border-radius: var(--radius);
            font-family: var(--display);
            font-size: 24px;
            background: var(--paper-3);
            color: var(--text-dim);
            text-align: center;
        }

        .plan-card.featured .btn-disabled-label {
            background: rgba(255, 255, 255, .1);
            color: rgba(255, 255, 255, .3);
        }

        /* ── Skeleton ── */
        .skel {
            background: linear-gradient(90deg, var(--paper-3) 25%, var(--border) 50%, var(--paper-3) 75%);
            background-size: 600px 100%;
            animation: shimmer 1.4s infinite linear;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns:1fr;
            }

            .hero-title {
                font-size: 72px;
            }
        }
    </style
</head>
<body>

@include('member._header')

<div class="hero">
    <p class="hero-eyebrow"><?= htmlspecialchars($site->name) ?> — Membership</p>
    <h1 class="hero-title">Choose<br><em>your plan</em></h1>
    <p class="hero-sub">Unlock everything in seconds.</p>
</div>

<div class="current-banner" id="currentBanner"></div>

<main class="shell" id="plansApp"
      data-api-url="/api/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscription-plans"
      data-site-slug="<?= htmlspecialchars(\App\Framework\Support\SiteContext::slug()) ?>">
    <div class="plans-grid" id="plansGrid">
        <!-- Skeleton placeholders -->
        <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="plan-card">
                <div class="skel" style="width:60%;height:36px;margin-bottom:16px"></div>
                <div class="skel" style="width:90%;height:14px;margin-bottom:8px"></div>
                <div class="skel" style="width:70%;height:14px;margin-bottom:28px"></div>
                <div class="skel" style="width:50%;height:56px;margin-bottom:28px"></div>
                <div class="skel" style="width:100%;height:48px"></div>
            </div>
        <?php endfor; ?>
    </div>
</main>

<script>
    /**
     * PlansPage — drives the Subscription Plans listing view.
     * All DOM construction goes through app-core UI helpers.
     */
    class PlansPage {
        /** @param {HTMLElement} root  The #plansApp shell element */
        constructor(root) {
            this.root = root;
            this.apiUrl = root.dataset.apiUrl;
            this.siteSlug = root.dataset.siteSlug;

            this.plansGrid = document.getElementById('plansGrid');
            this.currentBanner = document.getElementById('currentBanner');

            /** Voucher state keyed by plan slug */
            this.vouchers = {};
        }

        async init() {
            try {
                const res = await api(this.apiUrl);
                this.renderBanner(res.data.currentSubscription);
                this.renderPlans(res.data.plans, res.data.currentSubscription);
            } catch (err) {
                UI.toast(err.message || 'Failed to load plans', 'error');
                UI.render(this.plansGrid, [
                    UI.emptyState({icon: '⚠️', title: 'Unable to load plans', body: 'Please refresh the page.'}),
                ]);
            }
        }

        // ── Banner ─────────────────────────────────────────────────────────────

        renderBanner(currentSub) {
            if (!currentSub) return;
            UI.text(this.currentBanner, `✓ You are currently on the ${currentSub.plan_name} plan`);
            this.currentBanner.classList.add('visible');
        }

        // ── Plans grid ─────────────────────────────────────────────────────────

        renderPlans(plans, currentSub) {
            if (!plans || !plans.length) {
                UI.render(this.plansGrid, [
                    UI.emptyState({icon: '📦', title: 'No plans available', body: 'Check back soon.'}),
                ]);
                return;
            }

            UI.render(this.plansGrid, plans.map((plan, i) => {
                const card = this.planCard(plan, currentSub);
                setTimeout(() => card.classList.add('visible'), i * 90);
                return card;
            }));
        }

        planCard(plan, currentSub) {
            const isCurrent = currentSub && currentSub.plan_id === plan.id;
            const hasActiveSub = isCurrent;
            console.log(currentSub.plan_id, plan.id, hasActiveSub, isCurrent)

            const card = UI.el('div', {
                className: `plan-card ${plan.is_featured ? 'featured' : ''}`,
                'data-slug': plan.slug,
            });

            // Featured tag
            if (plan.is_featured) {
                card.appendChild(UI.el('div', {className: 'featured-tag'}, ['Most Popular']));
            }

            // Current plan tag
            if (isCurrent) {
                card.appendChild(UI.el('div', {className: 'current-tag'}, ['✓ Your Plan']));
            }

            // Name + trial
            card.appendChild(UI.el('div', {className: 'plan-name'}, [plan.name]));
            if (plan.trial_days > 0) {
                card.appendChild(UI.el('div', {className: 'plan-trial'}, [`🎉 ${plan.trial_days}-day free trial`]));
            }

            // Description
            card.appendChild(UI.el('div', {className: 'plan-desc'}, [
                plan.description || 'Get access to all premium features.',
            ]));

            // Price
            const priceWrap = UI.el('div', {className: 'plan-price-wrap'}, [
                UI.el('span', {className: 'plan-currency'}, [plan.currency]),
                UI.el('span', {className: 'plan-amount'}, [Number(plan.price).toFixed(2)]),
            ]);
            card.appendChild(priceWrap);
            card.appendChild(UI.el('div', {className: 'plan-period'}, [
                `per ${plan.billing_period_label ?? plan.billing_period ?? 'period'}`,
            ]));

            // Features
            if (plan.features && plan.features.length) {
                const ul = UI.el('ul', {className: 'plan-features'});
                plan.features.forEach(f => ul.appendChild(UI.el('li', {}, [f])));
                card.appendChild(ul);
            }

            // CTA area
            if (isCurrent) {
                card.appendChild(UI.el('div', {className: 'btn-disabled-label'}, ['Current Plan']));
            } else if (hasActiveSub) {
                card.appendChild(UI.el('div', {className: 'btn-disabled-label'}, ['Already Subscribed']));
            } else {
                // Voucher form
                const voucherInput = UI.el('input', {
                    className: 'voucher-input',
                    type: 'text',
                    placeholder: 'Voucher code (optional)',
                    'data-plan-slug': plan.slug,
                });
                const applyBtn = UI.el('button', {
                    className: 'btn-voucher',
                    onclick: () => this.applyVoucher(plan, voucherInput, voucherMsg, priceEl),
                }, ['Apply']);

                const voucherMsg = UI.el('div', {className: 'voucher-msg'});

                const voucherForm = UI.el('div', {className: 'voucher-form'}, [voucherInput, applyBtn]);
                card.appendChild(voucherForm);
                card.appendChild(voucherMsg);

                // Discounted price display (hidden until voucher applied)
                const priceEl = UI.el('div', {
                    className: 'voucher-msg ok',
                    style: 'display:none;margin-bottom:10px',
                });
                card.appendChild(priceEl);

                // Subscribe button
                const subscribeBtn = UI.el('button', {
                    className: `btn-subscribe`,
                    onclick: () => this.subscribe(plan, subscribeBtn, voucherInput),
                }, ['Subscribe Now']);
                card.appendChild(subscribeBtn);
            }

            return card;
        }

        // ── Voucher ────────────────────────────────────────────────────────────

        async applyVoucher(plan, input, msgEl, priceEl) {
            const code = input.value.trim();
            UI.text(msgEl, '');
            msgEl.className = 'voucher-msg';
            priceEl.style.display = 'none';

            if (!code) {
                UI.text(msgEl, 'Enter a voucher code first.');
                msgEl.classList.add('fail');
                return;
            }

            try {
                const data = await api(`${this.apiUrl}/${plan.slug}/validate-voucher`, {
                    method: 'POST',
                    body: JSON.stringify({voucher_code: code}),
                });

                this.vouchers[plan.slug] = {code, discount: data.discount};
                UI.text(msgEl, `✓ ${data.message}`);
                msgEl.classList.add('ok');

                UI.text(priceEl, `Final price: ${plan.currency} ${Number(data.final_price).toFixed(2)} (save ${Number(data.discount).toFixed(2)})`);
                priceEl.style.display = 'block';
            } catch (err) {
                delete this.vouchers[plan.slug];
                UI.text(msgEl, err.message || 'Invalid voucher code.');
                msgEl.classList.add('fail');
            }
        }

        // ── Subscribe ──────────────────────────────────────────────────────────

        async subscribe(plan, btn, voucherInput) {
            btn.disabled = true;
            btn.classList.add('loading');

            const voucher = this.vouchers[plan.slug];
            const payload = {voucher_code: voucher?.code ?? null};

            try {
                await api(`${this.apiUrl}/${plan.slug}/subscribe`, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                btn.classList.remove('loading');
                btn.classList.add('success-state');
                UI.text(btn, '✓ Subscribed!');

                setTimeout(() => {
                    window.location.href = `/${this.siteSlug}/member/subscriptions`;
                }, 1200);
            } catch (err) {
                btn.classList.remove('loading');
                btn.disabled = false;
                UI.toast(err.message || 'Failed to subscribe. Please try again.', 'error');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('plansApp');
        if (root) new PlansPage(root).init();
    });
</script>
</body>
</html>