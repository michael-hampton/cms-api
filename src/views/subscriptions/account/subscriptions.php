<?php $page_title = 'Subscriptions'; ?>
@include('subscriptions/account/_layout')
<main class="page-content">
    @include('subscriptions/shared/_subscription_account')
</main>
</div>
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
</body>
</html>
