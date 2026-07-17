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
<?php
$scripts = [
    'https://js.stripe.com/v3/',
    '/public/js/subscription-account.js',
    '/public/js/subscription-account-runtime.js',
    '/public/js/subscription-account-drawer-bootstrap.js',
    '/public/js/subscription-account-management.js',
    '/public/js/subscription-account-payment-method.js',
    '/public/js/subscription-account-history-delivery.js',
    '/public/js/subscription-account-upgrade.js',
    '/public/js/subscription-account-preferences.js',
    '/public/js/subscription-account-delivery-address.js',
    '/public/js/subscription-account-digital-access.js',
    '/public/js/subscription-account-issue-deliveries.js',
];

if (($account_context['show_subscription_modal'] ?? false) === true) {
    $scripts[] = '/public/js/subscription-account-acquisition.js';
}
?>
<?php foreach ($scripts as $script): ?>
    <script src="<?= htmlspecialchars($script) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
