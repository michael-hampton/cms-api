<?php
/** @var \App\Models\Member $member */
/** @var \App\Models\Site $site */

$pageTitle = 'My Subscriptions';
?>

@include('member/_header')

<meta name="csrf-token" content="<?= htmlspecialchars(\App\Framework\Security\Csrf::getToken()) ?>">
<link rel="stylesheet" href="/public/css/subscription-account.css">
<link rel="stylesheet" href="/public/css/subscription-account-drawer.css">
<link rel="stylesheet" href="/public/css/subscription-account-delivery.css">
<link rel="stylesheet" href="/public/css/subscription-account-upgrade.css">
<link rel="stylesheet" href="/public/css/member-subscription-account.css">
<style>
    .member-subscription-account .empty-state {
        position: relative;
        display: grid;
        justify-items: center;
        gap: 12px;
        padding: clamp(36px, 6vw, 64px) 24px;
        text-align: center;
    }

    .member-subscription-account .empty-state::before {
        display: grid;
        width: 72px;
        height: 72px;
        margin-bottom: 4px;
        place-items: center;
        background: linear-gradient(135deg, rgba(49, 87, 213, .14), rgba(49, 87, 213, .06));
        border: 1px solid rgba(49, 87, 213, .16);
        border-radius: 22px;
        color: var(--subscription-primary);
        content: "★";
        font-size: 30px;
        font-weight: 900;
        box-shadow: 0 14px 30px rgba(49, 87, 213, .12);
    }

    .member-subscription-account .empty-state__title {
        margin: 0;
        color: var(--subscription-text);
        font-size: clamp(24px, 3vw, 32px);
        font-weight: 850;
        letter-spacing: -.03em;
    }

    .member-subscription-account .empty-state__sub {
        max-width: 460px;
        margin: 0;
        font-size: 15px;
        line-height: 1.65;
    }

    .member-subscription-account .card:has(.empty-state) {
        overflow: hidden;
        background:
            radial-gradient(circle at top left, rgba(49, 87, 213, .10), transparent 34%),
            linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        border-color: rgba(49, 87, 213, .14);
        box-shadow: 0 18px 46px rgba(16, 24, 40, .09);
    }
</style>

<main class="member-subscription-account member-subscription-account__page">
    @include('subscriptions/shared/_subscription_account')
</main>

<script>
    window.SubscriptionAccountStripeKey = <?= json_encode(
        $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key')
    ) ?>;
</script>
<script src="https://js.stripe.com/v3/" defer></script>
<script src="/public/js/subscription-account.js" defer></script>
<script src="/public/js/subscription-account-runtime.js" defer></script>
<script src="/public/js/subscription-account-drawer-bootstrap.js" defer></script>
<script src="/public/js/subscription-account-management.js" defer></script>
<script src="/public/js/subscription-account-history-delivery.js" defer></script>
<script src="/public/js/subscription-account-upgrade.js" defer></script>
<script src="/public/js/subscription-account-preferences.js" defer></script>
<script src="/public/js/subscription-account-delivery-address.js" defer></script>
<script src="/public/js/subscription-account-digital-access.js" defer></script>
<script src="/public/js/subscription-account-issue-deliveries.js" defer></script>
<script src="/public/js/subscription-account-acquisition.js" defer></script>
</body>
</html>